<?php

namespace App\Collectors;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class PublicHtmlOddsCollector implements OddsCollector
{
    private const string MARKET = '1x2';

    public function __construct(private readonly Repository $config) {}

    public function collect(): Collection
    {
        return $this->collectHtml($this->fetchHtml($this->gamesUrl()));
    }

    protected function fetchHtml(string $url): string
    {
        return Http::accept('text/html')
            ->timeout(10)
            ->connectTimeout(3)
            ->retry([100, 500], when: function (\Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            })
            ->get($url)
            ->throw()
            ->body();
    }

    protected function gamesUrl(): string
    {
        return $this->config->string($this->gamesUrlConfigKey());
    }

    protected function collectHtml(string $html): Collection
    {
        $document = new DOMDocument;
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        $xpath = new DOMXPath($document);
        $cards = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' cardItem ')]");
        $collectedAt = CarbonImmutable::now();

        return collect($cards === false ? [] : iterator_to_array($cards))
            ->map(fn (DOMNode $card): ?CollectedOddsEvent => $this->toCollectedEvent($xpath, $card, $collectedAt))
            ->filter()
            ->values();
    }

    abstract public function source(): string;

    abstract protected function gamesUrlConfigKey(): string;

    private function toCollectedEvent(DOMXPath $xpath, DOMNode $card, CarbonImmutable $collectedAt): ?CollectedOddsEvent
    {
        $teams = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' nameTeam ')]//span",
            $card,
        );
        $odds = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' outcomesMain ')]//*[contains(concat(' ', normalize-space(@class), ' '), ' odd ')]",
            $card,
        );

        if ($teams === false || $odds === false || $teams->length !== 2 || $odds->length !== 3) {
            return null;
        }

        $homeTeam = $this->textContent($teams->item(0));
        $awayTeam = $this->textContent($teams->item(1));
        $homeOdd = $this->oddValue($this->textContent($odds->item(0)));
        $drawOdd = $this->oddValue($this->textContent($odds->item(1)));
        $awayOdd = $this->oddValue($this->textContent($odds->item(2)));

        if ($homeTeam === null || $awayTeam === null || $homeOdd === null || $drawOdd === null || $awayOdd === null) {
            return null;
        }

        return new CollectedOddsEvent(
            source: $this->source(),
            market: self::MARKET,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            eventDate: $this->valueForClass($xpath, $card, 'date'),
            eventTime: $this->valueForClass($xpath, $card, 'hour'),
            homeOdd: $homeOdd,
            drawOdd: $drawOdd,
            awayOdd: $awayOdd,
            collectedAt: $collectedAt,
        );
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
}
