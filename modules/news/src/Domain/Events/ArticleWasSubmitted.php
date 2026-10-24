<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleWasSubmitted — نویسنده خبر را برای بازبینی فرستاد (DOC-092 §3).
 */

namespace Modules\News\Domain\Events;

/**
 * @api 1.0
 */
final readonly class ArticleWasSubmitted
{
    public function __construct(
        public string $articleId,
        public string $slug,
        public string $title,
        public int $authorId,
    ) {
    }

    public function eventName(): string
    {
        return 'news.article.submitted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'article_id' => $this->articleId,
            'slug' => $this->slug,
            'title' => $this->title,
            'author_id' => $this->authorId,
        ];
    }
}
