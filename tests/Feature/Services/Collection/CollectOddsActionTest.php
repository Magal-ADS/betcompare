<?php

namespace Tests\Feature\Services\Collection;

use App\Collectors\CollectedOddsEvent;
use App\Collectors\OddsCollector;
use App\Collectors\OddsCollectorRegistry;
use App\Services\Collection\CollectOddsAction;
use App\Services\Comparison\OddsComparisonService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
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
        $this->assertDatabaseCount('odds', 3);
        $this->assertDatabaseHas('collection_source_results', [
            'collection_run_id' => $collectionRun->id,
            'status' => 'failed',
            'error_message' => 'Fonte indisponível.',
        ]);

        $comparisons = app(OddsComparisonService::class)->forRun($collectionRun);
        $matchedEvent = $comparisons->firstWhere('event.home_team', 'Flamengo');
        $homeSelection = $matchedEvent['selections']->firstWhere('key', 'home');
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
            market: '1x2',
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            eventDate: '26/ago',
            eventTime: '21:30',
            homeOdd: $homeOdd,
            drawOdd: $drawOdd,
            awayOdd: $awayOdd,
            collectedAt: CarbonImmutable::now(),
        );
    }
}
