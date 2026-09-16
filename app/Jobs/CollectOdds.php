<?php

namespace App\Jobs;

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

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1200;

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

        Log::error('OddRadar queued collection failed.', [
            'exception' => $exception?->getMessage(),
        ]);
    }
}
