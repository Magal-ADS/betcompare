<?php

namespace App\Collectors;

final class EsportesJLCollector extends PublicHtmlOddsCollector
{
    public function source(): string
    {
        return 'esportesjl';
    }

    protected function gamesUrlConfigKey(): string
    {
        return 'services.bookmakers.esportesjl.games_url';
    }
}
