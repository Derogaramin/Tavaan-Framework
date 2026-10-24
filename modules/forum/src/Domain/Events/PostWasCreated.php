<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostWasCreated — پست/پاسخ جدید ثبت شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.post.created` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class PostWasCreated
{
    public function __construct(
        public string $postId,
        public string $threadId,
        public string $threadSlug,
        public int $authorId,
        public ?string $parentId,
        public string $body,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.post.created';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'thread_id' => $this->threadId,
            'thread_slug' => $this->threadSlug,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'body' => $this->body,
        ];
    }
}
