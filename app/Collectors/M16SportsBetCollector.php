<?php

namespace App\Collectors;

final class M16SportsBetCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'm16sportsbet';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.m16sportsbet.games_url';
    }
}
