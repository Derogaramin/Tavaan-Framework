<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ApprovalRouter — مسیر تأیید سلسله‌مراتبی خرید بر اساس مبلغ (DOC-097 §3).
 */

namespace Modules\Erp\Domain\Services;

use Modules\Erp\Domain\Enums\PurchaseOrderStatus;

/**
 * ApprovalRouter — قانون مستند §3:
 *
 * ```
 * مبلغ ≤ finance_threshold            → approved (خودکار)
 * finance < مبلغ ≤ ceo_threshold      → مدیر مالی → approved
 * مبلغ > ceo_threshold                → مدیر مالی → مدیرعامل → approved
 * ```
 *
 * دامنهٔ خالص — برای تست مستقیم.
 *
 * @api 1.0
 */
final readonly class ApprovalRouter
{
    public function __construct(
        private int $financeThreshold,
        private int $ceoThreshold,
    ) {
        if ($financeThreshold < 0 || $ceoThreshold < $financeThreshold) {
            throw new \DomainException('آستانه‌های تأیید نامعتبر: ceo ≥ finance ≥ 0.');
        }
    }

    /** مقصد ثبت سفارش پس از submit — سلسله‌مراتبی: مالی پیش از مدیرعامل. */
    public function routeOnSubmit(int $totalMinor): PurchaseOrderStatus
    {
        if ($totalMinor <= $this->financeThreshold) {
            return PurchaseOrderStatus::Approved;
        }

        return PurchaseOrderStatus::PendingFinance;
    }

    /** پس از تأیید مالی — آیا مدیرعامل هم لازم است؟ */
    public function needsCeoAfterFinance(int $totalMinor): bool
    {
        return $totalMinor > $this->ceoThreshold;
    }

    /** آیا اصلاً تأیید انسانی لازم است؟ */
    public function needsApproval(int $totalMinor): bool
    {
        return $totalMinor > $this->financeThreshold;
    }

    public function financeThreshold(): int
    {
        return $this->financeThreshold;
    }

    public function ceoThreshold(): int
    {
        return $this->ceoThreshold;
    }
}
