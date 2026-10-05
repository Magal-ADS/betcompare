<?php

namespace App\Collectors;

final class TeamSportCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'teamsport';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.teamsport.games_url';
    }
}
