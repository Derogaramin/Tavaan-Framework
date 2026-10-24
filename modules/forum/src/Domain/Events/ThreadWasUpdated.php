<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadWasUpdated — موضوع ویرایش/جابجا/قفل/سنجاق شد (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Events;

/**
 * رویداد رشته‌ای `forum.thread.updated` — payload از راه Event::dispatch(name, toPayload()).
 *
 * @api 1.0
 */
final readonly class ThreadWasUpdated
{
    public function __construct(
        public string $threadId,
        public string $slug,
        public string $title,
        public string $change,
        public int $actorId,
    ) {
    }

    public function eventName(): string
    {
        return 'forum.thread.updated';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'thread_id' => $this->threadId,
            'slug' => $this->slug,
            'title' => $this->title,
            'change' => $this->change,
            'actor_id' => $this->actorId,
        ];
    }
}
