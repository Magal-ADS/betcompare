<?php

namespace App\Jobs;

use App\Models\CollectionRun;
use App\Services\Collection\CollectOddsAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CollectOdds implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string PENDING_CACHE_KEY = 'oddradar:odds-collection-pending';

    public const int PENDING_MINUTES = 35;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 2100;

    public function handle(CollectOddsAction $collectOdds): void
    {
        try {
            $collectOdds->execute();
        } finally {
            Cache::forget(self::PENDING_CACHE_KEY);
        }
    }

    public function uniqueId(): string
    {
        return 'oddradar-odds-collection';
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::PENDING_CACHE_KEY);

        CollectionRun::query()
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

        Log::error('OddRadar queued collection failed.', [
            'exception' => $exception?->getMessage(),
        ]);
    }
}
