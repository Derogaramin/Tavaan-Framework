<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Notification;

/**
 * تَوان | Tavaan — Contracts
 * Notifiable — قرارداد موجودیت آگاه‌پذیر (User/…؛ DOC-066 §3).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
interface Notifiable
{
    /**
     * مسیر آگاه‌سازی برای کانال مشخص (ایمیل/شماره/chat id/…).
     *
     * @return mixed string|list<string>|null
     */
    public function routeNotificationFor(string $driver, ?object $notification = null): mixed;

    /** شناسهٔ یکتا برای کانال database (morph id). */
    public function getNotificationKey(): int|string;
}
