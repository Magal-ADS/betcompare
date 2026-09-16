<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketOdd extends Model
{
    protected $fillable = [
        'source_event_id',
        'collection_run_id',
        'market_key',
        'market_name',
        'selection_key',
        'selection_name',
        'odd',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'odd' => 'decimal:3',
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
