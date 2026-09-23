<?php

namespace Tests\Feature\Services\Collection;

use App\Collectors\CollectedMarket;
use App\Collectors\CollectedOddsEvent;
use App\Collectors\OddsCollector;
use App\Collectors\OddsCollectorRegistry;
use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Services\Collection\CollectOddsAction;
use App\Services\Comparison\OddsComparisonService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CollectOddsActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_persists_available_sources_and_keeps_partial_collection_when_one_source_fails(): void
    {
        $this->freezeTime();
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([
                $this->collector('firebets', [$this->event('firebets', 'Flamengo', 'Palmeiras', 1.85, 3.40, 4.20)]),
                $this->collector('chute13', [$this->event('chute13', 'Flamengo RJ', 'Palmeiras SP', 1.90, 3.30, 4.00)]),
                $this->collector('a2bets', [$this->event('a2bets', 'Santos FC', 'Grêmio RS', 2.20, 3.10, 3.50)]),
                $this->collector('gbgoldbet', [], new RuntimeException('Fonte indisponível.')),
            ]);
        });

        $collectionRun = app(CollectOddsAction::class)->execute();

        $this->assertSame('partial', $collectionRun->status);
        $this->assertDatabaseCount('bookmakers', 4);
        $this->assertDatabaseCount('collection_source_results', 4);
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('market_odds', 9);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $collectionRun->id,
            'status' => 'failed',
            'error_message' => 'Fonte indisponível.',
        ]);

        $comparisons = app(OddsComparisonService::class)->forRun($collectionRun);
        $matchedEvent = $comparisons->firstWhere('event.home_team', 'Flamengo');
        $homeSelection = $matchedEvent['markets']
            ->firstWhere('key', 'match_winner')['selections']
            ->firstWhere('key', 'casa');
        $unmatchedEvent = $comparisons->firstWhere('event.home_team', 'Santos FC');

        $this->assertSame('chute13', $homeSelection['best_reference']['source']);
        $this->assertSame('-2.63', number_format($homeSelection['difference_percent'], 2, '.', ''));
        $this->assertSame('below', $homeSelection['state']);
        $this->assertFalse($unmatchedEvent['is_compared']);
    }

    public function test_marks_a_source_without_events_as_empty(): void
    {
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([
                $this->collector('firebets', []),
            ]);
        });

        $collectionRun = app(CollectOddsAction::class)->execute();

        $this->assertSame('completed', $collectionRun->status);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $collectionRun->id,
            'status' => 'empty',
            'events_count' => 0,
        ]);
    }

    public function test_keeps_only_one_snapshot_when_a_source_returns_the_same_event_twice(): void
    {
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([
                $this->collector('chute13', [
                    $this->event('chute13', 'Flamengo RJ', 'Palmeiras SP', 1.90, 3.30, 4.00),
                    $this->event('chute13', 'Flamengo', 'Palmeiras', 1.90, 3.30, 4.00),
                ]),
            ]);
        });

        $collectionRun = app(CollectOddsAction::class)->execute();

        $this->assertSame('completed', $collectionRun->status);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $collectionRun->id,
            'status' => 'completed',
            'events_count' => 1,
        ]);
        $this->assertDatabaseCount('market_odds', 3);
    }

    public function test_defers_only_the_limited_source_until_retry_after_expires(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fake([
            'https://gbgoldbet.test/rate-limit' => Http::response('error code: 1015', 429, ['Retry-After' => '7200']),
        ]);
        $collector = $this->rateLimitedCollector('gbgoldbet');
        $this->mock(OddsCollectorRegistry::class, function ($mock) use ($collector): void {
            $mock->shouldReceive('all')->twice()->andReturn([$collector]);
        });

        $limitedRun = app(CollectOddsAction::class)->execute();
        $deferredRun = app(CollectOddsAction::class)->execute();

        $limitedResult = $limitedRun->sourceResults()->firstOrFail();
        $deferredResult = $deferredRun->sourceResults()->firstOrFail();
        $this->assertSame('failed', $limitedRun->status);
        $this->assertSame('rate_limited', $limitedResult->status);
        $this->assertSame(429, $limitedResult->http_status);
        $this->assertSame(now()->addHours(2)->toDateTimeString(), $limitedResult->retry_at->toDateTimeString());
        $this->assertSame('deferred', $deferredRun->status);
        $this->assertSame('deferred', $deferredResult->status);
        $this->assertSame($limitedResult->retry_at->toDateTimeString(), $deferredResult->retry_at->toDateTimeString());
        Http::assertSentCount(1);
    }

    public function test_increases_the_wait_after_consecutive_rate_limits(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fake([
            'https://a2bets.test/rate-limit' => Http::response('Too Many Requests', 429),
        ]);
        $collector = $this->rateLimitedCollector('a2bets');
        $this->mock(OddsCollectorRegistry::class, function ($mock) use ($collector): void {
            $mock->shouldReceive('all')->twice()->andReturn([$collector]);
        });

        $firstRun = app(CollectOddsAction::class)->execute();
        $firstRetryAt = $firstRun->sourceResults()->firstOrFail()->retry_at;
        $this->travel(61)->minutes();
        $secondRun = app(CollectOddsAction::class)->execute();
        $secondRetryAt = $secondRun->sourceResults()->firstOrFail()->retry_at;

        $this->assertSame(now()->subMinute()->toDateTimeString(), $firstRetryAt->toDateTimeString());
        $this->assertSame(now()->addHours(3)->toDateTimeString(), $secondRetryAt->toDateTimeString());
        Http::assertSentCount(2);
    }

    public function test_collects_other_sources_while_a_limited_source_is_deferred(): void
    {
        $this->freezeTime();
        $limitedBookmaker = Bookmaker::create([
            'slug' => 'gbgoldbet',
            'name' => 'GB Gold Bet',
            'website_url' => 'https://gbgoldbet.test',
            'is_primary' => false,
        ]);
        $previousRun = CollectionRun::create([
            'status' => 'failed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        CollectionSourceResult::create([
            'collection_run_id' => $previousRun->id,
            'bookmaker_id' => $limitedBookmaker->id,
            'status' => 'rate_limited',
            'http_status' => 429,
            'retry_at' => now()->addHours(2),
        ]);
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([
                $this->collector('firebets', [$this->event('firebets', 'Flamengo', 'Palmeiras', 1.85, 3.40, 4.20)]),
                $this->collector('gbgoldbet', [], new RuntimeException('This collector should be deferred.')),
            ]);
        });

        $collectionRun = app(CollectOddsAction::class)->execute();

        $this->assertSame('partial', $collectionRun->status);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $collectionRun->id,
            'bookmaker_id' => $limitedBookmaker->id,
            'status' => 'deferred',
        ]);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $collectionRun->id,
            'status' => 'completed',
            'events_count' => 1,
        ]);
        $this->assertDatabaseCount('market_odds', 3);
    }

    public function test_marks_the_run_failed_when_collector_registry_crashes_before_source_processing(): void
    {
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andThrow(new RuntimeException('Registry unavailable.'));
        });

        try {
            app(CollectOddsAction::class)->execute();
            $this->fail('Expected the registry exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Registry unavailable.', $exception->getMessage());
        }

        $this->assertDatabaseHas('collection_runs', [
            'status' => 'failed',
            'finished_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param  array<int, CollectedOddsEvent>  $events
     */
    private function collector(string $source, array $events, ?RuntimeException $exception = null): OddsCollector
    {
        return new class($source, $events, $exception) implements OddsCollector
        {
            /**
             * @param  array<int, CollectedOddsEvent>  $events
             */
            public function __construct(
                private readonly string $source,
                private readonly array $events,
                private readonly ?RuntimeException $exception,
            ) {}

            public function source(): string
            {
                return $this->source;
            }

            public function collect(): Collection
            {
                if ($this->exception !== null) {
                    throw $this->exception;
                }

                return collect($this->events);
            }
        };
    }

    private function event(
        string $source,
        string $homeTeam,
        string $awayTeam,
        float $homeOdd,
        float $drawOdd,
        float $awayOdd,
    ): CollectedOddsEvent {
        return new CollectedOddsEvent(
            source: $source,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            eventDate: '26/ago',
            eventTime: '21:30',
            startsAt: CarbonImmutable::now()->addHour(),
            region: 'america',
            country: 'Brasil',
            countryCode: 'BRA',
            competition: 'Brasileirão Série A',
            markets: [
                'match_winner' => new CollectedMarket('match_winner', 'Vencedor do Encontro', [
                    ['label' => 'Casa', 'odd' => $homeOdd],
                    ['label' => 'Empate', 'odd' => $drawOdd],
                    ['label' => 'Fora', 'odd' => $awayOdd],
                ]),
            ],
            collectedAt: CarbonImmutable::now(),
        );
    }

    private function rateLimitedCollector(string $source): OddsCollector
    {
        return new class($source) implements OddsCollector
        {
            public function __construct(private readonly string $source) {}

            public function source(): string
            {
                return $this->source;
            }

            public function collect(): Collection
            {
                Http::get("https://{$this->source}.test/rate-limit")->throw();

                return collect();
            }
        };
    }
}
