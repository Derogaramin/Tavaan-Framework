<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * BreakingNewsWasPublished — خبر فوری منتشر/علامت‌گذاری شد (DOC-092 §6).
 */

namespace Modules\News\Domain\Events;

/**
 * مصرف‌کنندگان: realtime (broadcast کانال `news`)، cache (invalidate breaking).
 *
 * @api 1.0
 */
final readonly class BreakingNewsWasPublished
{
    public function __construct(
        public string $articleId,
        public string $slug,
        public string $title,
        public string $lead,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {
    }

    public function eventName(): string
    {
        return 'news.breaking.published';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'article_id' => $this->articleId,
            'slug' => $this->slug,
            'title' => $this->title,
            'lead' => $this->lead,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s.v'),
        ];
    }
}
