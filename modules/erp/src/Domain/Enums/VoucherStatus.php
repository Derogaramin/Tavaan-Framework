<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * VoucherStatus — چرخهٔ سند حسابداری (DOC-097 §2.1).
 */

namespace Modules\Erp\Domain\Enums;

/**
 * draft → approved → posted (immutable) • voided فقط از posted با سند معکوس.
 *
 * @api 1.0
 */
enum VoucherStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Voided = 'voided';

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Approved || $target === self::Posted,   // post مستقیم با مجوز
            self::Approved => $target === self::Posted || $target === self::Draft,   // برگشت به پیش‌نویس
            self::Posted => $target === self::Voided,
            self::Voided => false,
        };
    }

    /** 🔒 سند posted غیرقابل تغییر (اصلاح با سند معکوس). */
    public function isImmutable(): bool
    {
        return $this === self::Posted || $this === self::Voided;
    }

    /** آیا در تراز آزمایشی/گزارش‌ها حساب می‌شود؟ */
    public function countsInReports(): bool
    {
        return $this === self::Posted;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Approved => 'تأییدشده',
            self::Posted => 'ثبت‌شده',
            self::Voided => 'باطل‌شده',
        };
    }
}
