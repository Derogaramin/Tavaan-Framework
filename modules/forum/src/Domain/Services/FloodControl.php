<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * FloodControl — کنترل نرخ ارسال پست (DOC-094 §6/§7 + E2E-07).
 */

namespace Modules\Forum\Domain\Services;

/**
 * FloodControl — تصمیم‌گیر دامنهٔ خالص (بدون I/O): با توجه به زمان آخرین
 * نوشته‌های کاربر، اجازهٔ نوشتن می‌دهد یا نه.
 *
 * قواعد:
 * 1. حداقل فاصلهٔ زمانی بین دو پست (`min_seconds`)
 * 2. حداکثر تعداد پست در پنجرهٔ لغزان (`max_posts` در `window_seconds`)
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class FloodControl
{
    /**
     * @param array{enabled?: bool, min_seconds?: int, window_seconds?: int, max_posts?: int} $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * بررسی flood.
     *
     * @param list<\DateTimeImmutable> $recentPostTimes زمان نوشتن‌های اخیر کاربر
     *                                                  (داخل پنجره، صعودی)
     *
     * @return array{allowed: bool, reason: string, retry_after: int}
     */
    public function decide(array $recentPostTimes, \DateTimeImmutable $now): array
    {
        if (!$this->enabled()) {
            return ['allowed' => true, 'reason' => '', 'retry_after' => 0];
        }

        $minSeconds = \max(0, (int) ($this->config['min_seconds'] ?? 30));
        $window = \max(1, (int) ($this->config['window_seconds'] ?? 300));
        $maxPosts = \max(1, (int) ($this->config['max_posts'] ?? 10));

        // داخل پنجره نگه دار
        $windowStart = $now->sub(new \DateInterval('PT' . $window . 'S'));
        $inWindow = \array_values(\array_filter(
            $recentPostTimes,
            static fn (\DateTimeImmutable $t): bool => $t >= $windowStart,
        ));

        // قاعدهٔ ۱: حداقل فاصله
        if ($inWindow !== [] && $minSeconds > 0) {
            $last = $inWindow[\count($inWindow) - 1];
            $elapsed = $now->getTimestamp() - $last->getTimestamp();
            if ($elapsed < $minSeconds) {
                return [
                    'allowed' => false,
                    'reason' => 'min_interval',
                    'retry_after' => $minSeconds - $elapsed,
                ];
            }
        }

        // قاعدهٔ ۲: سقف پنجرهٔ لغزان
        if (\count($inWindow) >= $maxPosts) {
            $oldest = $inWindow[0];
            $retryAfter = \max(1, $window - ($now->getTimestamp() - $oldest->getTimestamp()));

            return ['allowed' => false, 'reason' => 'window_limit', 'retry_after' => $retryAfter];
        }

        return ['allowed' => true, 'reason' => '', 'retry_after' => 0];
    }
}
