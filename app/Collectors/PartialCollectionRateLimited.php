<?php

namespace App\Collectors;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use RuntimeException;

final class PartialCollectionRateLimited extends RuntimeException
{
    /** @param Collection<int, CollectedOddsEvent> $events */
    public function __construct(
        public readonly Collection $events,
        public readonly RequestException $rateLimitException,
    ) {
        parent::__construct($rateLimitException->getMessage(), previous: $rateLimitException);
    }
}
