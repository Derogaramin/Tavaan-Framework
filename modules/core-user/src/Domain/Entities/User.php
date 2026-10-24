<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * User — موجودیت کاربر (Authenticatable + Notifiable).
 */

namespace Modules\CoreUser\Domain\Entities;

use Modules\CoreUser\Domain\Enums\UserStatus;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Notification\Notifiable;

/**
 * User — موجودیت تغییرناپذیر کاربر روی جدول `users`.
 *
 * - `Authenticatable`: ورود با موتور auth (DatabaseUserProvider/GenericUser
 *   هم روی همان جدول کار می‌کند؛ این موجودیت برای نمایش/دامنه است).
 * - `Notifiable`: روت کانال‌ها (mail→email، sms→mobile، database→id).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class User implements Authenticatable, Notifiable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly UserStatus $status = UserStatus::Active,
        public readonly ?string $mobile = null,
        public readonly ?string $bio = null,
        public readonly ?string $avatar = null,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?string $lastLoginAt = null,
        public readonly ?string $createdAt = null,
        private readonly string $password = '',
        private string $rememberToken = '',
    ) {
    }

    /**
     * ساخت از ردیف دیتابیس.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            email: (string) ($row['email'] ?? ''),
            status: UserStatus::tryFrom((string) ($row['status'] ?? 'active')) ?? UserStatus::Active,
            mobile: isset($row['mobile']) ? (string) $row['mobile'] : null,
            bio: isset($row['bio']) ? (string) $row['bio'] : null,
            avatar: isset($row['avatar']) ? (string) $row['avatar'] : null,
            emailVerifiedAt: isset($row['email_verified_at']) ? (string) $row['email_verified_at'] : null,
            lastLoginAt: isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            password: (string) ($row['password'] ?? ''),
            rememberToken: (string) ($row['remember_token'] ?? ''),
        );
    }

    // ─── Authenticatable ──────────────────────────────────────────────────

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken !== '' ? $this->rememberToken : null;
    }

    public function setRememberToken(string $value): void
    {
        $this->rememberToken = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // ─── Notifiable ───────────────────────────────────────────────────────

    public function routeNotificationFor(string $driver, ?object $notification = null): mixed
    {
        return match ($driver) {
            'mail' => $this->email,
            'sms' => $this->mobile,
            'database' => $this->id,
            default => null,
        };
    }

    public function getNotificationKey(): int|string
    {
        return $this->id;
    }

    // ─── دامنه ────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status->canLogin();
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->emailVerifiedAt !== null && $this->emailVerifiedAt !== '';
    }

    /** نام نمایشی برای UI (بدون ایمیل). */
    public function displayName(): string
    {
        return $this->name !== '' ? $this->name : $this->email;
    }
}
