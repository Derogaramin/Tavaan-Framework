<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Cookie;

use Tavaan\Engine\Crypt\Crypt;

/**
 * تَوان | Tavaan — Cookie
 * CookieJar — ساخت/صف‌سازی کوکی‌ها برای ارسال در پاسخ.
 *
 * @api 1.0
 * @see docs/03-engines/cookie.md
 */
final class CookieJar
{
    /** @var array<string, Cookie> */
    private array $queued = [];

    public function __construct(
        private readonly ?Crypt $crypt = null,
        private readonly bool $defaultSecure = false,
        private readonly ?string $defaultDomain = null,
        private readonly string $defaultSameSite = 'Lax',
    ) {
    }

    public function make(
        string $name,
        string $value,
        int $minutes = 60,
        string $path = '/',
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = '',
        bool $encrypted = false,
    ): Cookie {
        return new Cookie(
            name: $name,
            value: $value,
            expire: $minutes <= 0 ? 0 : \time() + $minutes * 60,
            path: $path,
            domain: $domain ?? $this->defaultDomain,
            secure: $secure ?? $this->defaultSecure,
            httpOnly: $httpOnly,
            sameSite: $sameSite !== '' ? $sameSite : $this->defaultSameSite,
            encrypted: $encrypted,
        );
    }

    public function forever(string $name, string $value, bool $encrypted = false): Cookie
    {
        return $this->make($name, $value, minutes: 2628000, encrypted: $encrypted);   // ~۵ سال
    }

    /** کوکی منقضی‌شده (حذف). */
    public function forget(string $name, string $path = '/', ?string $domain = null): Cookie
    {
        return $this->make($name, '', minutes: -1440, path: $path, domain: $domain);
    }

    public function queue(Cookie $cookie): void
    {
        $this->queued[$cookie->name] = $cookie;
    }

    public function unqueue(string $name): void
    {
        unset($this->queued[$name]);
    }

    public function hasQueued(string $name): bool
    {
        return isset($this->queued[$name]);
    }

    /** @return list<Cookie> */
    public function queued(): array
    {
        return \array_values($this->queued);
    }

    public function flushQueued(): void
    {
        $this->queued = [];
    }

    /**
     * خواندن کوکی از درخواست (با decrypt در صورت نیاز).
     * 🔒 کوکی دستکاری‌شده → null (بدون crash).
     */
    public function get(string $name, ?string $default = null, ?array $requestCookies = null): ?string
    {
        $value = $requestCookies[$name] ?? null;
        if (!\is_string($value)) {
            return $default;
        }

        if ($this->crypt !== null && \str_starts_with($value, 'v')) {
            try {
                return $this->crypt->decrypt($value, 'cookie:' . $name);
            } catch (\Throwable) {
                return null;   // 🔒 tamper → بی‌صدا null
            }
        }

        return $value;
    }
}
