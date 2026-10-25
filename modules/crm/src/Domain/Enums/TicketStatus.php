<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketStatus — چرخهٔ تیکت (DOC-096 §3.3).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * open → pending (پاسخ کارشناس — منتظر مشتری) → open (پاسخ مشتری)
 * open/pending → resolved → closed (تأیید مشتری یا خودکار).
 *
 * @api 1.0
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Open => $target === self::Pending || $target === self::Resolved || $target === self::Closed,
            self::Pending => $target === self::Open || $target === self::Resolved || $target === self::Closed,
            self::Resolved => $target === self::Closed || $target === self::Open,   // بازگشت از حل (رد توسط مشتری)
            self::Closed => false,
        };
    }

    /** آیا ساعت SLA برای این وضعیت ticking است؟ (pending = منتظر مشتری → توقف) */
    public function slaRunning(): bool
    {
        return $this === self::Open;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Pending => 'منتظر مشتری',
            self::Resolved => 'حل‌شده',
            self::Closed => 'بسته',
        };
    }
}
