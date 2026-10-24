<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentStatusChanged — رویداد عمومی تغییر وضعیت نظر (Schema v1).
 */

namespace Modules\Comment\Domain\Events;

use Modules\Comment\Domain\Enums\CommentStatus;

/**
 * رویداد `comment.status.changed` — توسط ماژول مصرف‌کننده dispatch می‌شود
 * (مثلاً blog با رویدادهای `blog.comment.submitted/approved` — DOC-091 §5).
 *
 * @api 1.0
 */
final class CommentStatusChanged
{
    public function __construct(
        public readonly string $commentId,
        public readonly string $commentableType,
        public readonly string $commentableId,
        public readonly CommentStatus $status,
        public readonly ?CommentStatus $previousStatus = null,
    ) {
    }

    public function eventName(): string
    {
        return 'comment.status.changed';
    }
}
