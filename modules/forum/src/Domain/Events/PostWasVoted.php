<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostWasVoted — رأی ثبت/تغییر/حذف شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.post.voted` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class PostWasVoted
{
    public function __construct(
        public string $postId,
        public string $threadId,
        public int $postAuthorId,
        public int $voterId,
        public int $value,
        public int $oldValue,
        public int $score,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.post.voted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'thread_id' => $this->threadId,
            'post_author_id' => $this->postAuthorId,
            'voter_id' => $this->voterId,
            'value' => $this->value,
            'old_value' => $this->oldValue,
            'score' => $this->score,
        ];
    }
}
