<?php

namespace App\Services\Collection;

use App\Collectors\CollectedOddsEvent;
use App\Collectors\OddsCollectorRegistry;
use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Models\Odd;
use App\Models\SourceEvent;
use App\Services\Normalization\EventMatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class CollectOddsAction
{
    private const string STATUS_RUNNING = 'running';

    private const string STATUS_COMPLETED = 'completed';

    private const string STATUS_EMPTY = 'empty';

    private const string STATUS_PARTIAL = 'partial';

    private const string STATUS_FAILED = 'failed';

    public function __construct(
        private readonly EventMatcher $eventMatcher,
        private readonly Repository $config,
        private readonly OddsCollectorRegistry $collectorRegistry,
    ) {}

    public function execute(): CollectionRun
    {
        $collectionRun = CollectionRun::create([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
        $successfulSources = 0;
        $collectors = $this->collectorRegistry->all();

        foreach ($collectors as $collector) {
            $bookmaker = $this->bookmakerFor($collector->source());

            try {
                $events = $this->uniqueEvents($collector->collect());
                $this->storeEvents($collectionRun, $bookmaker, $events);

                CollectionSourceResult::create([
                    'collection_run_id' => $collectionRun->id,
                    'bookmaker_id' => $bookmaker->id,
                    'status' => $events->isEmpty() ? self::STATUS_EMPTY : self::STATUS_COMPLETED,
                    'events_count' => $events->count(),
                    'collected_at' => now(),
                ]);
                $successfulSources++;
            } catch (Throwable $exception) {
                Log::warning('OddRadar collection source failed.', [
                    'source' => $collector->source(),
                    'collection_run_id' => $collectionRun->id,
                    'exception' => $exception::class,
                ]);

                CollectionSourceResult::create([
                    'collection_run_id' => $collectionRun->id,
                    'bookmaker_id' => $bookmaker->id,
                    'status' => self::STATUS_FAILED,
                    'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                ]);
            }
        }

        $collectionRun->update([
            'status' => match ($successfulSources) {
                0 => self::STATUS_FAILED,
                count($collectors) => self::STATUS_COMPLETED,
                default => self::STATUS_PARTIAL,
            },
            'finished_at' => now(),
        ]);

        return $collectionRun->refresh();
    }

    /**
     * @param  Collection<int, CollectedOddsEvent>  $events
     * @return Collection<int, CollectedOddsEvent>
     */
    private function uniqueEvents(Collection $events): Collection
    {
        return $events->unique(function (CollectedOddsEvent $collectedEvent): string {
            $identity = $this->eventMatcher->sourceIdentity($collectedEvent);

            return implode("\x1F", [
                $collectedEvent->market,
                $identity['normalized_home_team'],
                $identity['normalized_away_team'],
                $identity['event_date'] ?? '',
                $identity['event_time'] ?? '',
            ]);
        })->values();
    }

    /**
     * @param  Collection<int, CollectedOddsEvent>  $events
     */
    private function storeEvents(CollectionRun $collectionRun, Bookmaker $bookmaker, Collection $events): void
    {
        DB::transaction(function () use ($collectionRun, $bookmaker, $events): void {
            $events->each(function (CollectedOddsEvent $collectedEvent) use ($collectionRun, $bookmaker): void {
                $event = $this->eventMatcher->matchOrCreate($collectedEvent);
                $identity = $this->eventMatcher->sourceIdentity($collectedEvent);
                $sourceEvent = SourceEvent::query()
                    ->where('bookmaker_id', $bookmaker->id)
                    ->where('sport', 'football')
                    ->where('market', $collectedEvent->market)
                    ->where($identity)
                    ->firstOrNew();

                $sourceEvent->fill([
                    ...$identity,
                    'event_id' => $event->id,
                    'bookmaker_id' => $bookmaker->id,
                    'sport' => 'football',
                    'market' => $collectedEvent->market,
                    'home_team' => $collectedEvent->homeTeam,
                    'away_team' => $collectedEvent->awayTeam,
                ]);
                $sourceEvent->save();

                Odd::create([
                    'source_event_id' => $sourceEvent->id,
                    'collection_run_id' => $collectionRun->id,
                    'home_odd' => $collectedEvent->homeOdd,
                    'draw_odd' => $collectedEvent->drawOdd,
                    'away_odd' => $collectedEvent->awayOdd,
                    'collected_at' => $collectedEvent->collectedAt,
                ]);
            });
        });
    }

    private function bookmakerFor(string $source): Bookmaker
    {
        $bookmaker = $this->config->array("services.bookmakers.{$source}");

        return Bookmaker::query()->updateOrCreate(
            ['slug' => $source],
            [
                'name' => $bookmaker['name'],
                'website_url' => $bookmaker['website_url'],
                'is_primary' => $bookmaker['is_primary'],
            ],
        );
    }
}
