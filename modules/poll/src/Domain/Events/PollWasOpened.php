<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollWasOpened — رویداد باز شدن نظرسنجی.
 */

namespace Modules\Poll\Domain\Events;

/**
 * رویداد `poll.opened` — پس از باز شدن نظرسنجی برای رأی‌گیری.
 *
 * @api 1.0
 */
final readonly class PollWasOpened
{
    public function __construct(
        public string $pollId,
        public string $slug,
        public string $question,
    ) {
    }

    public function eventName(): string
    {
        return 'poll.opened';
    }
}
