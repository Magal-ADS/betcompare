<?php

namespace App\Collectors;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnexpectedValueException;

final class FirebetsCollector extends PublicHtmlOddsCollector
{
    /** @var array<int, string> */
    private const array WEEKDAY_LABELS = [
        CarbonImmutable::SUNDAY => 'Domingo',
        CarbonImmutable::MONDAY => 'Segunda-Feira',
        CarbonImmutable::TUESDAY => 'Terça-Feira',
        CarbonImmutable::WEDNESDAY => 'Quarta-Feira',
        CarbonImmutable::THURSDAY => 'Quinta-Feira',
        CarbonImmutable::FRIDAY => 'Sexta-Feira',
        CarbonImmutable::SATURDAY => 'Sábado',
    ];

    public function collect(): Collection
    {
        $landingUrl = $this->gamesUrl();
        $landingHtml = $this->fetchHtml($landingUrl);
        $todayUrl = $this->todayGamesUrl($landingHtml, $landingUrl);

        return $this->collectHtml($this->fetchHtml($todayUrl));
    }

    public function source(): string
    {
        return 'firebets';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.firebets.games_url';
    }

    private function todayGamesUrl(string $html, string $landingUrl): string
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
        $links = $xpath->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' submenuItem ')]".
            "[.//span[contains(concat(' ', normalize-space(@class), ' '), ' name ') and normalize-space() = 'Jogos do Dia']]".
            "//a[contains(@href, 'jogos.aspx')]",
        );
        $todayLabel = self::WEEKDAY_LABELS[CarbonImmutable::now('America/Sao_Paulo')->dayOfWeek];

        foreach ($links === false ? [] : iterator_to_array($links) as $link) {
            if (Str::squish($link->textContent) !== $todayLabel) {
                continue;
            }

            $href = $link->attributes?->getNamedItem('href')?->nodeValue;

            if ($href === null) {
                continue;
            }

            return $this->absoluteUrl($landingUrl, html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
        }

        throw new UnexpectedValueException("Firebets did not expose the '{$todayLabel}' daily games link.");
    }

    private function absoluteUrl(string $landingUrl, string $href): string
    {
        if (Str::startsWith($href, ['https://', 'http://'])) {
            return $href;
        }

        return Str::beforeLast($landingUrl, '/').'/'.ltrim($href, '/');
    }
}
