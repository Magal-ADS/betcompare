<?php

namespace Tests\Feature;

use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'UTC'));
        config()->set('app.display_timezone', 'America/Sao_Paulo');
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
        $limitedBookmaker = Bookmaker::create([
            'slug' => 'gbgoldbet',
            'name' => 'GB Gold Bet',
            'website_url' => 'https://gbgoldbet.test',
            'is_primary' => false,
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $limitedBookmaker->id,
            'status' => 'rate_limited',
            'http_status' => 429,
            'retry_at' => now()->addHours(2),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/historico-coletas')
            ->assertOk()
            ->assertSee('Histórico de coletas')
            ->assertSee('A2Bets: sem eventos')
            ->assertSee('GB Gold Bet: limitada até 16/09/2026 11:00');
    }
}
