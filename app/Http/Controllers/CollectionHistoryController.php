<?php

namespace App\Http\Controllers;

use App\Models\CollectionRun;
use Illuminate\Contracts\View\View;

final class CollectionHistoryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): View
    {
        return view('collection-history', [
            'collectionRuns' => CollectionRun::query()
                ->with('sourceResults.bookmaker')
                ->latest('finished_at')
                ->paginate(20),
        ]);
    }
}
