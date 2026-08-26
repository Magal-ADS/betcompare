<?php

namespace App\Services\Normalization;

use Illuminate\Support\Str;

final class EventNormalizer
{
    private const array REDUNDANT_TOKENS = [
        'ac', 'ba', 'cf', 'cr', 'ec', 'es', 'fc', 'go', 'mg', 'pa', 'pe', 'pr', 'rj', 'rn', 'rs', 'sc', 'sp',
    ];

    public function team(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->explode(' ')
            ->filter()
            ->reject(fn (string $token): bool => in_array($token, self::REDUNDANT_TOKENS, true))
            ->implode(' ');
    }

    public function eventValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        return $normalized === '' ? null : $normalized;
    }
}
