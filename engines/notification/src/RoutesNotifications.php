<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification;

/**
 * تَوان | Tavaan — Notification
 * RoutesNotifications — trait مدل کاربر برای `$user->notify(...)` (DOC-066 §3).
 *
 * ```php
 * final class User extends Model implements Notifiable
 * {
 *     use RoutesNotifications;
 *     public function routeNotificationForMail(): ?string { return $this->email; }
 * }
 * ```
 *
 * مسیرهای پیش‌فرض: mail → `email`، sms → `mobile`/`phone`.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
trait RoutesNotifications
{
    /** ارسال آگاه‌سازی به این موجودیت. */
    public function notify(Notification $notification): void
    {
        /** @var NotificationSender $sender */
        $sender = \Tavaan\Core\Facade\Facades\Notification::getFacadeRoot();
        $sender->send($this, $notification);
    }

    /**
     * مسیر پیش‌فرض کانال‌ها — با متدهای routeNotificationFor{Channel} قابل override.
     */
    public function routeNotificationFor(string $driver, ?object $notification = null): mixed
    {
        $method = 'routeNotificationFor' . \ucfirst($driver);
        if (\method_exists($this, $method)) {
            return $this->{$method}($notification);
        }

        return match ($driver) {
            'mail' => $this->notificationProperty(['email', 'mail']),
            'sms' => $this->notificationProperty(['mobile', 'phone', 'cellphone']),
            'telegram', 'bale', 'eitaa' => $this->notificationProperty([$driver . '_chat_id', $driver . 'ChatId']),
            'push' => $this->notificationProperty(['push_token', 'device_token']),
            default => null,
        };
    }

    /** کلید morph برای کانال database — مدل‌های ORM از getKey() استفاده می‌کنند. */
    public function getNotificationKey(): int|string
    {
        if (\method_exists($this, 'getKey')) {
            return $this->getKey() ?? \spl_object_id($this);
        }

        return \spl_object_id($this);
    }

    /** @param list<string> $candidates */
    private function notificationProperty(array $candidates): ?string
    {
        foreach ($candidates as $property) {
            if (\property_exists($this, $property)) {
                $value = $this->{$property};
                if (\is_string($value) && $value !== '') {
                    return $value;
                }
                if (\is_int($value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }
}
