<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostWasHidden — پست پنهان شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.post.hidden` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class PostWasHidden
{
    public function __construct(
        public string $postId,
        public string $threadId,
        public int $authorId,
        public int $moderatorId,
        public ?string $reason,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.post.hidden';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'thread_id' => $this->threadId,
            'author_id' => $this->authorId,
            'moderator_id' => $this->moderatorId,
            'reason' => $this->reason,
        ];
    }
}
