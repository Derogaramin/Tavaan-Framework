<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleWasPublished — خبر منتشر شد (DOC-092 §3/§5).
 */

namespace Modules\News\Domain\Events;

/**
 * مصرف‌کنندگان: search (ایندکس)، cache (invalidate)، notification (نویسنده).
 *
 * @api 1.0
 */
final readonly class ArticleWasPublished
{
    public function __construct(
        public string $articleId,
        public string $slug,
        public string $title,
        public int $authorId,
        public int $editorId,
        public ?\DateTimeImmutable $publishedAt = null,
        public bool $isBreaking = false,
    ) {
    }

    public function eventName(): string
    {
        return 'news.article.published';
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
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s.v'),
            'is_breaking' => $this->isBreaking,
        ];
    }
}
