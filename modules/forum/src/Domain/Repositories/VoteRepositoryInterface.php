<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * VoteRepositoryInterface — پورت ماندگاری رأی (DOC-094 §2: forum_votes).
 */

namespace Modules\Forum\Domain\Repositories;

/**
 * رأی یکتا برای هر (post_id, user_id) — تغییر رأی = upsert.
 *
 * @api 1.0
 */
interface VoteRepositoryInterface
{
    /** رأی فعلی کاربر روی پست (+1/-1) یا null. */
    public function currentVote(string $postId, int $userId): ?int;

    /**
     * ثبت/تغییر رأی.
     *
     * @return int|null رأی قبلی (برای delta karma)
     */
    public function cast(string $postId, int $userId, int $value): ?int;

    /**
     * حذف رأی (toggle).
     *
     * @return int|null رأی قبلی
     */
    public function remove(string $postId, int $userId): ?int;

    /** مجموع value رأی‌های یک پست (منبع حقیقت vote_score). */
    public function scoreOf(string $postId): int;

    public function deleteForPost(string $postId): void;
}
