<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bookmaker extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'website_url',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function collectionSourceResults(): HasMany
    {
        return $this->hasMany(CollectionSourceResult::class);
    }

    public function sourceEvents(): HasMany
    {
        return $this->hasMany(SourceEvent::class);
    }
}
