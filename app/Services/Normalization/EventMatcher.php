<?php

namespace App\Services\Normalization;

use App\Collectors\CollectedOddsEvent;
use App\Models\Event;

final class EventMatcher
{
    private const string SPORT = 'football';

    public function __construct(private readonly EventNormalizer $normalizer) {}

    public function matchOrCreate(CollectedOddsEvent $collectedEvent): Event
    {
        $attributes = [
            'sport' => self::SPORT,
            'market' => $collectedEvent->market,
            'normalized_home_team' => $this->normalizer->team($collectedEvent->homeTeam),
            'normalized_away_team' => $this->normalizer->team($collectedEvent->awayTeam),
            'event_date' => $this->normalizer->eventValue($collectedEvent->eventDate),
            'event_time' => $this->normalizer->eventValue($collectedEvent->eventTime),
        ];

        $event = Event::query()->where($attributes)->first();

        return $event ?? Event::create([
            ...$attributes,
            'home_team' => $collectedEvent->homeTeam,
            'away_team' => $collectedEvent->awayTeam,
        ]);
    }

    /**
     * @return array{normalized_home_team: string, normalized_away_team: string, event_date: string|null, event_time: string|null}
     */
    public function sourceIdentity(CollectedOddsEvent $collectedEvent): array
    {
        return [
            'normalized_home_team' => $this->normalizer->team($collectedEvent->homeTeam),
            'normalized_away_team' => $this->normalizer->team($collectedEvent->awayTeam),
            'event_date' => $this->normalizer->eventValue($collectedEvent->eventDate),
            'event_time' => $this->normalizer->eventValue($collectedEvent->eventTime),
        ];
    }
}
