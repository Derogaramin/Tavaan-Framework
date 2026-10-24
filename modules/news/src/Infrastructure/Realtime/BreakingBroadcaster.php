<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * BreakingBroadcaster — خبر فوری روی کانال realtime (DOC-092 §6 + E2E-03).
 */

namespace Modules\News\Infrastructure\Realtime;

use Tavaan\Core\Facade\Facades\Broadcast;

/**
 * BreakingBroadcaster — شنوندهٔ `news.breaking.published`.
 *
 * Broadcast::publish('news', 'breaking', payload) — با broker پیش‌فرض (array)
 * بی‌اثر و بی‌خطر است؛ در استقرار واقعی به مشترکین کانال `news` push می‌شود.
 * best-effort: خطای realtime جریان انتشار را نمی‌شکند.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class BreakingBroadcaster
{
    /**
     * @param array<string, mixed> $config کلید config('news.realtime')
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /** قرارداد listener رویداد رشته‌ای: ($eventName, $payload). */
    public function onBreaking(string $event, mixed $payload): void
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            return;
        }

        try {
            Broadcast::publish(
                (string) ($this->config['channel'] ?? 'news'),
                'breaking',
                (array) $payload,
            );
        } catch (\Throwable) {
            // best-effort
        }
    }
}
