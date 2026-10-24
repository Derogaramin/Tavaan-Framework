<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostWasFlagged — پست گزارش شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.post.flagged` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class PostWasFlagged
{
    public function __construct(
        public int $flagId,
        public string $postId,
        public string $threadId,
        public int $postAuthorId,
        public ?int $reporterId,
        public ?string $reason,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.post.flagged';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'flag_id' => $this->flagId,
            'post_id' => $this->postId,
            'thread_id' => $this->threadId,
            'post_author_id' => $this->postAuthorId,
            'reporter_id' => $this->reporterId,
            'reason' => $this->reason,
        ];
    }
}
