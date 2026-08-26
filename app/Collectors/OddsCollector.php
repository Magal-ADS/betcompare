<?php

namespace App\Collectors;

use Illuminate\Support\Collection;

interface OddsCollector
{
    /**
     * @return Collection<int, CollectedOddsEvent>
     */
    public function collect(): Collection;
}
