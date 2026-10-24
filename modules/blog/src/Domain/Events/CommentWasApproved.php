<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CommentWasApproved — رویداد تأیید نظر (DOC-091 §5: blog.comment.approved).
 */

namespace Modules\Blog\Domain\Events;

/**
 * مصرف‌کننده: notification (به نویسندهٔ نظر).
 *
 * @api 1.0
 */
final readonly class CommentWasApproved
{
    public function __construct(
        public string $commentId,
        public string $postId,
        public string $postSlug,
        public string $commenterName,
        public string $commenterEmail,
    ) {
    }

    public function eventName(): string
    {
        return 'blog.comment.approved';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'comment_id' => $this->commentId,
            'post_id' => $this->postId,
            'post_slug' => $this->postSlug,
            'commenter_name' => $this->commenterName,
            'commenter_email' => $this->commenterEmail,
        ];
    }
}
