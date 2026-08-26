<?php

namespace App\Http\Controllers;

use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Services\Comparison\OddsComparisonService;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(OddsComparisonService $oddsComparisonService): View
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

        return view('dashboard', [
            'bookmakers' => $bookmakers,
            'collectionRun' => $collectionRun,
            'comparisons' => $collectionRun === null ? collect() : $oddsComparisonService->forRun($collectionRun),
            'sourceResults' => $sourceResults,
        ]);
    }
}
