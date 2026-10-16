<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Realtime;

/**
 * تَوان | Tavaan — Contracts
 * BrokerInterface — Message Broker برای fan-out بین workerها (DOC-045 §6).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
interface BrokerInterface
{
    /**
     * انتشار رویداد روی یک کانال.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(string $channel, string $name, array $payload): void;

    /**
     * دریافت رویدادهای جدید (پس از afterId) برای مجموعه‌ای از کانال‌ها.
     *
     * @param list<string> $channels
     * @param int          $timeout  حداکثر انتظار (ثانیه) — برای poll blocking
     * @param int          $afterId  مکان‌نمای مصرف‌کننده (۰ = از ابتدا)
     *
     * @return list<array{id: int, channel: string, name: string, payload: array<string, mixed>, at: string}>
     */
    public function poll(array $channels, int $timeout = 1, int $afterId = 0): array;

    /** آخرین id منتشرشده (برای شروع مصرف از «اکنون»). */
    public function lastId(): int;

    public function name(): string;
}
