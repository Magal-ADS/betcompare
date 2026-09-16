<?php

namespace App\Services\Comparison;

use App\Collectors\MarketCatalog;
use App\Models\CollectionRun;
use App\Models\Event;
use App\Models\MarketOdd;
use App\Models\SourceEvent;
use App\Services\Normalization\EventNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OddsComparisonService
{
    public function __construct(
        private readonly EventNormalizer $eventNormalizer,
        private readonly MarketCatalog $marketCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{event: Event, is_compared: bool, markets: Collection<int, array<string, mixed>>}>
     */
    public function forRun(CollectionRun $collectionRun, array $filters = []): Collection
    {
        return $this->filteredEventsForRunQuery($collectionRun, $filters)
            ->with($this->oddsRelationships($collectionRun))
            ->orderBy('starts_at')
            ->orderBy('home_team')
            ->get()
            ->map(fn (Event $event): array => $this->eventComparison($event))
            ->pipe(fn (Collection $comparisons): Collection => $this->sortComparisons(
                $comparisons,
                (string) ($filters['sort'] ?? 'time'),
            ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{event: Event, is_compared: bool, markets: Collection<int, array<string, mixed>>}>
     */
    public function paginateForRun(CollectionRun $collectionRun, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->filteredEventsForRunQuery($collectionRun, $filters)
            ->with($this->oddsRelationships($collectionRun));

        $this->applyDatabaseSort($query, $collectionRun, (string) ($filters['sort'] ?? 'time'));

        $paginator = $query->paginate($perPage);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Event $event): array => $this->eventComparison($event)),
        );

        return $paginator;
    }

    /**
     * @return array{
     *     regions: Collection<int, array{value: string, label: string}>,
     *     countries: Collection<int, array{value: string, label: string, region: string|null}>,
     *     competitions: Collection<int, array{value: string, label: string, region: string|null, country: string|null}>,
     *     games: Collection<int, array{value: int, label: string, region: string|null, country: string|null, competition: string|null}>
     * }
     */
    public function availableFilters(CollectionRun $collectionRun): array
    {
        $events = $this->eventsForRunQuery($collectionRun)
            ->orderBy('starts_at')
            ->orderBy('home_team')
            ->get(['id', 'home_team', 'away_team', 'region', 'country', 'competition']);
        $regionNames = ['america' => 'América', 'asia' => 'Ásia', 'europe' => 'Europa'];

        return [
            'regions' => collect($regionNames)
                ->filter(fn (string $label, string $region): bool => $events->contains('region', $region))
                ->map(fn (string $label, string $region): array => ['value' => $region, 'label' => $label])
                ->values(),
            'countries' => $events
                ->filter(fn (Event $event): bool => $event->country !== null)
                ->unique(fn (Event $event): string => ($event->region ?? '').'|'.$event->country)
                ->sortBy('country', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (Event $event): array => [
                    'value' => $event->country,
                    'label' => $event->country,
                    'region' => $event->region,
                ])
                ->values(),
            'competitions' => $events
                ->filter(fn (Event $event): bool => $event->competition !== null)
                ->unique(fn (Event $event): string => ($event->region ?? '').'|'.($event->country ?? '').'|'.$event->competition)
                ->sortBy('competition', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (Event $event): array => [
                    'value' => $event->competition,
                    'label' => $event->competition,
                    'region' => $event->region,
                    'country' => $event->country,
                ])
                ->values(),
            'games' => $events->map(fn (Event $event): array => [
                'value' => $event->id,
                'label' => $event->home_team.' x '.$event->away_team,
                'region' => $event->region,
                'country' => $event->country,
                'competition' => $event->competition,
            ])->values(),
        ];
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    public function currentWeek(): array
    {
        $now = CarbonImmutable::now(config('app.display_timezone'));

        return [
            'start' => $now->startOfWeek()->startOfDay(),
            'end' => $now->endOfWeek()->endOfDay(),
        ];
    }

    private function eventsForRunQuery(CollectionRun $collectionRun): Builder
    {
        $week = $this->currentWeek();

        return Event::query()
            ->whereBetween('starts_at', [$week['start']->utc(), $week['end']->utc()])
            ->whereIn('id', SourceEvent::query()
                ->whereIn('id', MarketOdd::query()
                    ->where('collection_run_id', $collectionRun->id)
                    ->select('source_event_id'))
                ->select('event_id'));
    }

    /** @param array<string, mixed> $filters */
    private function filteredEventsForRunQuery(CollectionRun $collectionRun, array $filters): Builder
    {
        return $this->eventsForRunQuery($collectionRun)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                foreach ($this->eventNormalizer->searchTerms($search) as $term) {
                    $query->where(function (Builder $query) use ($term): void {
                        $query->where('normalized_home_team', 'like', "%{$term}%")
                            ->orWhere('normalized_away_team', 'like', "%{$term}%");
                    });
                }
            })
            ->when($filters['region'] ?? null, fn (Builder $query, string $region): Builder => $query->where('region', $region))
            ->when($filters['country'] ?? null, fn (Builder $query, string $country): Builder => $query->where('country', $country))
            ->when($filters['competition'] ?? null, fn (Builder $query, string $competition): Builder => $query->where('competition', $competition))
            ->when($filters['game'] ?? null, fn (Builder $query, int|string $game): Builder => $query->whereKey((int) $game));
    }

    /** @return array<string, string|callable> */
    private function oddsRelationships(CollectionRun $collectionRun): array
    {
        return [
            'sourceEvents.bookmaker',
            'sourceEvents.marketOdds' => function (HasMany $query) use ($collectionRun): void {
                $query->where('collection_run_id', $collectionRun->id)->orderBy('id');
            },
        ];
    }

    private function applyDatabaseSort(Builder $query, CollectionRun $collectionRun, string $sort): void
    {
        if (in_array($sort, ['difference_asc', 'difference_desc'], true)) {
            $bestCompetitorOdds = DB::table('market_odds as competitor_odds')
                ->join('source_events as competitor_source_events', 'competitor_source_events.id', '=', 'competitor_odds.source_event_id')
                ->join('bookmakers as competitor_bookmakers', 'competitor_bookmakers.id', '=', 'competitor_source_events.bookmaker_id')
                ->where('competitor_odds.collection_run_id', $collectionRun->id)
                ->where('competitor_bookmakers.slug', '!=', 'firebets')
                ->groupBy('competitor_source_events.event_id', 'competitor_odds.market_key', 'competitor_odds.selection_key')
                ->select([
                    'competitor_source_events.event_id',
                    'competitor_odds.market_key',
                    'competitor_odds.selection_key',
                ])
                ->selectRaw('MAX(competitor_odds.odd) as best_odd');
            $largestDifference = DB::table('market_odds as firebets_odds')
                ->join('source_events as firebets_source_events', 'firebets_source_events.id', '=', 'firebets_odds.source_event_id')
                ->join('bookmakers as firebets_bookmakers', 'firebets_bookmakers.id', '=', 'firebets_source_events.bookmaker_id')
                ->joinSub($bestCompetitorOdds, 'competitor_best', function ($join): void {
                    $join->on('competitor_best.event_id', '=', 'firebets_source_events.event_id')
                        ->on('competitor_best.market_key', '=', 'firebets_odds.market_key')
                        ->on('competitor_best.selection_key', '=', 'firebets_odds.selection_key');
                })
                ->whereColumn('firebets_source_events.event_id', 'events.id')
                ->where('firebets_odds.collection_run_id', $collectionRun->id)
                ->where('firebets_bookmakers.slug', 'firebets')
                ->selectRaw('COALESCE(MAX(ABS((firebets_odds.odd - competitor_best.best_odd) / NULLIF(competitor_best.best_odd, 0) * 100)), 0)');

            $query->addSelect(['largest_difference' => $largestDifference])
                ->orderBy('largest_difference', $sort === 'difference_desc' ? 'desc' : 'asc')
                ->orderBy('starts_at')
                ->orderBy('home_team');

            return;
        }

        if ($sort === 'team') {
            $query->orderBy('home_team')->orderBy('starts_at');

            return;
        }

        $query->orderBy('starts_at')->orderBy('home_team');
    }

    /**
     * @return array{event: Event, is_compared: bool, markets: Collection<int, array<string, mixed>>}
     */
    private function eventComparison(Event $event): array
    {
        $offers = $event->sourceEvents
            ->flatMap(fn (SourceEvent $sourceEvent): Collection => $sourceEvent->marketOdds->map(
                fn (MarketOdd $marketOdd): array => ['source' => $sourceEvent->bookmaker->slug, 'odd' => $marketOdd],
            ));
        $offersByMarket = $offers->groupBy(fn (array $offer): string => $offer['odd']->market_key);
        $markets = collect($this->marketCatalog->names())
            ->filter(fn (string $name, string $key): bool => $offersByMarket->has($key))
            ->map(function (string $name, string $key) use ($event, $offersByMarket): array {
                $marketOffers = $offersByMarket->get($key);
                $selectionKeys = $marketOffers->pluck('odd.selection_key')->unique()->values();
                $selections = $selectionKeys->map(
                    fn (string $selectionKey): array => $this->selectionComparison($event, $key, $selectionKey, $marketOffers),
                );

                return [
                    'key' => $key,
                    'name' => $name,
                    'is_compared' => $selections->contains(fn (array $selection): bool => $selection['state'] !== 'unavailable'),
                    'selections' => $selections,
                ];
            })
            ->values();

        return [
            'event' => $event,
            'is_compared' => $markets->contains(fn (array $market): bool => $market['is_compared']),
            'markets' => $markets,
        ];
    }

    /**
     * @param  Collection<int, array{source: string, odd: MarketOdd}>  $marketOffers
     * @return array<string, mixed>
     */
    private function selectionComparison(Event $event, string $marketKey, string $selectionKey, Collection $marketOffers): array
    {
        $selectionOffers = $marketOffers
            ->filter(fn (array $offer): bool => $offer['odd']->selection_key === $selectionKey)
            ->values();
        $firebetsOffer = $selectionOffers->firstWhere('source', 'firebets');
        $firebetsOdd = $firebetsOffer === null ? null : (float) $firebetsOffer['odd']->odd;
        $bestOffer = $selectionOffers
            ->reject(fn (array $offer): bool => $offer['source'] === 'firebets')
            ->sortByDesc(fn (array $offer): float => (float) $offer['odd']->odd)
            ->first();
        $bestOdd = $bestOffer === null ? null : (float) $bestOffer['odd']->odd;
        $sourceLabel = $firebetsOffer['odd']->selection_name ?? $selectionOffers->first()['odd']->selection_name;

        return [
            'key' => $selectionKey,
            'label' => $this->selectionLabel($event, $marketKey, $selectionKey, $sourceLabel),
            'odds_by_source' => $selectionOffers->mapWithKeys(fn (array $offer): array => [
                $offer['source'] => $offer['odd']->odd,
            ]),
            'difference_percent_by_source' => $selectionOffers->mapWithKeys(fn (array $offer): array => [
                $offer['source'] => $offer['source'] === 'firebets' || $firebetsOdd === null
                    ? null
                    : (($firebetsOdd - (float) $offer['odd']->odd) / (float) $offer['odd']->odd) * 100,
            ]),
            'best_reference' => $bestOffer === null ? null : [
                'source' => $bestOffer['source'],
                'odd' => $bestOdd,
            ],
            'difference_percent' => $firebetsOdd === null || $bestOdd === null
                ? null
                : (($firebetsOdd - $bestOdd) / $bestOdd) * 100,
            'state' => $this->comparisonState($firebetsOdd, $bestOdd),
        ];
    }

    private function selectionLabel(Event $event, string $marketKey, string $selectionKey, string $sourceLabel): string
    {
        if ($marketKey !== 'match_winner') {
            return $sourceLabel;
        }

        return match ($selectionKey) {
            'casa' => $event->home_team,
            'fora' => $event->away_team,
            default => $sourceLabel,
        };
    }

    private function comparisonState(?float $firebetsOdd, ?float $bestOdd): string
    {
        if ($firebetsOdd === null || $bestOdd === null) {
            return 'unavailable';
        }

        return match (true) {
            $firebetsOdd > $bestOdd => 'above',
            $firebetsOdd < $bestOdd => 'below',
            default => 'equal',
        };
    }

    /**
     * @param  Collection<int, array{event: Event, is_compared: bool, markets: Collection<int, array<string, mixed>>}>  $comparisons
     */
    private function sortComparisons(Collection $comparisons, string $sort): Collection
    {
        return match ($sort) {
            'team' => $comparisons->sortBy(fn (array $comparison): string => $comparison['event']->home_team)->values(),
            'difference_asc' => $comparisons->sortBy(fn (array $comparison): float => $this->largestDifference($comparison))->values(),
            'difference_desc' => $comparisons->sortByDesc(fn (array $comparison): float => $this->largestDifference($comparison))->values(),
            default => $comparisons,
        };
    }

    /** @param array{markets: Collection<int, array<string, mixed>>} $comparison */
    private function largestDifference(array $comparison): float
    {
        return $comparison['markets']
            ->flatMap(fn (array $market): Collection => $market['selections'])
            ->pluck('difference_percent')
            ->filter(fn (?float $difference): bool => $difference !== null)
            ->map(fn (float $difference): float => abs($difference))
            ->max() ?? 0;
    }
}
