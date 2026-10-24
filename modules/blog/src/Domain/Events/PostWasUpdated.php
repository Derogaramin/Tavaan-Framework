<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostWasUpdated — رویداد ویرایش مقاله (DOC-091 §5: blog.post.updated).
 */

namespace Modules\Blog\Domain\Events;

use Modules\Content\Domain\Enums\ContentStatus;

/**
 * @api 1.0
 */
final readonly class PostWasUpdated
{
    public function __construct(
        public string $postId,
        public string $slug,
        public string $title,
        public int $authorId,
        public ContentStatus $status = ContentStatus::Draft,
        public ?\DateTimeImmutable $at = null,
    ) {
    }

    public function eventName(): string
    {
        return 'blog.post.updated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'slug' => $this->slug,
            'title' => $this->title,
            'author_id' => $this->authorId,
            'status' => $this->status->value,
            'at' => $this->at?->format('Y-m-d H:i:s.v'),
        ];
    }
}
