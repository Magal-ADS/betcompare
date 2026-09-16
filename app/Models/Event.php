<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'sport',
        'market',
        'home_team',
        'away_team',
        'normalized_home_team',
        'normalized_away_team',
        'event_date',
        'event_time',
        'starts_at',
        'region',
        'country',
        'country_code',
        'competition',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }

    public function sourceEvents(): HasMany
    {
        return $this->hasMany(SourceEvent::class);
    }
}
