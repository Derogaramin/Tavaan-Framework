<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollWasClosed — رویداد بسته شدن نظرسنجی.
 */

namespace Modules\Poll\Domain\Events;

/**
 * رویداد `poll.closed` — پس از بسته شدن رأی‌گیری.
 *
 * @api 1.0
 */
final readonly class PollWasClosed
{
    public function __construct(
        public string $pollId,
        public string $slug,
        public string $question,
        public int $totalVotes,
    ) {
    }

    public function eventName(): string
    {
        return 'poll.closed';
    }
}
