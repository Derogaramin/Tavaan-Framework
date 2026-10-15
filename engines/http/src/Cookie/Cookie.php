<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Cookie;

use Tavaan\Core\Contracts\Http\CookieInterface;

/**
 * تَوان | Tavaan — Cookie
 * Cookie — value object کوکی با پیش‌فرض‌های امن (HttpOnly=true, SameSite=Lax).
 *
 * @api 1.0
 * @see docs/03-engines/cookie.md
 */
final readonly class Cookie implements CookieInterface
{
    public function __construct(
        public string $name,
        public string $value = '',
        public int $expire = 0,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = false,
        public bool $httpOnly = true,          // 🔒 پیش‌فرض HttpOnly
        public string $sameSite = 'Lax',      // 🔒 پیش‌فرض Lax
        public bool $encrypted = false,
    ) {
        if (\preg_match('/[\r\n\0]/', $name . $value) === 1) {
            throw new \InvalidArgumentException('Cookie name/value must not contain CRLF.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function withValue(string $value): self
    {
        return new self($this->name, $value, $this->expire, $this->path, $this->domain, $this->secure, $this->httpOnly, $this->sameSite, $this->encrypted);
    }

    /** @return array<string, mixed> قالب setcookie() */
    public function toArray(): array
    {
        return [
            'expires' => $this->expire,
            'path' => $this->path,
            'domain' => $this->domain ?? '',
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite,
        ];
    }
}
