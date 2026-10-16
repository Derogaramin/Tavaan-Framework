<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Passwords;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Auth\UserProviderInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Auth\Throttle\LoginThrottle;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Auth
 * PasswordBroker — لینک بازیابی رمز (DOC-056 §4: sendPasswordResetLink/resetPassword).
 *
 * 🔒 توکن خام هرگز ذخیره نمی‌شود (فقط هش)؛ انقضا + throttle.
 * 🔒 User Enumeration: برای ایمیل ناموجود هم «ارسال شد» برگردان می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class PasswordBroker
{
    public const RESET_LINK_SENT = 'auth.reset.sent';

    public const PASSWORD_RESET = 'auth.reset.done';

    public const INVALID_TOKEN = 'auth.reset.invalid_token';

    public const EXPIRED_TOKEN = 'auth.reset.expired';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly UserProviderInterface $provider,
        private readonly HasherInterface $hasher,
        private readonly string $table = 'password_resets',
        private readonly int $expireSeconds = 3600,
        private readonly ?LoginThrottle $throttle = null,
        private readonly ?DispatcherInterface $events = null,
        /** @var \Closure(string, string, Authenticatable): void|null ارسال لینک (mail تا M5 با رویداد) */
        private readonly ?\Closure $linkSender = null,
    ) {
    }

    /**
     * ارسال لینک بازیابی — خروجی هیچ‌گاه وجود کاربر را لو نمی‌دهد.
     */
    public function sendResetLink(string $email): string
    {
        $this->throttle?->assertAllowed('reset:' . $email);
        $this->throttle?->hit('reset:' . $email);

        $user = $this->provider->retrieveByCredentials(['email' => $email]);
        if ($user === null) {
            // 🔒 User Enumeration — وانمود به ارسال
            return self::RESET_LINK_SENT;
        }

        $token = \bin2hex(\random_bytes(32));

        $this->db->table($this->table)->updateOrInsert(
            ['email' => $email],
            [
                'token' => \hash('sha256', $token),
                'created_at' => Clock::utcNow()->format('Y-m-d H:i:s'),
            ],
        );

        if ($this->linkSender !== null) {
            ($this->linkSender)($email, $token, $user);
        }
        $this->events?->dispatch('auth.password.reset_link_sent', ['email_masked' => $this->mask($email)]);

        return self::RESET_LINK_SENT;
    }

    /**
     * بازنشانی رمز با توکن.
     *
     * @param \Closure(Authenticatable, string): void $callback ذخیرهٔ رمز جدید
     */
    public function reset(string $email, string $token, #[\SensitiveParameter] string $newPassword, \Closure $callback): string
    {
        $record = $this->db->table($this->table)->where('email', $email)->first();
        if ($record === null || !\hash_equals((string) $record['token'], \hash('sha256', $token))) {
            return self::INVALID_TOKEN;
        }

        // 🔒 created_at به UTC ذخیره می‌شود → پارس در UTC (نه timezone محلی)
        try {
            $createdAt = (new \DateTimeImmutable((string) $record['created_at'], new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception) {
            $createdAt = 0;
        }
        if (Clock::timestamp() - $createdAt > $this->expireSeconds) {
            $this->db->table($this->table)->where('email', $email)->delete();

            return self::EXPIRED_TOKEN;
        }

        $user = $this->provider->retrieveByCredentials(['email' => $email]);
        if ($user === null) {
            return self::INVALID_TOKEN;
        }

        $callback($user, $newPassword);

        // 🔒 توکن یک‌بارمصرف
        $this->db->table($this->table)->where('email', $email)->delete();

        $this->events?->dispatch('auth.password.reset', ['email_masked' => $this->mask($email)]);

        return self::PASSWORD_RESET;
    }

    private function mask(string $email): string
    {
        [$local, $domain] = \array_pad(\explode('@', $email, 2), 2, '');

        return \substr($local, 0, 2) . '***@' . $domain;
    }
}
