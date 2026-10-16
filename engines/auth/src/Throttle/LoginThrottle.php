<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Throttle;

use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Auth
 * LoginThrottle — محدودسازی نرخ تلاش ورود/OTP/reset (DOC-056 §9).
 *
 * الگوریتم Fixed-Window روی cache با شمارنده + زمان قفل.
 * 🔒 کلید = hash(شناسه + IP) تا کاربر با rotate شدن IP قفل را نشکند… اما
 * مهاجم هم نتواند حساب دیگران را با IP خود قفل کند (decay کوتاه).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class LoginThrottle
{
    public function __construct(
        private readonly RepositoryInterface $cache,
        private readonly int $maxAttempts = 5,
        private readonly int $decaySeconds = 60,
        private readonly int $lockSeconds = 900,
        private readonly string $prefix = 'auth:throttle:',
    ) {
    }

    private function key(string $identifier): string
    {
        return $this->prefix . \hash('sha256', $identifier);
    }

    /** ثبت یک تلاش ناموفق. */
    public function hit(string $identifier): int
    {
        $key = $this->key($identifier);

        /** @var array{attempts: int, reset_at: int}|null $payload */
        $payload = $this->cache->get($key);
        $now = Clock::timestamp();

        if ($payload === null || $payload['reset_at'] <= $now) {
            $payload = ['attempts' => 0, 'reset_at' => $now + $this->decaySeconds];
        }

        $payload['attempts']++;

        $ttl = $payload['reset_at'] > $now ? $payload['reset_at'] - $now : $this->decaySeconds;
        if ($payload['attempts'] >= $this->maxAttempts && $this->lockSeconds > 0) {
            $payload['locked_until'] = $now + $this->lockSeconds;
            $ttl = \max($ttl, $this->lockSeconds);
        }

        $this->cache->put($key, $payload, $ttl);

        return $payload['attempts'];
    }

    /** آیا اکنون قفل است؟ */
    public function tooManyAttempts(string $identifier): bool
    {
        $payload = $this->payload($identifier);
        if ($payload === null) {
            return false;
        }

        $now = Clock::timestamp();

        if (isset($payload['locked_until']) && $payload['locked_until'] > $now) {
            return true;
        }

        return ($payload['attempts'] ?? 0) >= $this->maxAttempts && ($payload['reset_at'] ?? 0) > $now;
    }

    /** ثانیه‌های باقی‌مانده تا آزادسازی. */
    public function availableIn(string $identifier): int
    {
        $payload = $this->payload($identifier);
        if ($payload === null) {
            return 0;
        }

        $now = Clock::timestamp();
        $lockedUntil = (int) ($payload['locked_until'] ?? 0);
        if ($lockedUntil > $now) {
            return $lockedUntil - $now;
        }

        $resetAt = (int) ($payload['reset_at'] ?? 0);

        return ($payload['attempts'] ?? 0) >= $this->maxAttempts && $resetAt > $now ? $resetAt - $now : 0;
    }

    public function attempts(string $identifier): int
    {
        return (int) ($this->payload($identifier)['attempts'] ?? 0);
    }

    public function clear(string $identifier): void
    {
        $this->cache->forget($this->key($identifier));
    }

    public function assertAllowed(string $identifier): void
    {
        if ($this->tooManyAttempts($identifier)) {
            throw new \Tavaan\Engine\Auth\Exceptions\ThrottledException($this->availableIn($identifier));
        }
    }

    /** @return array<string, mixed>|null */
    private function payload(string $identifier): ?array
    {
        $payload = $this->cache->get($this->key($identifier));

        return \is_array($payload) ? $payload : null;
    }
}
