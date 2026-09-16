<?php

namespace App\Services\Collection;

use App\Models\Bookmaker;
use App\Models\CollectionSourceResult;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

final class SourceRateLimitPolicy
{
    public function __construct(private readonly Repository $config) {}

    public function activeRetryAt(Bookmaker $bookmaker): ?CarbonImmutable
    {
        $latestResult = CollectionSourceResult::query()
            ->whereBelongsTo($bookmaker)
            ->latest('id')
            ->first(['status', 'retry_at']);

        if (! in_array($latestResult?->status, ['rate_limited', 'deferred'], true)) {
            return null;
        }

        $retryAt = $latestResult->retry_at?->toImmutable();

        return $retryAt !== null && $retryAt->isFuture() ? $retryAt : null;
    }

    public function isRateLimited(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return false;
        }

        return $exception->response->status() === 429
            || Str::contains($exception->response->body(), 'error code: 1015', ignoreCase: true);
    }

    public function nextRetryAt(Bookmaker $bookmaker, RequestException $exception): CarbonImmutable
    {
        $backoffMinutes = $this->config->array('oddradar.source_rate_limit_backoff_minutes');
        $previousFailures = $this->consecutiveRateLimitFailures($bookmaker);
        $backoffIndex = min($previousFailures, count($backoffMinutes) - 1);
        $backoffRetryAt = CarbonImmutable::now()->addMinutes((int) $backoffMinutes[$backoffIndex]);
        $sourceRetryAt = $this->retryAfter($exception);
        $maximumRetryAt = CarbonImmutable::now()->addMinutes(
            $this->config->integer('oddradar.source_rate_limit_max_minutes', 1440),
        );
        $retryAt = $sourceRetryAt !== null && $sourceRetryAt->greaterThan($backoffRetryAt)
            ? $sourceRetryAt
            : $backoffRetryAt;

        return $retryAt->lessThanOrEqualTo($maximumRetryAt) ? $retryAt : $maximumRetryAt;
    }

    private function consecutiveRateLimitFailures(Bookmaker $bookmaker): int
    {
        $failures = 0;
        $statuses = CollectionSourceResult::query()
            ->whereBelongsTo($bookmaker)
            ->latest('id')
            ->pluck('status');

        foreach ($statuses as $status) {
            if ($status === 'deferred') {
                continue;
            }

            if ($status !== 'rate_limited') {
                break;
            }

            $failures++;
        }

        return $failures;
    }

    private function retryAfter(RequestException $exception): ?CarbonImmutable
    {
        $retryAfter = $exception->response->header('Retry-After');

        if ($retryAfter === null || trim($retryAfter) === '') {
            return null;
        }

        if (ctype_digit($retryAfter)) {
            return CarbonImmutable::now()->addSeconds((int) $retryAfter);
        }

        try {
            $retryAt = CarbonImmutable::parse($retryAfter);

            return $retryAt->isFuture() ? $retryAt : null;
        } catch (Throwable) {
            return null;
        }
    }
}
