<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserWriterInterface — پورت نوشتن کاربر (ثبت‌نام/پروفایل/مدیریت).
 */

namespace Modules\CoreUser\Contracts;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;

/**
 * پورت نوشتن کاربران — مکمل UserReaderInterface (DOC-090 §6).
 *
 * ارائه‌دهنده: tavaan.core-user • مصرف‌کننده: UseCaseهای خود ماژول
 * (Registration/Profile/Administration) و ابزارهای نصب.
 *
 * @api 1.0
 */
interface UserWriterInterface
{
    /** ساخت کاربر با رمز هش‌شده. */
    public function create(
        string $name,
        string $email,
        string $plainPassword,
        ?string $mobile = null,
        UserStatus $status = UserStatus::Active,
    ): User;

    /**
     * به‌روزرسانی پروفایل (فقط کلیدهای ارائه‌شده).
     *
     * @param array{name?: string, mobile?: ?string, bio?: ?string, avatar?: ?string} $fields
     */
    public function updateProfile(int|string $id, array $fields): bool;

    public function updatePassword(int|string $id, string $plainPassword): bool;

    public function setStatus(int|string $id, UserStatus $status): bool;

    /** حذف کاربر (بدون soft-delete — جدول users رکورد محور است). */
    public function delete(int|string $id): bool;
}
