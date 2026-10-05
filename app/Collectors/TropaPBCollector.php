<?php

namespace App\Collectors;

final class TropaPBCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'tropapb';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.tropapb.games_url';
    }
}
