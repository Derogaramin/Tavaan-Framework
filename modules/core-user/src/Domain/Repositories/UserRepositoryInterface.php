<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserRepositoryInterface — پورت داخلی ماندگاری کاربر (خواندن + نوشتن).
 */

namespace Modules\CoreUser\Domain\Repositories;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Contracts\UserWriterInterface;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;

/**
 * پورت کامل مخزن کاربر برای UseCaseهای داخل ماژول.
 *
 * ترکیب UserReaderInterface (قرارداد عمومی بین‌ماژولی — DOC-090 §6) و
 * UserWriterInterface (نوشتن) + خواندن‌های مدیریتی (صفحه‌بندی/آمار).
 *
 * @api 1.0
 */
interface UserRepositoryInterface extends UserReaderInterface, UserWriterInterface
{
    /** موجودیت کامل کاربر (داخل ماژول). */
    public function findUserById(int|string $id): ?User;

    /** موجودیت کامل کاربر از ایمیل (داخل ماژول). */
    public function findUserByEmail(string $email): ?User;

    /**
     * صفحه‌بندی کاربران با جست‌وجو و فیلتر وضعیت (پنل مدیریت).
     *
     * @return array{rows: list<User>, total: int}
     */
    public function paginate(string $search = '', ?UserStatus $status = null, int $page = 1, int $perPage = 20): array;

    public function countAll(): int;

    public function countByStatus(UserStatus $status): int;

    /**
     * تازه‌ترین کاربران (ویجت داشبورد).
     *
     * @return list<User>
     */
    public function latest(int $limit = 5): array;

    /** تعداد مدیران فعال — برای محافظت «آخرین مدیر» (از راه نقش‌های RBAC). */
    public function countActiveAdmins(): int;
}
