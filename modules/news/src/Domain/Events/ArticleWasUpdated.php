<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleWasUpdated — خبر ویرایش شد (cache invalidate + بازایندکس).
 */

namespace Modules\News\Domain\Events;

use Modules\News\Domain\Enums\ArticleStatus;

/**
 * @api 1.0
 */
final readonly class ArticleWasUpdated
{
    public function __construct(
        public string $articleId,
        public string $slug,
        public string $title,
        public int $authorId,
        public ArticleStatus $status = ArticleStatus::Draft,
    ) {
    }

    public function eventName(): string
    {
        return 'news.article.updated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'article_id' => $this->articleId,
            'slug' => $this->slug,
            'title' => $this->title,
            'author_id' => $this->authorId,
            'status' => $this->status->value,
        ];
    }
}
