<?php

namespace App\Collectors;

use App\Services\Normalization\RegionResolver;
use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class Chute13ClubCollector implements OddsCollector
{
    private const array DATES = ['today', 'tomorrow', 'week'];

    public function __construct(
        private readonly Repository $config,
        private readonly RegionResolver $regionResolver,
    ) {}

    public function source(): string
    {
        return 'chute13';
    }

    /** @return Collection<int, CollectedOddsEvent> */
    public function collect(): Collection
    {
        $cookies = new CookieJar;
        $page = Http::withOptions(['cookies' => $cookies])
            ->accept('text/html')
            ->timeout(10)
            ->connectTimeout(3)
            ->get($this->config->string('services.bookmakers.chute13.games_url'))
            ->throw();
        $this->throwIfRateLimited($page);

        if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $page->body(), $matches) !== 1) {
            throw new RuntimeException('Chute13 Club: token da página pública não encontrado.');
        }

        $events = collect();
        $apiUrl = rtrim($this->config->string('services.bookmakers.chute13.website_url'), '/').'/web/leagues';

        foreach (self::DATES as $date) {
            $response = Http::withOptions(['cookies' => $cookies])
                ->acceptJson()
                ->withHeaders(['X-CSRF-TOKEN' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5)])
                ->timeout(10)
                ->connectTimeout(3)
                ->post($apiUrl, ['sport_id' => 1, 'date' => $date])
                ->throw();
            $this->throwIfRateLimited($response);
            $leagues = $response->json('leagues');

            if (! is_array($leagues)) {
                throw new RuntimeException('Chute13 Club: resposta de ligas inválida.');
            }

            foreach ($leagues as $league) {
                if (! is_array($league)) {
                    continue;
                }

                foreach ($league['matches'] ?? [] as $match) {
                    $event = is_array($match) ? $this->eventFromMatch($league, $match) : null;

                    if ($event !== null) {
                        $events->push($event);
                    }
                }
            }
        }

        return $events
            ->unique(fn (CollectedOddsEvent $event): string => Str::lower($event->homeTeam.'|'.$event->awayTeam.'|'.$event->startsAt?->toIso8601String()))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $league
     * @param  array<string, mixed>  $match
     */
    private function eventFromMatch(array $league, array $match): ?CollectedOddsEvent
    {
        if (($match['sport_slug'] ?? null) !== 'soccer'
            || ($match['active'] ?? false) !== true
            || ! is_string($match['home_team'] ?? null)
            || ! is_string($match['away_team'] ?? null)
            || ! is_string($match['match_date'] ?? null)) {
            return null;
        }

        $selections = [];

        foreach ($match['quotations'] ?? [] as $quotation) {
            if (! is_array($quotation) || (string) ($quotation['bet_slug'] ?? '') !== '3') {
                continue;
            }

            $choice = (string) ($quotation['choice_slug'] ?? '');
            $label = ['1' => 'Casa', 'X' => 'Empate', '2' => 'Fora'][$choice] ?? null;
            $value = $quotation['value'] ?? null;

            if ($label !== null && is_numeric($value) && (float) $value > 0) {
                $selections[$choice] = ['label' => $label, 'odd' => (float) $value];
            }
        }

        if (count($selections) !== 3) {
            return null;
        }

        try {
            $startsAt = CarbonImmutable::parse($match['match_date'])->utc();
        } catch (Throwable) {
            return null;
        }

        $localStart = $startsAt->setTimezone($this->config->string('app.display_timezone'));
        $country = is_array($league['country'] ?? null) ? $league['country'] : [];
        $countryName = is_string($country['name'] ?? null) ? $country['name'] : null;
        $countryCode = is_string($country['code'] ?? null) ? Str::upper($country['code']) : null;
        $competition = is_string($league['name'] ?? null) ? $league['name'] : null;

        return new CollectedOddsEvent(
            source: $this->source(),
            homeTeam: Str::squish($match['home_team']),
            awayTeam: Str::squish($match['away_team']),
            eventDate: $localStart->format('d/m'),
            eventTime: $localStart->format('H:i'),
            startsAt: $startsAt,
            region: $this->regionResolver->resolve($countryCode, $countryName, $competition),
            country: $countryName,
            countryCode: $countryCode,
            competition: $competition,
            markets: [
                'match_winner' => new CollectedMarket(
                    key: 'match_winner',
                    name: 'Vencedor do Encontro',
                    selections: [$selections['1'], $selections['X'], $selections['2']],
                ),
            ],
            collectedAt: CarbonImmutable::now(),
        );
    }

    private function throwIfRateLimited(Response $response): void
    {
        if (Str::contains($response->body(), 'error code: 1015', ignoreCase: true)) {
            throw $response->toException() ?? new RequestException($response);
        }
    }
}
