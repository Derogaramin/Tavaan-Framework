<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * Comment — موجودیت تغییرناپذیر نظر (پلیمورفیک روی هر subject).
 */

namespace Modules\Comment\Domain\Entities;

use Modules\Comment\Domain\Enums\CommentStatus;

/**
 * Comment — موجودیت دامنه (immutable + wither).
 *
 * بدنه به‌صورت متن خام ذخیره می‌شود؛ escape در لایهٔ نمایش (View) انجام
 * می‌گیرد (AC-BLOG-03). شناسه ULID (CHAR(26)) مطابق DOC-091 §2.
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class Comment
{
    public function __construct(
        public readonly string $id,
        public readonly string $commentableType,
        public readonly string $commentableId,
        public readonly string $authorName,
        public readonly string $authorEmail,
        public readonly string $body,
        public readonly CommentStatus $status = CommentStatus::Pending,
        public readonly ?string $parentId = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?string $userId = null,
    ) {
    }

    /** ساخت نظر جدید (شناسهٔ ULID خودکار). */
    public static function submit(
        string $commentableType,
        string $commentableId,
        string $authorName,
        string $authorEmail,
        string $body,
        CommentStatus $status = CommentStatus::Pending,
        ?string $parentId = null,
        ?string $userId = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            commentableType: $commentableType,
            commentableId: $commentableId,
            authorName: $authorName,
            authorEmail: $authorEmail,
            body: $body,
            status: $status,
            parentId: $parentId,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            userId: $userId,
        );
    }

    public function withStatus(CommentStatus $status): self
    {
        return new self(
            id: $this->id,
            commentableType: $this->commentableType,
            commentableId: $this->commentableId,
            authorName: $this->authorName,
            authorEmail: $this->authorEmail,
            body: $this->body,
            status: $status,
            parentId: $this->parentId,
            createdAt: $this->createdAt,
            userId: $this->userId,
        );
    }

    /** آیا پاسخِ نظر دیگری است؟ */
    public function isReply(): bool
    {
        return $this->parentId !== null && $this->parentId !== '';
    }

    /** تعداد لینک‌های بدنه (heuristic اسپم). */
    public function linkCount(): int
    {
        return \preg_match_all('#(?:https?://|www\.)#i', $this->body);
    }
}
