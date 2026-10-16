<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Tests\Fixtures;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\RoutesNotifications;

/** Fixture: کاربر آگاه‌پذیر (ایمیل/موبایل/chat). */
final class TestNotifiable implements Notifiable
{
    use RoutesNotifications;

    public function __construct(
        public readonly int $id = 1,
        public string $email = 'user@example.com',
        public string $mobile = '09121234567',
        public string $telegram_chat_id = '98765',
    ) {
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
