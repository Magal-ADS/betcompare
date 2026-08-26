<?php

namespace App\Collectors;

final class A2BetsCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'a2bets';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.a2bets.games_url';
    }
}
