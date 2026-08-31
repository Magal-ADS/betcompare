<?php

namespace Tests\Feature;

use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollectionHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_renders_the_collection_history_page(): void
    {
        $bookmaker = Bookmaker::create([
            'slug' => 'a2bets',
            'name' => 'A2Bets',
            'website_url' => 'https://a2bets.test',
            'is_primary' => false,
        ]);
        $collectionRun = CollectionRun::create([
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $bookmaker->id,
            'status' => 'empty',
            'events_count' => 0,
            'collected_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/historico-coletas')
            ->assertOk()
            ->assertSee('Histórico de coletas')
            ->assertSee('A2Bets: sem eventos');
    }
}
