<?php

namespace App\Services\Collection;

use App\Collectors\CollectedMarket;
use App\Collectors\CollectedOddsEvent;
use App\Collectors\OddsCollectorRegistry;
use App\Models\Bookmaker;
use App\Models\CollectionRun;
use App\Models\CollectionSourceResult;
use App\Models\MarketOdd;
use App\Models\SourceEvent;
use App\Services\Normalization\EventMatcher;
use App\Services\Normalization\EventNormalizer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\RequestException;
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

    private const string STATUS_DEFERRED = 'deferred';

    private const string STATUS_RATE_LIMITED = 'rate_limited';

    public function __construct(
        private readonly EventMatcher $eventMatcher,
        private readonly EventNormalizer $eventNormalizer,
        private readonly Repository $config,
        private readonly OddsCollectorRegistry $collectorRegistry,
        private readonly SourceRateLimitPolicy $sourceRateLimitPolicy,
    ) {}

    public function execute(): CollectionRun
    {
        $collectionRun = CollectionRun::create([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
        $successfulSources = 0;
        $deferredSources = 0;
        $collectors = $this->collectorRegistry->all();

        foreach ($collectors as $collector) {
            $bookmaker = $this->bookmakerFor($collector->source());
            $retryAt = $this->sourceRateLimitPolicy->activeRetryAt($bookmaker);

            if ($retryAt !== null) {
                CollectionSourceResult::create([
                    'collection_run_id' => $collectionRun->id,
                    'bookmaker_id' => $bookmaker->id,
                    'status' => self::STATUS_DEFERRED,
                    'retry_at' => $retryAt,
                ]);
                $deferredSources++;

                continue;
            }

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
                $isRateLimited = $this->sourceRateLimitPolicy->isRateLimited($exception);
                $retryAt = $isRateLimited && $exception instanceof RequestException
                    ? $this->sourceRateLimitPolicy->nextRetryAt($bookmaker, $exception)
                    : null;

                Log::warning('OddRadar collection source failed.', [
                    'source' => $collector->source(),
                    'collection_run_id' => $collectionRun->id,
                    'exception' => $exception::class,
                    'http_status' => $exception instanceof RequestException ? $exception->response->status() : null,
                    'retry_at' => $retryAt?->toIso8601String(),
                ]);

                CollectionSourceResult::create([
                    'collection_run_id' => $collectionRun->id,
                    'bookmaker_id' => $bookmaker->id,
                    'status' => $isRateLimited ? self::STATUS_RATE_LIMITED : self::STATUS_FAILED,
                    'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                    'http_status' => $exception instanceof RequestException ? $exception->response->status() : null,
                    'retry_at' => $retryAt,
                ]);
            }
        }

        $collectionRun->update([
            'status' => match ($successfulSources) {
                0 => count($collectors) > 0 && $deferredSources === count($collectors)
                    ? self::STATUS_DEFERRED
                    : self::STATUS_FAILED,
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
                $identity['normalized_home_team'],
                $identity['normalized_away_team'],
                $identity['starts_at']?->toIso8601String() ?? ($identity['event_date'] ?? ''),
                $identity['starts_at'] === null ? ($identity['event_time'] ?? '') : '',
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
                    ->where('market', 'pregame')
                    ->where($identity)
                    ->firstOrNew();

                $sourceEvent->fill([
                    ...$identity,
                    'event_id' => $event->id,
                    'bookmaker_id' => $bookmaker->id,
                    'sport' => 'football',
                    'market' => 'pregame',
                    'home_team' => $collectedEvent->homeTeam,
                    'away_team' => $collectedEvent->awayTeam,
                    'region' => $collectedEvent->region,
                    'country' => $collectedEvent->country,
                    'country_code' => $collectedEvent->countryCode,
                    'competition' => $collectedEvent->competition,
                ]);
                $sourceEvent->save();

                collect($collectedEvent->markets)->each(function (CollectedMarket $market) use ($sourceEvent, $collectionRun, $collectedEvent): void {
                    collect($market->selections)
                        ->unique(fn (array $selection): string => $this->eventNormalizer->marketSelection($selection['label']))
                        ->each(function (array $selection) use ($sourceEvent, $collectionRun, $collectedEvent, $market): void {
                            MarketOdd::create([
                                'source_event_id' => $sourceEvent->id,
                                'collection_run_id' => $collectionRun->id,
                                'market_key' => $market->key,
                                'market_name' => $market->name,
                                'selection_key' => $this->eventNormalizer->marketSelection($selection['label']),
                                'selection_name' => $selection['label'],
                                'odd' => $selection['odd'],
                                'collected_at' => $collectedEvent->collectedAt,
                            ]);
                        });
                });
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
