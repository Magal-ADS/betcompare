<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Services\Comparison\OddsComparisonService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

final class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, OddsComparisonService $oddsComparisonService): View
    {
        $collectionRun = CollectionRun::query()
            ->whereIn('status', ['completed', 'partial', 'failed'])
            ->with('sourceResults')
            ->latest('finished_at')
            ->first();
        $bookmakers = Bookmaker::query()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
        $sourceResults = $collectionRun?->sourceResults->keyBy('bookmaker_id') ?? collect();

        $allComparisons = $collectionRun === null ? collect() : $oddsComparisonService->forRun(
            $collectionRun,
            $request->validated('team'),
            $request->validated('date'),
            $request->validated('sort', 'time'),
        );
        $page = LengthAwarePaginator::resolveCurrentPage();
        $comparisons = new LengthAwarePaginator(
            $allComparisons->forPage($page, 15)->values(),
            $allComparisons->count(),
            15,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('dashboard', [
            'bookmakers' => $bookmakers,
            'collectionRun' => $collectionRun,
            'comparisons' => $comparisons,
            'sourceResults' => $sourceResults,
            'filters' => $request->validated(),
        ]);
    }
}
