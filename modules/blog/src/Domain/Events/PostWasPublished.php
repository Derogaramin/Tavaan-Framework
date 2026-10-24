<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostWasPublished — رویداد انتشار مقاله (DOC-091 §5: blog.post.published).
 */

namespace Modules\Blog\Domain\Events;

use Modules\Content\Domain\Enums\ContentStatus;

/**
 * مصرف‌کنندگان: search (ایندکس)، notification (دنبال‌کنندگان)، cache invalidate.
 *
 * @api 1.0
 */
final readonly class PostWasPublished
{
    public function __construct(
        public string $postId,
        public string $slug,
        public string $title,
        public int $authorId,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {
    }

    public function eventName(): string
    {
        return 'blog.post.published';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'slug' => $this->slug,
            'title' => $this->title,
            'author_id' => $this->authorId,
            'status' => ContentStatus::Published->value,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s.v'),
        ];
    }
}
