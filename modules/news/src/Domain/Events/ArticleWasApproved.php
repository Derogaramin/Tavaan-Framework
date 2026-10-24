<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleWasApproved — ویراستار خبر را تأیید کرد (DOC-092 §3).
 */

namespace Modules\News\Domain\Events;

/**
 * @api 1.0
 */
final readonly class ArticleWasApproved
{
    public function __construct(
        public string $articleId,
        public string $slug,
        public string $title,
        public int $authorId,
        public int $editorId,
        public ?\DateTimeImmutable $scheduledAt = null,
    ) {
    }

    public function eventName(): string
    {
        return 'news.article.approved';
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
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s.v'),
        ];
    }
}
