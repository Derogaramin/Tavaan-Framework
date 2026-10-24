<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentStoreInterface — پورت ذخیره‌سازی نظرات (Hexagonal).
 */

namespace Modules\Comment\Contracts;

use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;

/**
 * پورت ماندگاری نظرات — پیاده‌سازی توسط ماژول مصرف‌کننده روی جدول
 * با prefix خودش (مثلاً `blog_comments` در ماژول blog — DOC-091 §2).
 *
 * 🔒 بدون Join/FK بین‌ماژولی (DoD ماژول — DOC-113 §3).
 *
 * @api 1.0
 */
interface CommentStoreInterface
{
    public function save(Comment $comment): void;

    public function findById(string $id): ?Comment;

    /**
     * نظرات یک subject — به‌ترتیب زمانی صعودی.
     *
     * @return list<Comment>
     */
    public function forSubject(string $commentableType, string $commentableId, ?CommentStatus $status = null): array;

    public function updateStatus(string $id, CommentStatus $status): void;

    public function delete(string $id): void;

    public function countByStatus(CommentStatus $status): int;

    /** آیا این ایمیل پیش‌تر نظر تأییدشده داشته است؟ (auto-approve trusted) */
    public function authorHasApprovedHistory(string $email): bool;
}
