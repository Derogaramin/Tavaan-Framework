<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CommentWasSubmitted — رویداد ثبت نظر (DOC-091 §5: blog.comment.submitted).
 */

namespace Modules\Blog\Domain\Events;

/**
 * مصرف‌کنندگان: moderation queue، notification (به نویسندهٔ مقاله).
 *
 * @api 1.0
 */
final readonly class CommentWasSubmitted
{
    public function __construct(
        public string $commentId,
        public string $postId,
        public string $postSlug,
        public string $authorName,
        public string $authorEmail,
        public string $status,
    ) {
    }

    public function eventName(): string
    {
        return 'blog.comment.submitted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'comment_id' => $this->commentId,
            'post_id' => $this->postId,
            'post_slug' => $this->postSlug,
            'author_name' => $this->authorName,
            'author_email' => $this->authorEmail,
            'status' => $this->status,
        ];
    }
}
