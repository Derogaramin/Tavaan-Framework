<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Realtime;

/**
 * تَوان | Tavaan — Contracts
 * ShouldBroadcast — رویدادی که باید روی کانال‌های بلادرنگ منتشر شود (DOC-045 §3).
 *
 * EventDispatcher پس از اجرای listenerها، این رویدادها را به موتور
 * realtime تحویل می‌دهد (در صورت bind بودن).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
interface ShouldBroadcast
{
    /** @return list<string> نام کانال‌ها */
    public function broadcastOn(): array;

    /** نام رویداد در پروتکل (پیش‌فرض: نام کلاس). */
    public function broadcastAs(): string;

    /** @return array<string, mixed> payload ارسالی */
    public function broadcastWith(): array;

    /** شرط انتشار (پیش‌فرض true). */
    public function broadcastWhen(): bool;
}
