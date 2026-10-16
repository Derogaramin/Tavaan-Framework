<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\RateLimit;

use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Security
 * RateLimiter — محدودسازی نرخ با سه الگوریتم (DOC-059 §2.2).
 *
 * | الگوریتم | پیاده‌سازی |
 * |---|---|
 * | Sliding Window (پیش‌فرض) | sliding-log روی cache (دقیق؛ حافظه ≤ max) |
 * | Fixed Window | شمارندهٔ پنجرهٔ ثابت |
 * | Token Bucket | سطل با نرخ پرشدن |
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class RateLimiter
{
    public function __construct(
        private readonly RepositoryInterface $cache,
        private readonly string $algorithm = 'sliding',
        private readonly string $prefix = 'ratelimit:',
    ) {
    }

    /**
     * یک درخواست مصرف می‌کند؛ false یعنی رد (بیش از حد).
     */
    public function attempt(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            return false;
        }

        $this->hit($key, $decaySeconds);

        return true;
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        return match ($this->algorithm) {
            'fixed' => $this->fixedCount($key) >= $maxAttempts,
            'token_bucket' => $this->bucketTokens($key, $maxAttempts, $decaySeconds) < 1.0,
            default => $this->slidingCount($key, $decaySeconds) >= $maxAttempts,
        };
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        return match ($this->algorithm) {
            'fixed' => $this->fixedHit($key, $decaySeconds),
            'token_bucket' => (int) $this->bucketConsume($key, $decaySeconds),
            default => $this->slidingHit($key, $decaySeconds),
        };
    }

    public function remaining(string $key, int $maxAttempts, int $decaySeconds = 60): int
    {
        $used = match ($this->algorithm) {
            'fixed' => $this->fixedCount($key),
            'token_bucket' => (int) \floor($this->bucketTokens($key, $maxAttempts, $decaySeconds)),
            default => $this->slidingCount($key, $decaySeconds),
        };

        return \max(0, $maxAttempts - $used);
    }

    /** ثانیه تا آزادسازی (برای Retry-After). */
    public function availableIn(string $key, int $decaySeconds = 60): int
    {
        $payload = $this->payload($key);
        if ($payload === null) {
            return 0;
        }

        $now = Clock::timestamp();

        if ($this->algorithm === 'fixed') {
            return \max(0, (int) ($payload['reset_at'] ?? 0) - $now);
        }

        if ($this->algorithm === 'token_bucket') {
            $tokens = $this->bucketTokens($key, (int) ($payload['max'] ?? 60), $decaySeconds);

            return $tokens > 0 ? 0 : (int) \ceil((1 - $tokens) * ($decaySeconds / \max(1, (int) ($payload['max'] ?? 60))));
        }

        $hits = $this->freshHits($payload, $decaySeconds);
        if ($hits === []) {
            return 0;
        }

        return \max(0, (int) $hits[0] + $decaySeconds - $now);
    }

    public function clear(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
    }

    public function attempts(string $key, int $decaySeconds = 60): int
    {
        return match ($this->algorithm) {
            'fixed' => $this->fixedCount($key),
            default => $this->slidingCount($key, $decaySeconds),
        };
    }

    // ─── Sliding Window (log) ─────────────────────────────────────────────

    /** @return list<int> */
    private function freshHits(array $payload, int $decaySeconds): array
    {
        $now = Clock::timestamp();
        $cutoff = $now - $decaySeconds;

        return \array_values(\array_filter(
            (array) ($payload['hits'] ?? []),
            static fn ($ts): bool => \is_int($ts) && $ts > $cutoff,
        ));
    }

    private function slidingCount(string $key, int $decaySeconds): int
    {
        $payload = $this->payload($key);
        if ($payload === null) {
            return 0;
        }

        return \count($this->freshHits($payload, $decaySeconds));
    }

    private function slidingHit(string $key, int $decaySeconds): int
    {
        $payload = $this->payload($key) ?? ['hits' => []];
        $hits = $this->freshHits($payload, $decaySeconds);
        $hits[] = Clock::timestamp();

        $this->cache->put($this->cacheKey($key), ['hits' => $hits], $decaySeconds + 1);

        return \count($hits);
    }

    // ─── Fixed Window ─────────────────────────────────────────────────────

    private function fixedCount(string $key): int
    {
        $payload = $this->payload($key);
        if ($payload === null || (int) ($payload['reset_at'] ?? 0) <= Clock::timestamp()) {
            return 0;
        }

        return (int) ($payload['count'] ?? 0);
    }

    private function fixedHit(string $key, int $decaySeconds): int
    {
        $now = Clock::timestamp();
        $payload = $this->payload($key);

        if ($payload === null || (int) ($payload['reset_at'] ?? 0) <= $now) {
            $payload = ['count' => 0, 'reset_at' => $now + $decaySeconds];
        }

        $payload['count'] = (int) $payload['count'] + 1;
        $ttl = \max(1, (int) $payload['reset_at'] - $now);
        $this->cache->put($this->cacheKey($key), $payload, $ttl);

        return (int) $payload['count'];
    }

    // ─── Token Bucket ─────────────────────────────────────────────────────

    /** @return float تعداد توکن فعلی */
    private function bucketTokens(string $key, int $maxAttempts, int $decaySeconds): float
    {
        $payload = $this->payload($key);
        $now = (float) Clock::timestamp();   // Clock (قابل Fake در تست)

        if ($payload === null) {
            return (float) $maxAttempts;
        }

        $tokens = (float) ($payload['tokens'] ?? $maxAttempts);
        $last = (float) ($payload['ts'] ?? $now);

        // نرخ پرشدن: max توکن در decaySeconds (refill منفی ممکن نیست)
        $refill = \max(0.0, $now - $last) * ($maxAttempts / \max(1, $decaySeconds));

        return \min((float) $maxAttempts, $tokens + $refill);
    }

    private function bucketConsume(string $key, int $decaySeconds): int
    {
        $payload = $this->payload($key);
        $max = (int) ($payload['max'] ?? 60);
        $tokens = $this->bucketTokens($key, $max, $decaySeconds);
        $tokens = \max(0.0, $tokens - 1);

        $this->cache->put($this->cacheKey($key), [
            'tokens' => $tokens,
            'ts' => (float) Clock::timestamp(),
            'max' => $max,
        ], $decaySeconds * 2);

        return (int) \floor($tokens);
    }

    /** شروع سطل با ظرفیت مشخص (قبل از attempt). */
    public function ensureBucket(string $key, int $maxAttempts): void
    {
        if ($this->payload($key) === null) {
            $this->cache->put($this->cacheKey($key), [
                'tokens' => (float) $maxAttempts,
                'ts' => (float) Clock::timestamp(),
                'max' => $maxAttempts,
            ], 3600);
        }
    }

    // ─── مشترک ────────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function payload(string $key): ?array
    {
        $payload = $this->cache->get($this->cacheKey($key));

        return \is_array($payload) ? $payload : null;
    }

    private function cacheKey(string $key): string
    {
        return $this->prefix . \hash('xxh128', $key);
    }
}
