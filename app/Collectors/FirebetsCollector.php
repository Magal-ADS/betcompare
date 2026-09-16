<?php

namespace App\Collectors;

final class FirebetsCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'firebets';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.firebets.games_url';
    }
}
