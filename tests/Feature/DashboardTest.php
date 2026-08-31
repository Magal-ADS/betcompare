<?php

namespace Tests\Feature;

use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Models\Event;
use App\Models\Odd;
use App\Models\SourceEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_renders_available_odds_and_a_failed_source_without_hiding_the_comparison(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-08-26 16:26:00', 'UTC'));
        config()->set('app.display_timezone', 'America/Sao_Paulo');

        $firebets = Bookmaker::create([
            'slug' => 'firebets',
            'name' => 'Firebets',
            'website_url' => 'https://firebets.test',
            'is_primary' => true,
        ]);
        $chute13 = Bookmaker::create([
            'slug' => 'chute13',
            'name' => 'Chute13',
            'website_url' => 'https://chute13.test',
            'is_primary' => false,
        ]);
        $a2Bets = Bookmaker::create([
            'slug' => 'a2bets',
            'name' => 'A2Bets',
            'website_url' => 'https://a2bets.test',
            'is_primary' => false,
        ]);
        $gbGoldBet = Bookmaker::create([
            'slug' => 'gbgoldbet',
            'name' => 'GB Gold Bet',
            'website_url' => 'https://gbgoldbet.test',
            'is_primary' => false,
        ]);
        $collectionRun = CollectionRun::create([
            'status' => 'partial',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $event = Event::create([
            'sport' => 'football',
            'market' => '1x2',
            'home_team' => 'Flamengo',
            'away_team' => 'Palmeiras',
            'normalized_home_team' => 'flamengo',
            'normalized_away_team' => 'palmeiras',
            'event_date' => '26 ago',
            'event_time' => '21 30',
        ]);

        $this->createOffer($collectionRun, $firebets, $event, 1.85, 3.40, 4.20);
        $this->createOffer($collectionRun, $chute13, $event, 1.90, 3.30, 4.00);
        CollectionSourceResult::create([
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $a2Bets->id,
            'status' => 'empty',
            'events_count' => 0,
            'collected_at' => now(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $firebets->id,
            'status' => 'completed',
            'events_count' => 1,
            'collected_at' => now(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $chute13->id,
            'status' => 'completed',
            'events_count' => 1,
            'collected_at' => now(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $gbGoldBet->id,
            'status' => 'failed',
            'error_message' => 'Fonte indisponível.',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Flamengo')
            ->assertSee('Palmeiras')
            ->assertSee('1,90')
            ->assertSee('Chute13')
            ->assertSee('↓ Abaixo')
            ->assertSee('GB Gold Bet')
            ->assertSee('Fonte indisponível nesta coleta')
            ->assertSee('A2Bets')
            ->assertSee('Nenhum evento disponível nesta coleta')
            ->assertSee('Sem eventos')
            ->assertSee('Última atualização: 26/08/2026 13:26');
    }

    private function createOffer(
        CollectionRun $collectionRun,
        Bookmaker $bookmaker,
        Event $event,
        float $homeOdd,
        float $drawOdd,
        float $awayOdd,
    ): void {
        $sourceEvent = SourceEvent::create([
            'bookmaker_id' => $bookmaker->id,
            'event_id' => $event->id,
            'sport' => 'football',
            'market' => '1x2',
            'home_team' => $event->home_team,
            'away_team' => $event->away_team,
            'normalized_home_team' => $event->normalized_home_team,
            'normalized_away_team' => $event->normalized_away_team,
            'event_date' => $event->event_date,
            'event_time' => $event->event_time,
        ]);

        Odd::create([
            'source_event_id' => $sourceEvent->id,
            'collection_run_id' => $collectionRun->id,
            'home_odd' => $homeOdd,
            'draw_odd' => $drawOdd,
            'away_odd' => $awayOdd,
            'collected_at' => now(),
        ]);
    }
}
