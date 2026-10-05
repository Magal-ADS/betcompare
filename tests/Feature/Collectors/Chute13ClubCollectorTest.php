<?php

namespace Tests\Feature\Collectors;

use App\Collectors\Chute13ClubCollector;
use App\Collectors\OddsCollectorRegistry;
use App\Services\Collection\CollectOddsAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Chute13ClubCollectorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_collects_public_football_1x2_and_converts_match_time_to_utc(): void
    {
        config()->set('services.bookmakers.chute13club.games_url', 'https://chute13club.test/web');
        config()->set('services.bookmakers.chute13club.website_url', 'https://chute13club.test');
        config()->set('app.display_timezone', 'America/Sao_Paulo');
        Http::preventStrayRequests();
        Http::fake([
            'https://chute13club.test/web' => Http::response('<meta name="csrf-token" content="public-page-token">'),
            'https://chute13club.test/web/leagues' => Http::sequence()
                ->push($this->leaguesResponse())
                ->push(['leagues' => []])
                ->push(['leagues' => []]),
        ]);

        $event = app(Chute13ClubCollector::class)->collect()->sole();

        $this->assertSame('chute13club', $event->source);
        $this->assertSame('Flamengo', $event->homeTeam);
        $this->assertSame('Palmeiras', $event->awayTeam);
        $this->assertSame('2026-10-06T00:30:00+00:00', $event->startsAt->toIso8601String());
        $this->assertSame('05/10', $event->eventDate);
        $this->assertSame('21:30', $event->eventTime);
        $this->assertSame('Brasil', $event->country);
        $this->assertSame('Série A', $event->competition);
        $this->assertSame([1.85, 3.4, 4.2], array_column($event->markets['match_winner']->selections, 'odd'));
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://chute13club.test/web/leagues'
            && $request->hasHeader('X-CSRF-TOKEN', 'public-page-token'));
    }

    public function test_rejects_a_page_without_a_public_session_token(): void
    {
        config()->set('services.bookmakers.chute13club.games_url', 'https://chute13club.test/web');
        Http::preventStrayRequests();
        Http::fake(['https://chute13club.test/web' => Http::response('<html></html>')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('token da página pública não encontrado');

        app(Chute13ClubCollector::class)->collect();
    }

    public function test_stores_chute13_club_odds_in_a_collection_run(): void
    {
        config()->set('services.bookmakers.chute13club.games_url', 'https://chute13club.test/web');
        config()->set('services.bookmakers.chute13club.website_url', 'https://chute13club.test');
        Http::preventStrayRequests();
        Http::fake([
            'https://chute13club.test/web' => Http::response('<meta name="csrf-token" content="public-page-token">'),
            'https://chute13club.test/web/leagues' => Http::sequence()
                ->push($this->leaguesResponse())
                ->push(['leagues' => []])
                ->push(['leagues' => []]),
        ]);
        $collector = app(Chute13ClubCollector::class);
        $this->mock(OddsCollectorRegistry::class, function ($mock) use ($collector): void {
            $mock->shouldReceive('all')->once()->andReturn([$collector]);
        });

        $run = app(CollectOddsAction::class)->execute();

        $this->assertSame('completed', $run->status);
        $this->assertDatabaseHas('bookmakers', ['slug' => 'chute13club', 'name' => 'Chute13 Club']);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $run->id,
            'status' => 'completed',
            'events_count' => 1,
        ]);
        $this->assertDatabaseCount('market_odds', 3);
    }

    /** @return array<string, mixed> */
    private function leaguesResponse(): array
    {
        return [
            'leagues' => [[
                'name' => 'Série A',
                'country' => ['name' => 'Brasil', 'code' => 'br'],
                'matches' => [[
                    'sport_slug' => 'soccer',
                    'active' => true,
                    'home_team' => 'Flamengo',
                    'away_team' => 'Palmeiras',
                    'match_date' => '2026-10-06T00:30:00.000000Z',
                    'quotations' => [
                        ['bet_slug' => '3', 'choice_slug' => '1', 'value' => 1.85],
                        ['bet_slug' => '3', 'choice_slug' => 'X', 'value' => 3.4],
                        ['bet_slug' => '3', 'choice_slug' => '2', 'value' => 4.2],
                        ['bet_slug' => '520', 'choice_slug' => '1', 'value' => 1.5],
                    ],
                ]],
            ]],
        ];
    }
}
