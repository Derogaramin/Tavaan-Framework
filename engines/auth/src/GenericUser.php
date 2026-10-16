<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth;

use Tavaan\Core\Contracts\Auth\Authenticatable;

/**
 * تَوان | Tavaan — Auth
 * GenericUser — کاربر عمومی آرایه‌محور (بدون وابستگی به ORM — DOC-056).
 *
 * وقتی provider.model تنظیم نشده باشد، سطرها به GenericUser نگاشت می‌شوند.
 * مدل‌های ORM نیز می‌توانند Authenticatable را پیاده کنند (مانند TSecureUser).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class GenericUser implements Authenticatable
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes)
    {
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function getAuthIdentifier(): int|string
    {
        $id = $this->attributes[$this->getAuthIdentifierName()] ?? null;

        return \is_int($id) ? $id : (string) $id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? '');
    }

    public function getRememberToken(): ?string
    {
        return isset($this->attributes['remember_token']) ? (string) $this->attributes['remember_token'] : null;
    }

    public function setRememberToken(string $value): void
    {
        $this->attributes['remember_token'] = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /** آیا حساب مسدود است (ستون اختیاری is_banned/status). */
    public function isBanned(): bool
    {
        if (isset($this->attributes['is_banned'])) {
            return (bool) $this->attributes['is_banned'];
        }

        return ($this->attributes['status'] ?? 'active') === 'banned';
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
