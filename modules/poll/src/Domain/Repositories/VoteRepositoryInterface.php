<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * VoteRepositoryInterface — پورت ماندگاری رأی‌ها (Hexagonal).
 */

namespace Modules\Poll\Domain\Repositories;

use Modules\Poll\Domain\Entities\Vote;

/**
 * @api 1.0
 * @see modules/poll/README.md
 */
interface VoteRepositoryInterface
{
    public function add(Vote $vote): void;

    /** شمارش هر گزینه: اندیس گزینه → تعداد. */
    public function countsByPoll(string $pollId): array;

    public function countByPoll(string $pollId): int;

    public function hasVotedByUser(string $pollId, int $userId): bool;

    public function hasVotedByIpHash(string $pollId, string $ipHash): bool;

    /** حذف همهٔ رأی‌های یک نظرسنجی (force delete). */
    public function deleteByPoll(string $pollId): void;

    public function countAll(): int;
}
