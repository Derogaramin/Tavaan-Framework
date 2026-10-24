<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadWasCreated — موضوع جدید ساخته شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.thread.created` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class ThreadWasCreated
{
    public function __construct(
        public string $threadId,
        public int $forumId,
        public string $title,
        public string $slug,
        public int $authorId,
        public string $firstPostId,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.thread.created';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'thread_id' => $this->threadId,
            'forum_id' => $this->forumId,
            'title' => $this->title,
            'slug' => $this->slug,
            'author_id' => $this->authorId,
            'first_post_id' => $this->firstPostId,
        ];
    }
}
