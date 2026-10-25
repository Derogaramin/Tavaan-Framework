<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PurchaseOrderStatus — چرخهٔ سفارش خرید (DOC-097 §2.3).
 */

namespace Modules\Erp\Domain\Enums;

/**
 * draft → (submit) → pending_finance|approved (بر اساس مبلغ) → approved →
 * received → invoiced → paid • rejected/canceled انشعاب‌ها.
 *
 * @api 1.0
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingFinance = 'pending_finance';
    case PendingCeo = 'pending_ceo';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case Canceled = 'canceled';

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::PendingFinance || $target === self::PendingCeo || $target === self::Approved || $target === self::Canceled,
            self::PendingFinance => $target === self::PendingCeo || $target === self::Approved || $target === self::Rejected,
            self::PendingCeo => $target === self::Approved || $target === self::Rejected,
            self::Approved => $target === self::Received || $target === self::Canceled,
            self::Received => $target === self::Invoiced,
            self::Invoiced => $target === self::Paid,
            self::Rejected, self::Paid, self::Canceled => false,
        };
    }

    /** در انتظار تأیید (هر سطح). */
    public function isAwaitingApproval(): bool
    {
        return $this === self::PendingFinance || $this === self::PendingCeo;
    }

    public function isFinal(): bool
    {
        return $this === self::Rejected || $this === self::Paid || $this === self::Canceled;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::PendingFinance => 'در انتظار تأیید مالی',
            self::PendingCeo => 'در انتظار تأیید مدیرعامل',
            self::Approved => 'تأییدشده',
            self::Rejected => 'ردشده',
            self::Received => 'رسیدشده',
            self::Invoiced => 'فاکتورشده',
            self::Paid => 'پرداخت‌شده',
            self::Canceled => 'لغوشده',
        };
    }
}
