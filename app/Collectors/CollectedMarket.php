<?php

namespace App\Collectors;

final readonly class CollectedMarket
{
    /** @param array<int, array{label: string, odd: float}> $selections */
    public function __construct(
        public string $key,
        public string $name,
        public array $selections,
    ) {}
}
