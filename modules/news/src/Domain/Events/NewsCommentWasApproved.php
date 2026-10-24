<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsCommentWasApproved — نظر روی خبر تأیید شد.
 */

namespace Modules\News\Domain\Events;

/**
 * @api 1.0
 */
final readonly class NewsCommentWasApproved
{
    public function __construct(
        public string $commentId,
        public string $articleId,
        public string $articleSlug,
        public string $commenterName,
        public string $commenterEmail,
    ) {
    }

    public function eventName(): string
    {
        return 'news.comment.approved';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'comment_id' => $this->commentId,
            'article_id' => $this->articleId,
            'article_slug' => $this->articleSlug,
            'commenter_name' => $this->commenterName,
            'commenter_email' => $this->commenterEmail,
        ];
    }
}
