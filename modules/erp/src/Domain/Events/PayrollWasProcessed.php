<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PayrollWasProcessed — حقوق پردازش شد (DOC-097 §9: hr.payroll.processed).
 */

namespace Modules\Erp\Domain\Events;

/**
 * مصرف‌کننده: accounting (سند هزینهٔ حقوق).
 *
 * @api 1.0
 */
final readonly class PayrollWasProcessed
{
    public function __construct(
        public string $payrollRunId,
        public string $periodMonth,
        public int $totalGross,
        public int $totalNet,
        public int $slipCount,
        public ?string $voucherId,
    ) {
    }

    public function eventName(): string
    {
        return 'hr.payroll.processed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'payroll_run_id' => $this->payrollRunId,
            'period_month' => $this->periodMonth,
            'total_gross' => $this->totalGross,
            'total_net' => $this->totalNet,
            'slip_count' => $this->slipCount,
            'voucher_id' => $this->voucherId,
        ];
    }
}
