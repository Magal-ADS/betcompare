<?php

namespace App\Http\Controllers;

use App\Models\CollectionRun;
use App\Services\Collection\CollectOddsAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

final class RefreshOddsController extends Controller
{
    public function __invoke(CollectOddsAction $collectOdds): RedirectResponse
    {
        $collectionRun = Cache::lock('oddradar:odds-collection', 180)->get(
            fn (): CollectionRun => $collectOdds->execute(),
        );

        if (! $collectionRun instanceof CollectionRun) {
            return to_route('dashboard')->with('status', 'Já existe uma atualização de odds em andamento.');
        }

        return to_route('dashboard')->with(
            'status',
            $collectionRun->status === 'failed'
                ? 'Não foi possível coletar dados das fontes neste momento.'
                : 'Coleta concluída. O painel foi atualizado com as fontes disponíveis.',
        );
    }
}
