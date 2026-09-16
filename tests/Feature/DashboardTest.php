<?php

namespace Tests\Feature;

use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Models\Event;
use App\Models\MarketOdd;
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
            'starts_at' => CarbonImmutable::parse('2026-08-26 21:30:00', 'UTC'),
            'region' => 'america',
            'country' => 'Brasil',
            'country_code' => 'BRA',
            'competition' => 'Brasileirão Série A',
        ]);

        $this->createOffer($collectionRun, $firebets, $event, 1.85, 3.40, 4.20);
        $this->createOffer($collectionRun, $chute13, $event, 1.90, 3.30, 4.00);
        $this->createMarketOffer($collectionRun, $firebets, $event, 'total_goals', 'Total de Gols no Jogo', 'mais de 1 5', 'Mais de 1,5', 1.70);
        $this->createMarketOffer($collectionRun, $chute13, $event, 'total_goals', 'Total de Gols no Jogo', 'mais de 1 5', 'Mais de 1,5', 1.75);
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
            'error_message' => "HTTP request returned status code 429:\nerror code: 1015",
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
            ->assertSee('Limite temporário da fonte atingido. Aguarde antes de atualizar novamente.')
            ->assertDontSee('error code: 1015')
            ->assertSee('A2Bets')
            ->assertSee('Nenhum evento disponível nesta coleta')
            ->assertSee('Sem eventos')
            ->assertSee('1. Região')
            ->assertSee('2. País')
            ->assertSee('Brasileirão Série A')
            ->assertSee('Total de Gols no Jogo')
            ->assertSee('Mais de 1,5')
            ->assertSee('Firebets ↓ -2,63%')
            ->assertSee('Última atualização: 26/08/2026 13:26');
    }

    public function test_shows_the_source_retry_time_and_last_valid_collection(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'UTC'));
        config()->set('app.display_timezone', 'America/Sao_Paulo');
        $bookmaker = Bookmaker::create([
            'slug' => 'a2bets',
            'name' => 'A2Bets',
            'website_url' => 'https://a2bets.test',
            'is_primary' => false,
        ]);
        $successfulRun = CollectionRun::create([
            'status' => 'completed',
            'started_at' => now()->subDay()->subMinute(),
            'finished_at' => now()->subDay(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $successfulRun->id,
            'bookmaker_id' => $bookmaker->id,
            'status' => 'completed',
            'events_count' => 10,
            'collected_at' => now()->subDay(),
        ]);
        $limitedRun = CollectionRun::create([
            'status' => 'failed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $limitedRun->id,
            'bookmaker_id' => $bookmaker->id,
            'status' => 'rate_limited',
            'http_status' => 429,
            'retry_at' => now()->addHours(2),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('A fonte aplicou um limite temporário')
            ->assertSee('Limitada')
            ->assertSee('Nova tentativa após 16/09/2026 11:00')
            ->assertSee('Último dado válido: 15/09/2026 09:00');
    }

    public function test_search_finds_a_game_by_normalized_name_and_only_returns_the_current_week(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-08-26 12:00:00', 'UTC'));
        $firebets = Bookmaker::create([
            'slug' => 'firebets',
            'name' => 'Firebets',
            'website_url' => 'https://firebets.test',
            'is_primary' => true,
        ]);
        $collectionRun = CollectionRun::create([
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $targetEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'São Paulo FC',
            'away_team' => 'Boca Juniors',
            'normalized_home_team' => 'sao paulo',
            'normalized_away_team' => 'boca juniors',
            'event_date' => '26 ago',
            'event_time' => '21 30',
            'starts_at' => CarbonImmutable::parse('2026-08-26 21:30:00', 'UTC'),
            'region' => 'america',
            'country' => 'Brasil',
            'country_code' => 'BRA',
            'competition' => 'Copa Libertadores',
        ]);
        $otherEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'Flamengo',
            'away_team' => 'Palmeiras',
            'normalized_home_team' => 'flamengo',
            'normalized_away_team' => 'palmeiras',
            'event_date' => '27 ago',
            'event_time' => '20 00',
            'starts_at' => CarbonImmutable::parse('2026-08-27 20:00:00', 'UTC'),
            'region' => 'america',
            'country' => 'Brasil',
            'country_code' => 'BRA',
            'competition' => 'Brasileirão Série A',
        ]);
        $outsideWeekEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'São Paulo Futuro',
            'away_team' => 'Boca Futuro',
            'normalized_home_team' => 'sao paulo futuro',
            'normalized_away_team' => 'boca futuro',
            'event_date' => '02 set',
            'event_time' => '21 30',
            'starts_at' => CarbonImmutable::parse('2026-09-02 21:30:00', 'UTC'),
            'region' => 'america',
            'country' => 'Brasil',
            'country_code' => 'BRA',
            'competition' => 'Copa Libertadores',
        ]);
        $this->createOffer($collectionRun, $firebets, $targetEvent, 1.80, 3.20, 4.10);
        $this->createOffer($collectionRun, $firebets, $otherEvent, 1.70, 3.30, 4.30);
        $this->createOffer($collectionRun, $firebets, $outsideWeekEvent, 1.60, 3.40, 4.50);

        $this->get('/?search=sao+paulo+x+boca')
            ->assertOk()
            ->assertSee('São Paulo FC')
            ->assertSee('Boca Juniors')
            ->assertViewHas('comparisons', function ($comparisons) use ($targetEvent): bool {
                return $comparisons->count() === 1
                    && $comparisons->first()['event']->is($targetEvent);
            })
            ->assertDontSee('name="date"', false);
    }

    public function test_filters_the_weekly_games_by_region_country_championship_and_game(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-08-26 12:00:00', 'UTC'));
        $firebets = Bookmaker::create([
            'slug' => 'firebets',
            'name' => 'Firebets',
            'website_url' => 'https://firebets.test',
            'is_primary' => true,
        ]);
        $collectionRun = CollectionRun::create([
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $brazilEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'Santos',
            'away_team' => 'Grêmio',
            'normalized_home_team' => 'santos',
            'normalized_away_team' => 'gremio',
            'starts_at' => CarbonImmutable::parse('2026-08-27 20:00:00', 'UTC'),
            'region' => 'america',
            'country' => 'Brasil',
            'country_code' => 'BRA',
            'competition' => 'Brasileirão Série A',
        ]);
        $europeEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'Arsenal',
            'away_team' => 'Chelsea',
            'normalized_home_team' => 'arsenal',
            'normalized_away_team' => 'chelsea',
            'starts_at' => CarbonImmutable::parse('2026-08-28 20:00:00', 'UTC'),
            'region' => 'europe',
            'country' => 'Inglaterra',
            'country_code' => 'ENG',
            'competition' => 'Premier League',
        ]);
        $this->createOffer($collectionRun, $firebets, $brazilEvent, 1.90, 3.20, 4.00);
        $this->createOffer($collectionRun, $firebets, $europeEvent, 1.80, 3.30, 4.20);

        $this->get('/?region=america&country=Brasil&competition=Brasileir%C3%A3o+S%C3%A9rie+A&game='.$brazilEvent->id)
            ->assertOk()
            ->assertSee('Santos')
            ->assertSee('Grêmio')
            ->assertViewHas('comparisons', function ($comparisons) use ($brazilEvent): bool {
                return $comparisons->count() === 1
                    && $comparisons->first()['event']->is($brazilEvent);
            })
            ->assertSee('Todos os jogos');
    }

    public function test_orders_the_paginated_dashboard_by_largest_difference(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-08-26 12:00:00', 'UTC'));
        $firebets = Bookmaker::create([
            'slug' => 'firebets',
            'name' => 'Firebets',
            'website_url' => 'https://firebets.test',
            'is_primary' => true,
        ]);
        $competitor = Bookmaker::create([
            'slug' => 'chute13',
            'name' => 'Chute13',
            'website_url' => 'https://chute13.test',
            'is_primary' => false,
        ]);
        $collectionRun = CollectionRun::create([
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $largestDifferenceEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'Maior Diferença',
            'away_team' => 'Visitante A',
            'normalized_home_team' => 'maior diferenca',
            'normalized_away_team' => 'visitante a',
            'starts_at' => CarbonImmutable::parse('2026-08-27 20:00:00', 'UTC'),
        ]);
        $smallerDifferenceEvent = Event::create([
            'sport' => 'football',
            'market' => 'pregame',
            'home_team' => 'Menor Diferença',
            'away_team' => 'Visitante B',
            'normalized_home_team' => 'menor diferenca',
            'normalized_away_team' => 'visitante b',
            'starts_at' => CarbonImmutable::parse('2026-08-27 21:00:00', 'UTC'),
        ]);
        $this->createOffer($collectionRun, $firebets, $largestDifferenceEvent, 2.00, 3.00, 4.00);
        $this->createOffer($collectionRun, $competitor, $largestDifferenceEvent, 1.00, 3.00, 4.00);
        $this->createOffer($collectionRun, $firebets, $smallerDifferenceEvent, 1.50, 3.00, 4.00);
        $this->createOffer($collectionRun, $competitor, $smallerDifferenceEvent, 2.00, 3.00, 4.00);

        $this->get('/?sort=difference_desc')
            ->assertOk()
            ->assertViewHas('comparisons', function ($comparisons) use ($largestDifferenceEvent): bool {
                return $comparisons->first()['event']->is($largestDifferenceEvent);
            });
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
            'starts_at' => $event->starts_at,
            'region' => $event->region,
            'country' => $event->country,
            'country_code' => $event->country_code,
            'competition' => $event->competition,
        ]);

        foreach ([
            ['key' => 'casa', 'name' => 'Casa', 'odd' => $homeOdd],
            ['key' => 'empate', 'name' => 'Empate', 'odd' => $drawOdd],
            ['key' => 'fora', 'name' => 'Fora', 'odd' => $awayOdd],
        ] as $selection) {
            MarketOdd::create([
                'source_event_id' => $sourceEvent->id,
                'collection_run_id' => $collectionRun->id,
                'market_key' => 'match_winner',
                'market_name' => 'Vencedor do Encontro',
                'selection_key' => $selection['key'],
                'selection_name' => $selection['name'],
                'odd' => $selection['odd'],
                'collected_at' => now(),
            ]);
        }
    }

    private function createMarketOffer(
        CollectionRun $collectionRun,
        Bookmaker $bookmaker,
        Event $event,
        string $marketKey,
        string $marketName,
        string $selectionKey,
        string $selectionName,
        float $odd,
    ): void {
        $sourceEvent = SourceEvent::query()
            ->whereBelongsTo($bookmaker)
            ->whereBelongsTo($event)
            ->sole();

        MarketOdd::create([
            'source_event_id' => $sourceEvent->id,
            'collection_run_id' => $collectionRun->id,
            'market_key' => $marketKey,
            'market_name' => $marketName,
            'selection_key' => $selectionKey,
            'selection_name' => $selectionName,
            'odd' => $odd,
            'collected_at' => now(),
        ]);
    }
}
