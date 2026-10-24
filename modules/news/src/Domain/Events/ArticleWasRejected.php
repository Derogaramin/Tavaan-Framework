<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleWasRejected — ویراستار خبر را رد کرد (بازگشت به نویسنده).
 */

namespace Modules\News\Domain\Events;

/**
 * @api 1.0
 */
final readonly class ArticleWasRejected
{
    public function __construct(
        public string $articleId,
        public string $slug,
        public string $title,
        public int $authorId,
        public int $editorId,
        public string $reason = '',
    ) {
    }

    public function eventName(): string
    {
        return 'news.article.rejected';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'article_id' => $this->articleId,
            'slug' => $this->slug,
            'title' => $this->title,
            'author_id' => $this->authorId,
            'editor_id' => $this->editorId,
            'reason' => $this->reason,
        ];
    }
}
