<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Odd extends Model
{
    protected $fillable = [
        'source_event_id',
        'collection_run_id',
        'home_odd',
        'draw_odd',
        'away_odd',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'home_odd' => 'decimal:3',
            'draw_odd' => 'decimal:3',
            'away_odd' => 'decimal:3',
            'collected_at' => 'datetime',
        ];
    }

    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(SourceEvent::class);
    }
}
