<?php

namespace App\Collectors;

class OddsCollectorRegistry
{
    public function __construct(
        private readonly FirebetsCollector $firebetsCollector,
        private readonly Chute13Collector $chute13Collector,
        private readonly A2BetsCollector $a2BetsCollector,
        private readonly GBGoldBetCollector $gbGoldBetCollector,
        private readonly M16SportsBetCollector $m16SportsBetCollector,
        private readonly Chute13ClubCollector $chute13ClubCollector,
        private readonly TropaPBCollector $tropaPBCollector,
        private readonly EsportesJLCollector $esportesJLCollector,
        private readonly PalpiteCertoCollector $palpiteCertoCollector,
        private readonly TeamSportCollector $teamSportCollector,
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
            $this->m16SportsBetCollector,
            $this->chute13ClubCollector,
            $this->tropaPBCollector,
            $this->esportesJLCollector,
            $this->palpiteCertoCollector,
            $this->teamSportCollector,
        ];
    }
}
