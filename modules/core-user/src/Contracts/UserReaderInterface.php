<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserReaderInterface — قرارداد عمومی خواندن کاربر (DOC-090 §6).
 */

namespace Modules\CoreUser\Contracts;

use Tavaan\Core\Contracts\Notification\Notifiable;

/**
 * ارائه‌دهنده: tavaan.core-user • مصرف‌کنندگان: همهٔ ماژول‌ها (blog نویسندهٔ
 * مقاله، shop مشتری، crm کاربر و …).
 *
 * @api 1.0
 */
interface UserReaderInterface
{
    public function findById(int|string $id): ?UserSnapshot;

    public function findByEmail(string $email): ?UserSnapshot;

    /**
     * نگاشت دسته‌ای شناسه‌ها → snapshot (بدون N+1).
     *
     * @param list<int|string> $ids
     *
     * @return array<string, UserSnapshot> کلید = شناسهٔ رشته‌ای
     */
    public function findByIds(array $ids): array;

    /**
     * نام نمایشی یا fallback — برای ویوها.
     */
    public function displayNameOf(int|string $id, string $fallback = 'کاربر'): string;

    /**
     * موجودیت کاربر به‌عنوان Notifiable (برای موتور notification) —
     * تنها نقطهٔ عبور Notifiable از مرز ماژول (DOC-090 §6).
     */
    public function findNotifiable(int|string $id): ?Notifiable;
}
