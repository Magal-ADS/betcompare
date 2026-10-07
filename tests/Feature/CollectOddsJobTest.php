<?php

namespace Tests\Feature;

use App\Collectors\OddsCollectorRegistry;
use App\Jobs\CollectOdds;
use App\Models\CollectionRun;
use App\Services\Collection\CollectOddsAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CollectOddsJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_collection_runs_in_the_queue_and_releases_the_pending_marker(): void
    {
        Cache::put(CollectOdds::PENDING_CACHE_KEY, true, now()->addMinutes(20));
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([]);
        });

        (new CollectOdds)->handle(app(CollectOddsAction::class));

        $this->assertFalse(Cache::has(CollectOdds::PENDING_CACHE_KEY));
        $this->assertDatabaseHas('collection_runs', ['status' => 'failed']);
    }

    public function test_failed_job_marks_unfinished_collection_as_failed(): void
    {
        $collectionRun = CollectionRun::create([
            'status' => 'running',
            'started_at' => now()->subMinutes(30),
        ]);
        Cache::put(CollectOdds::PENDING_CACHE_KEY, true, now()->addMinutes(CollectOdds::PENDING_MINUTES));

        (new CollectOdds)->failed(new \RuntimeException('Collection timed out.'));

        $this->assertFalse(Cache::has(CollectOdds::PENDING_CACHE_KEY));
        $this->assertEquals('failed', $collectionRun->refresh()->status);
        $this->assertNotNull($collectionRun->finished_at);
    }
}
