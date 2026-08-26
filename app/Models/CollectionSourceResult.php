<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionSourceResult extends Model
{
    protected $fillable = [
        'collection_run_id',
        'bookmaker_id',
        'status',
        'events_count',
        'collected_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
        ];
    }

    public function bookmaker(): BelongsTo
    {
        return $this->belongsTo(Bookmaker::class);
    }

    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }
}
