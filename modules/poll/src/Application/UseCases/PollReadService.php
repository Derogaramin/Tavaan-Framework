<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollReadService — مسیر خواندن نظرسنجی‌ها و نتایج.
 */

namespace Modules\Poll\Application\UseCases;

use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Modules\Poll\Domain\Services\VoteTally;

/**
 * PollReadService — UseCase خواندن (عمومی + نتایج).
 *
 * - دسترسی عمومی فقط به نظرسنجی غیرtrash با وضعیت public (open/closed)
 * - نتایج طبق پیکربندی `poll.results` (after_vote|always|closed)
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class PollReadService
{
    /** @param array<string, mixed> $config کلید config('poll') */
    public function __construct(
        private readonly PollRepositoryInterface $polls,
        private readonly VoteRepositoryInterface $votes,
        private readonly VoteTally $tally,
        private readonly array $config = [],
    ) {
    }

    /** نظرسنجی قابل نمایش عمومی از slug — در غیر این صورت null (کنترلر 404). */
    public function publicBySlug(string $slug): ?Poll
    {
        $poll = $this->polls->findBySlug($slug);

        return $poll !== null && $poll->status()->isPublic() ? $poll : null;
    }

    /** نظرسنجی از slug برای پنل (بدون محدودیت وضعیت). */
    public function bySlug(string $slug): ?Poll
    {
        return $this->polls->findBySlug($slug);
    }

    /**
     * فهرست عمومی نظرسنجی‌های باز.
     *
     * @return list<Poll>
     */
    public function listOpen(int $limit = 10): array
    {
        return $this->polls->listOpen($limit);
    }

    /**
     * نتایج جمع‌بندی‌شده.
     *
     * @return array{rows: list<array{index: int, label: string, count: int, percent: float}>, total: int, leader: ?int}
     */
    public function results(Poll $poll): array
    {
        $counts = $this->votes->countsByPoll($poll->id());
        $rows = $this->tally->tally($poll->options(), $counts);

        return [
            'rows' => $rows,
            'total' => $this->tally->total($counts),
            'leader' => $this->tally->leader($rows),
        ];
    }

    /** شمار کل رأی‌های یک نظرسنجی. */
    public function totalVotes(Poll $poll): int
    {
        return $this->votes->countByPoll($poll->id());
    }

    /** آیا نتایج به بازدیدکننده نشان داده شود؟ (طبق poll.results) */
    public function shouldShowResults(Poll $poll, bool $hasVoted): bool
    {
        $mode = (string) ($this->config['results'] ?? 'after_vote');

        return match ($mode) {
            'always' => true,
            'closed' => !$poll->isVotable(),
            default => $hasVoted || !$poll->isVotable(),   // after_vote
        };
    }
}
