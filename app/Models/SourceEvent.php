<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceEvent extends Model
{
    protected $fillable = [
        'bookmaker_id',
        'event_id',
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

    public function bookmaker(): BelongsTo
    {
        return $this->belongsTo(Bookmaker::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function odds(): HasMany
    {
        return $this->hasMany(Odd::class);
    }

    public function marketOdds(): HasMany
    {
        return $this->hasMany(MarketOdd::class);
    }
}
