<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ProfileRepositoryInterface — پورت پروفایل انجمن (username/karma/ban).
 */

namespace Modules\Forum\Domain\Repositories;

use Modules\Forum\Domain\Entities\ForumProfile;

/**
 * @api 1.0
 */
interface ProfileRepositoryInterface
{
    public function save(ForumProfile $profile): void;

    public function findByUserId(int $userId): ?ForumProfile;

    public function findByUsername(string $username): ?ForumProfile;

    public function usernameExists(string $username, ?int $exceptUserId = null): bool;

    /**
     * @param list<int> $userIds
     *
     * @return array<string, ForumProfile> کلید = شناسهٔ رشته‌ای کاربر
     */
    public function findByUserIds(array $userIds): array;

    /** فهرست اعضا — به‌ترتیب karma. */
    public function topByKarma(int $limit = 10): array;

    /** تعداد کل پروفایل‌ها (آمار صفحهٔ نخست). */
    public function countAll(): int;

    /** delta مستقیم karma (بدون load/save کامل — مسیر رأی). */
    public function incrementKarma(int $userId, int $delta): void;

    public function delete(int $userId): void;
}
