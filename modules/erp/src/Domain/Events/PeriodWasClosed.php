<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PeriodWasClosed — دورهٔ مالی بسته شد (DOC-097 §4).
 */

namespace Modules\Erp\Domain\Events;

/**
 * @api 1.0
 */
final readonly class PeriodWasClosed
{
    public function __construct(
        public int $periodId,
        public string $title,
        public string $closeVoucherId,
        public int $closedBy,
    ) {
    }

    public function eventName(): string
    {
        return 'accounting.period.closed';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'period_id' => $this->periodId,
            'title' => $this->title,
            'close_voucher_id' => $this->closeVoucherId,
            'closed_by' => $this->closedBy,
        ];
    }
}
