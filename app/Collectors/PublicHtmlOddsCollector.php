<?php

namespace App\Collectors;

use App\Services\Normalization\RegionResolver;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

abstract class PublicHtmlOddsCollector implements OddsCollector
{
    /** @var array<string, int> */
    private const array MONTHS = [
        'jan' => 1,
        'fev' => 2,
        'mar' => 3,
        'abr' => 4,
        'mai' => 5,
        'jun' => 6,
        'jul' => 7,
        'ago' => 8,
        'set' => 9,
        'out' => 10,
        'nov' => 11,
        'dez' => 12,
    ];

    public function __construct(
        private readonly Repository $config,
        private readonly MarketCatalog $marketCatalog,
        private readonly RegionResolver $regionResolver,
    ) {}

    public function collect(): Collection
    {
        $landingPage = $this->fetchHtml($this->gamesUrl());
        $weekUrls = $this->weekGamesUrls($landingPage['html'], $landingPage['url']);
        $weekPages = collect();
        $rateLimitException = null;

        foreach ($weekUrls as $url) {
            if ($url === $landingPage['url']) {
                $weekPages->push($landingPage);

                continue;
            }

            try {
                $weekPages->push($this->fetchHtml($url));
            } catch (ConnectionException|RequestException $exception) {
                Log::warning('OddRadar weekly page could not be collected.', [
                    'source' => $this->source(),
                    'url' => $url,
                    'status' => $exception instanceof RequestException ? $exception->response->status() : null,
                ]);

                if ($exception instanceof RequestException && $this->isRateLimitedResponse($exception->response)) {
                    $rateLimitException = $exception;

                    break;
                }
            }
        }

        if ($weekPages->isEmpty()) {
            $weekPages = collect([$landingPage]);
        }
        $listedEvents = $weekPages
            ->flatMap(fn (array $page): Collection => $this->listedEvents($page['html'], $page['url']))
            ->unique(fn (array $listedEvent): string => $this->listedEventIdentity($listedEvent['event']))
            ->values();

        if ($rateLimitException !== null) {
            throw new PartialCollectionRateLimited($listedEvents->pluck('event'), $rateLimitException);
        }

        [$events, $rateLimitException] = $this->addDetailMarkets($listedEvents);
        $events = $events
            ->filter(fn (CollectedOddsEvent $event): bool => $event->markets !== [])
            ->values();

        if ($rateLimitException !== null) {
            throw new PartialCollectionRateLimited($events, $rateLimitException);
        }

        return $events;
    }

    /** @return array{html: string, url: string} */
    protected function fetchHtml(string $url): array
    {
        $response = Http::accept('text/html')
            ->timeout(10)
            ->connectTimeout(3)
            ->retry([100, 500], when: function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            })
            ->get($url)
            ->throw();

