<?php

namespace App\Collectors;

final class Chute13Collector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'chute13';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.chute13.games_url';
    }
}
