<?php

namespace App\Services\Normalization;

use App\Collectors\CollectedOddsEvent;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EventMatcher
{
    private const string SPORT = 'football';

    public function __construct(private readonly EventNormalizer $normalizer) {}

    public function matchOrCreate(CollectedOddsEvent $collectedEvent): Event
    {
        $attributes = [
            'sport' => self::SPORT,
            'normalized_home_team' => $this->normalizer->team($collectedEvent->homeTeam),
            'normalized_away_team' => $this->normalizer->team($collectedEvent->awayTeam),
        ];
        $event = Event::query()
            ->where($attributes)
            ->when(
                $collectedEvent->startsAt,
                fn (Builder $query): Builder => $query->where('starts_at', $collectedEvent->startsAt),
                fn (Builder $query): Builder => $query
                    ->whereNull('starts_at')
                    ->where('event_date', $this->normalizer->eventValue($collectedEvent->eventDate))
                    ->where('event_time', $this->normalizer->eventValue($collectedEvent->eventTime)),
            )
            ->first();

        if ($event !== null) {
            $event->fill([
                'region' => $event->region ?? $collectedEvent->region,
                'country' => $event->country ?? $collectedEvent->country,
                'country_code' => $event->country_code ?? $collectedEvent->countryCode,
                'competition' => $event->competition ?? $collectedEvent->competition,
            ])->save();

            return $event;
        }

        return Event::create([
            ...$attributes,
            'market' => 'pregame',
            'home_team' => $collectedEvent->homeTeam,
            'away_team' => $collectedEvent->awayTeam,
            'event_date' => $this->normalizer->eventValue($collectedEvent->eventDate),
            'event_time' => $this->normalizer->eventValue($collectedEvent->eventTime),
            'starts_at' => $collectedEvent->startsAt,
            'region' => $collectedEvent->region,
            'country' => $collectedEvent->country,
            'country_code' => $collectedEvent->countryCode,
            'competition' => $collectedEvent->competition,
        ]);
    }

    /**
     * @return array{normalized_home_team: string, normalized_away_team: string, event_date: string|null, event_time: string|null, starts_at: CarbonImmutable|null}
     */
    public function sourceIdentity(CollectedOddsEvent $collectedEvent): array
    {
        return [
            'normalized_home_team' => $this->normalizer->team($collectedEvent->homeTeam),
            'normalized_away_team' => $this->normalizer->team($collectedEvent->awayTeam),
            'event_date' => $this->normalizer->eventValue($collectedEvent->eventDate),
            'event_time' => $this->normalizer->eventValue($collectedEvent->eventTime),
            'starts_at' => $collectedEvent->startsAt,
        ];
    }
}