        return [
            'html' => $response->body(),
            'url' => (string) $response->effectiveUri(),
        ];
    }

    protected function gamesUrl(): string
    {
        return $this->config->string($this->gamesUrlConfigKey());
    }

    abstract public function source(): string;

    abstract protected function gamesUrlConfigKey(): string;

    /** @return array<int, string> */
    private function weekGamesUrls(string $html, string $landingUrl): array
    {
        $xpath = $this->xpath($html);
        $links = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' submenuItem ')]".
            "[.//span[contains(concat(' ', normalize-space(@class), ' '), ' name ') and normalize-space() = 'Jogos do Dia']]".
            "//a[contains(@href, 'jogos.aspx')]",
        );
        $urls = collect($links === false ? [] : iterator_to_array($links))
            ->map(function (DOMNode $link) use ($landingUrl): ?string {
                $href = $link->attributes?->getNamedItem('href')?->nodeValue;

                return $href === null ? null : $this->absoluteUrl($landingUrl, html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
            })
            ->filter()
            ->unique()
            ->take(7)
            ->values()
            ->all();

        return $urls === [] ? [$landingUrl] : $urls;
    }

    /** @return Collection<int, array{event: CollectedOddsEvent, details_url: string|null}> */
    private function listedEvents(string $html, string $pageUrl): Collection
    {
        $xpath = $this->xpath($html);
        $countryGroups = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' pais ')]");

        if ($countryGroups !== false && $countryGroups->length > 0) {
            return collect(iterator_to_array($countryGroups))->flatMap(function (DOMNode $countryGroup) use ($xpath, $pageUrl): array {
                $location = $this->locationForGroup($xpath, $countryGroup);

                if ($this->isPromotionalMarketGroup($location['competition'])) {
                    return [];
                }

                $cards = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' cardItem ')]", $countryGroup);

                return collect($cards === false ? [] : iterator_to_array($cards))
                    ->map(fn (DOMNode $card): ?array => $this->listedEventFromCard($xpath, $card, $pageUrl, $location))
                    ->filter()
                    ->values()
                    ->all();
            })->values();
        }

        $cards = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' cardItem ')]");

        return collect($cards === false ? [] : iterator_to_array($cards))
            ->map(fn (DOMNode $card): ?array => $this->listedEventFromCard($xpath, $card, $pageUrl, [
                'region' => null,
                'country' => null,
                'country_code' => null,
                'competition' => null,
            ]))
            ->filter()
            ->values();
    }

    /**
     * @param  array{region: string|null, country: string|null, country_code: string|null, competition: string|null}  $location
     * @return array{event: CollectedOddsEvent, details_url: string|null}|null
     */
    private function listedEventFromCard(DOMXPath $xpath, DOMNode $card, string $pageUrl, array $location): ?array
    {
        $teams = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' nameTeam ')]//span",
            $card,
        );

        if ($teams === false || $teams->length !== 2) {
            return null;
        }

        $homeTeam = $this->textContent($teams->item(0));
        $awayTeam = $this->textContent($teams->item(1));

        if ($homeTeam === null || $awayTeam === null) {
            return null;
        }

        $eventDate = $this->valueForClass($xpath, $card, 'date');
        $eventTime = $this->valueForClass($xpath, $card, 'hour');
        $detailsHref = $xpath->evaluate(
            "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' totalOutcomes-button ')]/@href)",
            $card,
        );

        return [
            'event' => new CollectedOddsEvent(
                source: $this->source(),
                homeTeam: $homeTeam,
                awayTeam: $awayTeam,
                eventDate: $eventDate,
                eventTime: $eventTime,
                startsAt: $this->startsAt($eventDate, $eventTime),
                region: $location['region'],
                country: $location['country'],
                countryCode: $location['country_code'],
                competition: $location['competition'],
                markets: $this->mainMarket($xpath, $card),
                collectedAt: CarbonImmutable::now(),
            ),
            'details_url' => $detailsHref === '' ? null : $this->absoluteUrl($pageUrl, html_entity_decode($detailsHref, ENT_QUOTES | ENT_HTML5)),
        ];
    }

    /** @return array{region: string|null, country: string|null, country_code: string|null, competition: string|null} */
    private function locationForGroup(DOMXPath $xpath, DOMNode $countryGroup): array
    {
        $header = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' eventlist-country ')]",
            $countryGroup,
        )?->item(0);
        $description = $header === null ? null : $this->textContent($xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' name ')]",
            $header,
        )?->item(0));
        [$country, $competition] = $description === null
            ? [null, null]
            : array_pad(explode(' - ', $description, 2), 2, null);
        $flagClass = $header === null ? '' : $xpath->evaluate(
            "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' flag ')]/@class)",
            $header,
        );
        $countryCode = Str::of($flagClass)->match('/(?:^|\s)flag-([A-Z]{3})(?:\s|$)/')->toString() ?: null;

        return [
            'region' => $this->regionResolver->resolve($countryCode, $country, $competition),
            'country' => $country,
            'country_code' => $countryCode,
            'competition' => $competition,
        ];
    }

    private function isPromotionalMarketGroup(?string $competition): bool
    {
        if ($competition === null) {
            return false;
        }

        return Str::of($competition)
            ->ascii()
            ->lower()
            ->contains(['chutes ao gol', 'defesas de goleiro']);
    }

    /** @return array<string, CollectedMarket> */
    private function mainMarket(DOMXPath $xpath, DOMNode $card): array
    {
        $odds = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' outcomesMain ')]".
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' odd ')]",
            $card,
        );

        if ($odds === false || $odds->length !== 3) {
            return [];
        }

        $values = collect(iterator_to_array($odds))
            ->map(fn (DOMNode $odd): ?float => $this->oddValue($this->textContent($odd)));

        if ($values->contains(null)) {
            return [];
        }

        return [
            'match_winner' => new CollectedMarket(
                key: 'match_winner',
                name: 'Vencedor do Encontro',
                selections: collect(['Casa', 'Empate', 'Fora'])
                    ->map(fn (string $label, int $index): array => ['label' => $label, 'odd' => $values[$index]])
                    ->all(),
            ),
        ];
    }

    /**
     * @param  Collection<int, array{event: CollectedOddsEvent, details_url: string|null}>  $listedEvents
     * @return array{Collection<int, CollectedOddsEvent>, RequestException|null}
     */
    private function addDetailMarkets(Collection $listedEvents): array
    {
        $eventsWithDetails = $listedEvents->filter(fn (array $listedEvent): bool => $listedEvent['details_url'] !== null);

        if ($eventsWithDetails->isEmpty()) {
            return [$listedEvents->pluck('event'), null];
        }

        $concurrency = max(1, $this->config->integer('oddradar.collection_detail_concurrency', 2));
        $responses = [];
        $rateLimitException = null;

        foreach ($eventsWithDetails->chunk($concurrency) as $batch) {
            $batchResponses = Http::pool(
                fn (Pool $pool): array => $batch
                    ->map(fn (array $listedEvent, int $index) => $pool->as((string) $index)
                        ->accept('text/html')
                        ->timeout(10)
                        ->connectTimeout(3)
                        ->get($listedEvent['details_url']))
                    ->all(),
                concurrency: $concurrency,
            );
            $responses += $batchResponses;

            foreach ($batchResponses as $response) {
                if ($response instanceof Response && $this->isRateLimitedResponse($response)) {
                    $rateLimitException = $response->toException() ?? new RequestException($response);

                    break 2;
                }
            }
        }

        $events = $listedEvents->map(function (array $listedEvent, int $index) use ($responses): CollectedOddsEvent {
            $event = $listedEvent['event'];
            $response = $responses[(string) $index] ?? null;

            if (! $response instanceof Response || $response->failed()) {
                if ($listedEvent['details_url'] !== null) {
                    Log::warning('OddRadar event markets could not be collected.', [
                        'source' => $this->source(),
                        'event' => $event->homeTeam.' x '.$event->awayTeam,
                        'url' => $listedEvent['details_url'],
                    ]);
                }

                return $event;
            }

            return new CollectedOddsEvent(
                source: $event->source,
                homeTeam: $event->homeTeam,
                awayTeam: $event->awayTeam,
                eventDate: $event->eventDate,
                eventTime: $event->eventTime,
                startsAt: $event->startsAt,
                region: $event->region,
                country: $event->country,
                countryCode: $event->countryCode,
                competition: $event->competition,
                markets: [...$event->markets, ...$this->detailMarkets($response->body())],
                collectedAt: $event->collectedAt,
            );
        });

        return [$events, $rateLimitException];
    }

    /** @return array<string, CollectedMarket> */
    private function detailMarkets(string $html): array
    {
        $xpath = $this->xpath($html);
        $marketNodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' eventdetail-market ')]");

        return collect($marketNodes === false ? [] : iterator_to_array($marketNodes))
            ->map(function (DOMNode $marketNode) use ($xpath): ?CollectedMarket {
                $sourceName = $this->textContent($xpath->query(
                    ".//*[contains(concat(' ', normalize-space(@class), ' '), ' eventdetail-market-header ')]".
                    "//*[contains(concat(' ', normalize-space(@class), ' '), ' name ')]",
                    $marketNode,
                )?->item(0));
                $market = $sourceName === null ? null : $this->marketCatalog->find($sourceName);

                if ($market === null) {
                    return null;
                }

                $optionNodes = $xpath->query(
                    ".//*[contains(concat(' ', normalize-space(@class), ' '), ' eventdetail-optionItem ')]",
                    $marketNode,
                );
                $selections = collect($optionNodes === false ? [] : iterator_to_array($optionNodes))
                    ->map(function (DOMNode $optionNode) use ($xpath): ?array {
                        $label = $this->textContent($xpath->query(
                            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' name ')]",
                            $optionNode,
                        )?->item(0));
                        $odd = $this->oddValue($this->textContent($xpath->query(
                            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' odd ')]",
                            $optionNode,
                        )?->item(0)));

                        return $label === null || $odd === null ? null : ['label' => $label, 'odd' => $odd];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return $selections === [] ? null : new CollectedMarket($market['key'], $market['name'], $selections);
            })
            ->filter()
            ->mapWithKeys(fn (CollectedMarket $market): array => [$market->key => $market])
            ->all();
    }

    private function startsAt(?string $eventDate, ?string $eventTime): ?CarbonImmutable
    {
        if ($eventDate === null || $eventTime === null) {
            return null;
        }

        $date = Str::of($eventDate)->ascii()->lower()->match('/\d{1,2}\s*\/\s*[a-z]{3}/')->toString();
        $time = Str::of($eventTime)->match('/\d{1,2}:\d{2}/')->toString();

        if ($date === '' || $time === '') {
            return null;
        }

        [$day, $monthName] = explode('/', Str::replace(' ', '', $date));
        $month = self::MONTHS[$monthName] ?? null;

        if ($month === null) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));
        $now = CarbonImmutable::now(config('app.display_timezone'));
        $startsAt = CarbonImmutable::create($now->year, $month, (int) $day, $hour, $minute, 0, $now->timezone);

        if ($startsAt->lessThan($now->subMonths(6))) {
            return $startsAt->addYear()->utc();
        }

        if ($startsAt->greaterThan($now->addMonths(6))) {
            return $startsAt->subYear()->utc();
        }

        return $startsAt->utc();
    }

    private function isRateLimitedResponse(Response $response): bool
    {
        return $response->status() === 429
            || Str::contains($response->body(), 'error code: 1015', ignoreCase: true);
    }

    private function listedEventIdentity(CollectedOddsEvent $event): string
    {
        return Str::of($event->homeTeam.'|'.$event->awayTeam.'|'.($event->startsAt?->toIso8601String() ?? ''))
            ->ascii()
            ->lower()
            ->toString();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        return new DOMXPath($document);
    }

    private function valueForClass(DOMXPath $xpath, DOMNode $card, string $class): ?string
    {
        $nodes = $xpath->query(
            sprintf(".//*[contains(concat(' ', normalize-space(@class), ' '), ' %s ')]", $class),
            $card,
        );

        return $nodes === false ? null : $this->textContent($nodes->item(0));
    }

    private function textContent(?DOMNode $node): ?string
    {
        $value = Str::squish($node?->textContent ?? '');

        return $value === '' ? null : $value;
    }

    private function oddValue(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->trim()->replace(' ', '');

        if ($normalized->contains(',')) {
            $normalized = $normalized->replace('.', '')->replace(',', '.');
        }

        $odd = $normalized->toFloat();

        return $odd > 0 ? $odd : null;
    }

    private function absoluteUrl(string $baseUrl, string $href): string
    {
        if (Str::startsWith($href, ['https://', 'http://'])) {
            return $href;
        }

        $origin = Str::of($baseUrl)->match('/^https?:\/\/[^\/]+/')->toString();

        if (Str::startsWith($href, '/')) {
            return $origin.$href;
        }

        $path = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
        $directory = Str::beforeLast($path, '/');

        return $origin.'/'.trim($directory.'/'.ltrim($href, './'), '/');
    }
}
