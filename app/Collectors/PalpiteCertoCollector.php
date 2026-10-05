<?php

namespace App\Collectors;

final class PalpiteCertoCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'palpitecerto';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.palpitecerto.games_url';
    }
}
