<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ReputationCalculator — قواعد Karma (DOC-094 §6: Reputation/Karma).
 */

namespace Modules\Forum\Domain\Services;

/**
 * ReputationCalculator — دامنهٔ خالص: deltaهای karma از config.
 *
 * قواعد پیش‌فرض:
 * - دریافت +1: `upvote_received` (۲)
 * - دریافت -1: `downvote_received` (-۱)
 * - برگزیده‌شدن پاسخ: `solution_author` (۱۵)
 * - ساخت موضوع/پست: `thread_created`/`post_created` (۱)
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ReputationCalculator
{
    /**
     * @param array{enabled?: bool, upvote_received?: int, downvote_received?: int,
     *              solution_author?: int, thread_created?: int, post_created?: int} $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /** delta نویسندهٔ پست وقتی رأی value (+1/-1) دریافت می‌کند. */
    public function forVoteReceived(int $value): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        if ($value === 0) {
            return 0;   // رأی لغو شده — بدون delta
        }

        return $value > 0
            ? (int) ($this->config['upvote_received'] ?? 2)
            : (int) ($this->config['downvote_received'] ?? -1);
    }

    /** delta نویسندهٔ پست وقتی پاسخ او برگزیده می‌شود/از برگزیدگی خارج می‌شود. */
    public function forSolution(bool $marked): int
    {
        if (!$this->enabled()) {
            return 0;
        }
        $bonus = (int) ($this->config['solution_author'] ?? 15);

        return $marked ? $bonus : -$bonus;
    }

    public function forThreadCreated(): int
    {
        return $this->enabled() ? (int) ($this->config['thread_created'] ?? 1) : 0;
    }

    public function forPostCreated(): int
    {
        return $this->enabled() ? (int) ($this->config['post_created'] ?? 1) : 0;
    }
}
