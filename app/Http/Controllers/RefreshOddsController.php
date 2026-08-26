<?php

namespace App\Http\Controllers;

use App\Services\Collection\CollectOddsAction;
use Illuminate\Http\RedirectResponse;

final class RefreshOddsController extends Controller
{
    public function __invoke(CollectOddsAction $collectOdds): RedirectResponse
    {
        $collectionRun = $collectOdds->execute();

        return to_route('dashboard')->with(
            'status',
            $collectionRun->status === 'failed'
                ? 'Não foi possível coletar dados das fontes neste momento.'
                : 'Coleta concluída. O painel foi atualizado com as fontes disponíveis.',
        );
    }
}
