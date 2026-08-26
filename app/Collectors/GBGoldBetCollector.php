<?php

namespace App\Collectors;

final class GBGoldBetCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'gbgoldbet';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.gbgoldbet.games_url';
    }
}
