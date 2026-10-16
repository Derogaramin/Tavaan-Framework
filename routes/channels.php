<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — کانال‌های بلادرنگ (DOC-045 §3)
 *
 * این فایل توسط RealtimeServiceProvider در boot بارگذاری می‌شود.
 * هر کانال خصوصی باید callback احراز داشته باشد؛ بدون callback،
 * کانال‌های private/presence رد می‌شوند (fail-closed).
 *
 * نمونه:
 *
 * use Tavaan\Core\Facade\Facades\Broadcast;
 *
 * Broadcast::channel('private:orders.{orderId}', function ($user, int $orderId): bool {
 *     return $user->can('shop.order.view', $orderId);
 * });
 *
 * Broadcast::channel('presence:chat.{room}', function ($user, string $room): array {
 *     return ['id' => $user->getAuthIdentifier(), 'name' => $user->name];
 * });
 */
