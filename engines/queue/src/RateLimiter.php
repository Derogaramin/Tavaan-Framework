<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * RateLimiter — محدودیت نرخ اجرای job (DOC-061 §3: withRateLimit).
 *
 * شمارنده در cache (کلید `tvn:queue:rate:{key}`) با TTL پنجره نگه داشته
 * می‌شود. بدون cache → همیشه مجاز (degrade ایمن).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class RateLimiter
{
    public function __construct(private readonly ?CacheRepository $cache = null)
    {
    }

    /**
     * تلاش برای مصرف یک واحد از سهمیه.
     *
     * @param array{key: string, max: int, seconds?: int} $limit
     *
     * @return bool آیا اجرا مجاز است؟
     */
    public function attempt(array $limit): bool
    {
        if ($this->cache === null) {
            return true;
        }

        $key = 'tvn:queue:rate:' . (string) ($limit['key'] ?? '');
        $max = \max(1, (int) ($limit['max'] ?? 1));
        $seconds = \max(1, (int) ($limit['seconds'] ?? 60));

        // 🔒 add اتمیک است — اولین مصرف، TTL پنجره را ست می‌کند
        if ($this->cache->add($key, 1, $seconds)) {
            return true;
        }

        $current = $this->cache->increment($key);
        if ($current === false) {
            return true;    // خطای cache → fail-open
        }

        return $current <= $max;
    }

    /**
     * تأخیر پیشنهادی برای release وقتی سهمیه تمام است (ثانیه).
     *
     * @param array{key: string, max: int, seconds?: int} $limit
     */
    public function retryAfter(array $limit): int
    {
        // محافظه‌کارانه: کل پنجره (بدون دسترسی به TTL باقی‌مانده در قرارداد cache)
        return \min(\max(1, (int) ($limit['seconds'] ?? 60)), 60);
    }

    /** پاک‌سازی شمارنده (تست/ریست دستی). */
    public function reset(string $key): void
    {
        $this->cache?->forget('tvn:queue:rate:' . $key);
    }

    public function now(): int
    {
        return Clock::timestamp();
    }
}
