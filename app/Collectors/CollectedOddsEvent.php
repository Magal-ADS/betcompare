<?php

namespace App\Collectors;

use Carbon\CarbonImmutable;

final readonly class CollectedOddsEvent
{
    public function __construct(
        public string $source,
        public string $market,
        public string $homeTeam,
        public string $awayTeam,
        public ?string $eventDate,
        public ?string $eventTime,
        public float $homeOdd,
        public float $drawOdd,
        public float $awayOdd,
        public CarbonImmutable $collectedAt,
    ) {}
}
