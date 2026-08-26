<?php

namespace App\Services\Comparison;

use App\Models\CollectionRun;
use App\Models\Event;
use App\Models\Odd;
use App\Models\SourceEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final class OddsComparisonService
{
    /**
     * @return Collection<int, array{event: Event, is_compared: bool, selections: Collection<int, array<string, mixed>>}>
     */
    public function forRun(CollectionRun $collectionRun): Collection
    {
        return Event::query()
            ->whereHas('sourceEvents.odds', function (Builder $query) use ($collectionRun): void {
                $query->where('collection_run_id', $collectionRun->id);
            })
            ->with([
                'sourceEvents.bookmaker',
                'sourceEvents.odds' => function (HasMany $query) use ($collectionRun): void {
                    $query->where('collection_run_id', $collectionRun->id);
                },
            ])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->orderBy('home_team')
            ->get()
            ->map(fn (Event $event): array => $this->eventComparison($event));
    }

    /**
     * @return array{event: Event, is_compared: bool, selections: Collection<int, array<string, mixed>>}
     */
    private function eventComparison(Event $event): array
    {
        $offers = $event->sourceEvents
            ->filter(fn (SourceEvent $sourceEvent): bool => $sourceEvent->odds->isNotEmpty())
            ->map(fn (SourceEvent $sourceEvent): array => [
                'source' => $sourceEvent->bookmaker->slug,
                'odd' => $sourceEvent->odds->sole(),
            ]);

        $selections = collect([
            ['key' => 'home', 'label' => $event->home_team, 'column' => 'home_odd'],
            ['key' => 'draw', 'label' => 'Empate', 'column' => 'draw_odd'],
            ['key' => 'away', 'label' => $event->away_team, 'column' => 'away_odd'],
        ])->map(fn (array $selection): array => $this->selectionComparison($selection, $offers));

        return [
            'event' => $event,
            'is_compared' => $offers->contains(fn (array $offer): bool => $offer['source'] === 'firebets')
                && $selections->contains(fn (array $selection): bool => $selection['best_reference'] !== null),
            'selections' => $selections,
        ];
    }

    /**
     * @param  array{key: string, label: string, column: string}  $selection
     * @param  Collection<int, array{source: string, odd: Odd}>  $offers
     * @return array<string, mixed>
     */
    private function selectionComparison(array $selection, Collection $offers): array
    {
        $firebetsOffer = $offers->firstWhere('source', 'firebets');
        $firebetsOdd = $firebetsOffer === null ? null : $firebetsOffer['odd']->{$selection['column']};
        $bestOffer = $offers
            ->reject(fn (array $offer): bool => $offer['source'] === 'firebets')
            ->sortByDesc(fn (array $offer): float => (float) $offer['odd']->{$selection['column']})
            ->first();
        $bestOdd = $bestOffer === null ? null : (float) $bestOffer['odd']->{$selection['column']};
        $firebetsValue = $firebetsOdd === null ? null : (float) $firebetsOdd;

        return [
            ...$selection,
            'odds_by_source' => $offers->mapWithKeys(fn (array $offer): array => [
                $offer['source'] => $offer['odd']->{$selection['column']},
            ]),
            'best_reference' => $bestOffer === null ? null : [
                'source' => $bestOffer['source'],
                'odd' => $bestOdd,
            ],
            'difference_percent' => $firebetsValue === null || $bestOdd === null
                ? null
                : (($firebetsValue - $bestOdd) / $bestOdd) * 100,
            'state' => $this->comparisonState($firebetsValue, $bestOdd),
        ];
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
}
