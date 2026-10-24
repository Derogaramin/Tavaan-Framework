<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserSnapshot — DTO عمومی کاربر برای مصرف ماژول‌های دیگر.
 */

namespace Modules\CoreUser\Contracts;

/**
 * UserSnapshot — نمای خواندنی و پایدار از کاربر (بدون رمز/توکن).
 *
 * 🔒 ماژول‌های مصرف‌کننده به موجودیت داخلی User وابسته نمی‌شوند —
 * فقط این DTO از مرز ماژول عبور می‌کند (DOC-022: Contracts عمومی است).
 *
 * @api 1.0
 */
final readonly class UserSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $mobile = null,
        public ?string $avatar = null,
        public string $status = 'active',
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            email: (string) ($row['email'] ?? ''),
            mobile: isset($row['mobile']) ? (string) $row['mobile'] : null,
            avatar: isset($row['avatar']) ? (string) $row['avatar'] : null,
            status: (string) ($row['status'] ?? 'active'),
        );
    }

    public function displayName(): string
    {
        return $this->name !== '' ? $this->name : $this->email;
    }
}
