<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Services\Comparison\OddsComparisonService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

final class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, OddsComparisonService $oddsComparisonService): View
    {
        $collectionRun = CollectionRun::query()
            ->whereIn('status', ['completed', 'partial', 'failed', 'deferred'])
            ->with('sourceResults')
            ->latest('finished_at')
            ->first();
        $bookmakers = Bookmaker::query()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
        $sourceResults = $collectionRun?->sourceResults->keyBy('bookmaker_id') ?? collect();
        $lastSuccessfulSourceResults = CollectionSourceResult::query()
            ->whereIn('id', CollectionSourceResult::query()
                ->selectRaw('MAX(id)')
                ->where('status', 'completed')
                ->groupBy('bookmaker_id'))
            ->get()
            ->keyBy('bookmaker_id');

        $filters = $request->validated();
        $filterOptions = $collectionRun === null
            ? ['regions' => collect(), 'countries' => collect(), 'competitions' => collect(), 'games' => collect()]
            : $oddsComparisonService->availableFilters($collectionRun);
        $comparisons = $collectionRun === null
            ? new LengthAwarePaginator([], 0, 15)
            : $oddsComparisonService->paginateForRun($collectionRun, $filters);
        $comparisons->withQueryString();

        return view('dashboard', [
            'bookmakers' => $bookmakers,
            'collectionRun' => $collectionRun,
            'comparisons' => $comparisons,
            'sourceResults' => $sourceResults,
            'lastSuccessfulSourceResults' => $lastSuccessfulSourceResults,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'week' => $oddsComparisonService->currentWeek(),
        ]);
    }
}
