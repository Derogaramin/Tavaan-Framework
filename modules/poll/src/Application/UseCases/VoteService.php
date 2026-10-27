<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * VoteService — ثبت رأی با اعتبارسنجی یکتایی و پنجرهٔ زمانی.
 */

namespace Modules\Poll\Application\UseCases;

use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Entities\Vote;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;

/**
 * VoteService — UseCase رأی‌گیری.
 *
 * قواعد:
 * - فقط نظرسنجی votable (باز + در پنجرهٔ زمانی + غیرtrash)
 * - رأی یکتا: کاربر واردشده بر اساس user_id، مهمان بر اساس ip_hash
 * - گزینه‌های نامعتبر/تکراری رد می‌شوند؛ چندانتخابی فقط وقتی multiple
 *
 * 🔒 حریم خصوصی: IP خام هرگز ذخیره نمی‌شود (sha256 با نمک app.key).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class VoteService
{
    /** @param array<string, mixed> $config کلید config('poll') */
    public function __construct(
        private readonly VoteRepositoryInterface $votes,
        private readonly array $config = [],
    ) {
    }

    /**
     * ثبت رأی (یک یا چند گزینه).
     *
     * @param list<int|string> $optionIndexes
     *
     * @return list<Vote> ردیف‌های ثبت‌شده
     *
     * @throws ValidationException
     */
    public function cast(Poll $poll, array $optionIndexes, ?int $userId = null, ?string $ip = null): array
    {
        if (!$poll->isVotable()) {
            throw new ValidationException(
                errors: ['poll' => ['این نظرسنجی اکنون رأی‌پذیر نیست.']],
                messageId: 'poll::messages.not_votable',
            );
        }

        $guestAllowed = (bool) ($this->config['voting']['guest_allowed'] ?? true);
        if ($userId === null && !$guestAllowed) {
            throw new ValidationException(
                errors: ['poll' => ['رأی‌دادن فقط برای کاربران واردشده مجاز است.']],
                messageId: 'poll::messages.login_required',
            );
        }

        $indexes = $this->normalizeIndexes($poll, $optionIndexes);
        $this->assertNotVoted($poll, $userId, $ip);

        $ipHash = $userId === null ? $this->ipHash($ip) : null;

        $created = [];
        foreach ($indexes as $index) {
            $vote = Vote::cast($poll->id(), $index, $userId, $ipHash);
            $this->votes->add($vote);
            $created[] = $vote;
        }

        Event::dispatch('poll.vote.cast', [
            'poll_id' => $poll->id(),
            'slug' => $poll->slug(),
            'option_indexes' => $indexes,
            'user_id' => $userId,
        ]);

        return $created;
    }

    /** آیا این کاربر/مهمان پیش‌تر رأی داده است؟ */
    public function hasVoted(Poll $poll, ?int $userId = null, ?string $ip = null): bool
    {
        $uniqueBy = (array) ($this->config['voting']['unique_by'] ?? ['user', 'ip']);

        if ($userId !== null && \in_array('user', $uniqueBy, true)) {
            return $this->votes->hasVotedByUser($poll->id(), $userId);
        }

        if ($userId === null && \in_array('ip', $uniqueBy, true)) {
            $hash = $this->ipHash($ip);

            return $hash !== null && $this->votes->hasVotedByIpHash($poll->id(), $hash);
        }

        return false;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param list<int|string> $raw
     *
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function normalizeIndexes(Poll $poll, array $raw): array
    {
        $indexes = [];
        foreach ($raw as $value) {
            if (\is_array($value) || \is_object($value) || !\is_numeric($value)) {
                continue;
            }
            $index = (int) $value;
            if (!$poll->hasOption($index) || \in_array($index, $indexes, true)) {
                continue;
            }
            $indexes[] = $index;
        }

        if ($indexes === []) {
            throw new ValidationException(
                errors: ['option' => ['دست‌کم یک گزینهٔ معتبر انتخاب کنید.']],
                messageId: 'poll::messages.option_required',
            );
        }

        if (!$poll->multiple() && \count($indexes) > 1) {
            throw new ValidationException(
                errors: ['option' => ['این نظرسنجی تک‌انتخابی است.']],
                messageId: 'poll::messages.single_choice_only',
            );
        }

        \sort($indexes);

        return $indexes;
    }

    /** @throws ValidationException */
    private function assertNotVoted(Poll $poll, ?int $userId, ?string $ip): void
    {
        if ($this->hasVoted($poll, $userId, $ip)) {
            throw new ValidationException(
                errors: ['poll' => ['شما پیش‌تر در این نظرسنجی رأی داده‌اید.']],
                messageId: 'poll::messages.already_voted',
            );
        }
    }

    /** هش یک‌طرفهٔ IP با نمک app.key (بدون ذخیرهٔ IP خام). */
    private function ipHash(?string $ip): ?string
    {
        if ($ip === null || \trim($ip) === '') {
            return null;
        }

        $salt = \function_exists('config') ? (string) \config('app.key', '') : '';

        return \hash('sha256', \trim($ip) . '|' . $salt);
    }
}
