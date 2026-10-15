<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Handlers;

use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Session\SessionHandlerInterface;

/**
 * درایور کوکی (stateless) — دادهٔ نشست رمزنگاری‌شده در خود کوکی.
 *
 * 🔒 همیشه با Crypt (AES-256-GCM) — کوکی دستکاری‌شده → دادهٔ خالی.
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class CookieHandler implements SessionHandlerInterface
{
    /** @var array<string, array<string, mixed>> بافر write برای ارسال در terminate */
    private array $buffer = [];

    public function __construct(private readonly Crypt $crypt, private readonly int $maxBytes = 3072)
    {
    }

    public function read(string $id): array
    {
        $raw = $this->buffer[$id] ?? null;
        if (!\is_string($raw)) {
            return [];
        }

        try {
            $data = $this->crypt->decryptJson($raw, 'session:' . $id);
        } catch (\Throwable) {
            return [];   // 🔒 tamper → خالی
        }

        return $data;
    }

    public function write(string $id, array $data): bool
    {
        $encrypted = $this->crypt->encryptJson($data, 'session:' . $id);
        if (\strlen($encrypted) > $this->maxBytes) {
            // کوکی خیلی بزرگ — در middleware به فایل fallback یا هشدار لاگ
            return false;
        }

        $this->buffer[$id] = $encrypted;

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->buffer[$id]);

        return true;
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        return 0;
    }

    /** مقدار رمزنگاری‌شده برای Set-Cookie (توسط middleware خوانده می‌شود). */
    public function cookieValue(string $id): ?string
    {
        return \is_string($this->buffer[$id] ?? null) ? $this->buffer[$id] : null;
    }

    /** middleware کوکی درخواست را برای read() بارگذاری می‌کند. */
    public function seedFromCookie(string $id, string $encrypted): void
    {
        $this->buffer[$id] = $encrypted;
    }
}
