<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionRun extends Model
{
    protected $fillable = [
        'status',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function odds(): HasMany
    {
        return $this->hasMany(Odd::class);
    }

    public function sourceResults(): HasMany
    {
        return $this->hasMany(CollectionSourceResult::class);
    }
}
