<?php

namespace App\Collectors;

use Illuminate\Support\Collection;

interface OddsCollector
{
    public function source(): string;

    /**
     * @return Collection<int, CollectedOddsEvent>
     */
    public function collect(): Collection;
}
