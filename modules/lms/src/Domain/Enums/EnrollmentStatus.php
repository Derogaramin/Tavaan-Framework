<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * EnrollmentStatus — چرخهٔ وضعیت ثبت‌نام (DOC-095 §2/§3.1).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * EnrollmentStatus — pending_payment → active → completed، با انشعاب dropped.
 *
 * افزودنی مستند نسبت به DOC-095 §2: `pending_payment` برای جریان پرداخت
 * دورهٔ پولی (§3.1: purchase → callback → ثبت‌نام).
 *
 * @api 1.0
 */
enum EnrollmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Completed = 'completed';
    case Dropped = 'dropped';

    /**
     * ماتریس انتقال:
     * pending_payment → active | dropped
     * active          → completed | dropped
     * completed       → (پایانی)
     * dropped         → active (بازگشت مدیرتی/پرداخت‌شده) | pending_payment (پرداخت مجدد)
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::PendingPayment => $target === self::Active || $target === self::Dropped,
            self::Active => $target === self::Completed || $target === self::Dropped,
            self::Completed => false,
            self::Dropped => $target === self::Active || $target === self::PendingPayment,
        };
    }

    /** آیا دانشجو به محتوا دسترسی دارد؟ */
    public function grantsAccess(): bool
    {
        return $this === self::Active || $this === self::Completed;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::PendingPayment => 'در انتظار پرداخت',
            self::Active => 'در حال گذراندن',
            self::Completed => 'تکمیل‌شده',
            self::Dropped => 'رهاشده',
        };
    }
}
