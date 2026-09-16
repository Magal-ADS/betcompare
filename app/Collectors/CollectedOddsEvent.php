<?php

namespace App\Collectors;

use Carbon\CarbonImmutable;

final readonly class CollectedOddsEvent
{
    /**
     * @param  array<string, CollectedMarket>  $markets
     */
    public function __construct(
        public string $source,
        public string $homeTeam,
        public string $awayTeam,
        public ?string $eventDate,
        public ?string $eventTime,
        public ?CarbonImmutable $startsAt,
        public ?string $region,
        public ?string $country,
        public ?string $countryCode,
        public ?string $competition,
        public array $markets,
        public CarbonImmutable $collectedAt,
    ) {}
}
