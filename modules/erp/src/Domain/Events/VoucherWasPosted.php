<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * VoucherWasPosted — سند حسابداری ثبت قطعی شد (DOC-097 §2.1: immutable).
 */

namespace Modules\Erp\Domain\Events;

/**
 * @api 1.0
 */
final readonly class VoucherWasPosted
{
    public function __construct(
        public string $voucherId,
        public string $voucherNumber,
        public string $date,
        public string $sourceModule,
        public ?string $sourceId,
        public int $totalDebit,
        public int $totalCredit,
    ) {
    }

    public function eventName(): string
    {
        return 'erp.voucher.posted';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'voucher_id' => $this->voucherId,
            'voucher_number' => $this->voucherNumber,
            'date' => $this->date,
            'source_module' => $this->sourceModule,
            'source_id' => $this->sourceId,
            'total_debit' => $this->totalDebit,
            'total_credit' => $this->totalCredit,
        ];
    }
}
