<?php

namespace App\Http\Controllers;

use App\Jobs\CollectOdds;
use App\Models\CollectionRun;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

final class RefreshOddsController extends Controller
{
    public function __invoke(Repository $config): RedirectResponse
    {
        $cooldownMinutes = $config->integer('oddradar.collection_cooldown_minutes', 30);
        $recentCollectionExists = CollectionRun::query()
            ->whereNotNull('finished_at')
            ->where('started_at', '>', now()->subMinutes($cooldownMinutes))
            ->exists();

        if ($recentCollectionExists) {
            return to_route('dashboard')->with(
                'status',
                "Aguarde pelo menos {$cooldownMinutes} minutos entre atualizações para respeitar o limite das fontes.",
            );
        }

        if (! Cache::add(CollectOdds::PENDING_CACHE_KEY, true, now()->addMinutes(20))) {
            return to_route('dashboard')->with('status', 'Já existe uma atualização de odds em andamento.');
        }

        CollectOdds::dispatch();

        return to_route('dashboard')->with('status', 'Atualização iniciada em segundo plano. Você pode continuar usando o painel enquanto as odds são coletadas.');
    }
}
