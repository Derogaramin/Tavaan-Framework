<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostWasDeleted — پست حذف شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.post.deleted` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class PostWasDeleted
{
    public function __construct(
        public string $postId,
        public string $threadId,
        public int $authorId,
        public int $actorId,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.post.deleted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'thread_id' => $this->threadId,
            'author_id' => $this->authorId,
            'actor_id' => $this->actorId,
        ];
    }
}
