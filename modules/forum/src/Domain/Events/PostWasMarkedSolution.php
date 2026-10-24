<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostWasMarkedSolution — پاسخ برگزیده شد/برداشته شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.post.solved` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class PostWasMarkedSolution
{
    public function __construct(
        public string $postId,
        public string $threadId,
        public int $postAuthorId,
        public int $threadAuthorId,
        public bool $marked,
        public int $actorId,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.post.solved';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'thread_id' => $this->threadId,
            'post_author_id' => $this->postAuthorId,
            'thread_author_id' => $this->threadAuthorId,
            'marked' => $this->marked,
            'actor_id' => $this->actorId,
        ];
    }
}
