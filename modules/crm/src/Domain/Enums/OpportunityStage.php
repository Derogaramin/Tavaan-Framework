<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityStage — مراحل pipeline فروش (DOC-096 §2/§3.1).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * OpportunityStage — prospecting → qualification → proposal → negotiation
 * → closed_won / closed_lost.
 *
 * احتمال پیش‌فرض هر مرحله برای weighted forecast (§3.1) — مقدار نهایی از
 * config('crm.pipeline.probabilities') خوانده می‌شود؛ این map fallback است.
 *
 * @api 1.0
 */
enum OpportunityStage: string
{
    case Prospecting = 'prospecting';
    case Qualification = 'qualification';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case ClosedWon = 'closed_won';
    case ClosedLost = 'closed_lost';

    /** @return list<self> مراحل باز (خط لولهٔ فعال) */
    public static function openStages(): array
    {
        return [self::Prospecting, self::Qualification, self::Proposal, self::Negotiation];
    }

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Prospecting;
    }

    /**
     * ماتریس انتقال — فقط رو به جلو + بستن (won/lost) از هر مرحلهٔ باز.
     * یک مرحله «جهش رو به عقب» هم مجاز است (برگشت از مذاکره به پیشنهاد).
     */
    public function canMoveTo(self $target): bool
    {
        if ($target === $this || $this->isClosed()) {
            return false;
        }

        if ($target === self::ClosedWon || $target === self::ClosedLost) {
            return true;   // بستن از هر مرحلهٔ باز
        }

        $order = [
            self::Prospecting->value => 0,
            self::Qualification->value => 1,
            self::Proposal->value => 2,
            self::Negotiation->value => 3,
        ];

        $from = $order[$this->value] ?? null;
        $to = $order[$target->value] ?? null;
        if ($from === null || $to === null) {
            return false;
        }

        // رو به جلو (هر پرش) یا یک گام عقب
        return $to > $from || $to === $from - 1;
    }

    public function isClosed(): bool
    {
        return $this === self::ClosedWon || $this === self::ClosedLost;
    }

    public function isWon(): bool
    {
        return $this === self::ClosedWon;
    }

    /** احتمال پیش‌فرض (fallback — مقدار واقعی از config). */
    public function defaultProbability(): int
    {
        return match ($this) {
            self::Prospecting => 10,
            self::Qualification => 25,
            self::Proposal => 50,
            self::Negotiation => 75,
            self::ClosedWon => 100,
            self::ClosedLost => 0,
        };
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Prospecting => 'احتمال اولیه',
            self::Qualification => 'واجد صلاحیت',
            self::Proposal => 'ارائهٔ پیشنهاد',
            self::Negotiation => 'مذاکره',
            self::ClosedWon => 'موفق (won)',
            self::ClosedLost => 'ناموفق (lost)',
        };
    }
}
