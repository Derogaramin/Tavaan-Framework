<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsCommentWasSubmitted — نظر روی خبر ثبت شد.
 */

namespace Modules\News\Domain\Events;

/**
 * @api 1.0
 */
final readonly class NewsCommentWasSubmitted
{
    public function __construct(
        public string $commentId,
        public string $articleId,
        public string $articleSlug,
        public string $authorName,
        public string $authorEmail,
        public string $status,
    ) {
    }

    public function eventName(): string
    {
        return 'news.comment.submitted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'comment_id' => $this->commentId,
            'article_id' => $this->articleId,
            'article_slug' => $this->articleSlug,
            'author_name' => $this->authorName,
            'author_email' => $this->authorEmail,
            'status' => $this->status,
        ];
    }
}
