<?php

namespace App\Collectors;

class OddsCollectorRegistry
{
    public function __construct(
        private readonly FirebetsCollector $firebetsCollector,
        private readonly Chute13Collector $chute13Collector,
        private readonly A2BetsCollector $a2BetsCollector,
        private readonly GBGoldBetCollector $gbGoldBetCollector,
    ) {}

    /**
     * @return array<int, OddsCollector>
     */
    public function all(): array
    {
        return [
            $this->firebetsCollector,
            $this->chute13Collector,
            $this->a2BetsCollector,
            $this->gbGoldBetCollector,
        ];
    }
}
