<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesInvoice — فاکتور فروش (issued→paid).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Events\SalesInvoiceWasIssued;

/**
 * @api 1.0
 */
final class SalesInvoice
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly string $invoiceNumber,
        private readonly string $customerId,
        private readonly ?string $salesOrderId,
        private readonly int $amount,
        private readonly int $cogsAmount,
        private string $status,          // issued|paid|canceled
        private ?string $voucherId,
        private readonly \DateTimeImmutable $issuedAt,
        private ?\DateTimeImmutable $paidAt,
    ) {
    }

    public static function issue(string $invoiceNumber, string $customerId, int $amount, int $cogsAmount, ?string $salesOrderId = null, ?\DateTimeImmutable $at = null): self
    {
        if ($amount <= 0) {
            throw new \DomainException('مبلغ فاکتور باید مثبت باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $invoiceNumber,
            $customerId,
            $salesOrderId,
            $amount,
            \max(0, $cogsAmount),
            'issued',
            null,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['invoice_number'] ?? ''),
            (string) ($row['customer_id'] ?? ''),
            isset($row['sales_order_id']) && $row['sales_order_id'] !== null && $row['sales_order_id'] !== '' ? (string) $row['sales_order_id'] : null,
            (int) ($row['amount'] ?? 0),
            (int) ($row['cogs_amount'] ?? 0),
            (string) ($row['status'] ?? 'issued'),
            isset($row['voucher_id']) && $row['voucher_id'] !== null && $row['voucher_id'] !== '' ? (string) $row['voucher_id'] : null,
            isset($row['issued_at']) ? new \DateTimeImmutable((string) $row['issued_at'], new \DateTimeZone('UTC')) : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            isset($row['paid_at']) && $row['paid_at'] !== null && $row['paid_at'] !== '' ? new \DateTimeImmutable((string) $row['paid_at'], new \DateTimeZone('UTC')) : null,
        );
    }

    public function attachVoucher(string $voucherId): void
    {
        $this->voucherId ??= $voucherId;
    }

    public function recordIssued(): void
    {
        $this->record(new SalesInvoiceWasIssued($this->id, $this->invoiceNumber, $this->customerId, $this->amount, $this->cogsAmount, $this->salesOrderId, $this->voucherId));
    }

    public function markPaid(?\DateTimeImmutable $at = null): void
    {
        if ($this->status === 'paid') {
            return;
        }
        $this->status = 'paid';
        $this->paidAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function salesOrderId(): ?string
    {
        return $this->salesOrderId;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function cogsAmount(): int
    {
        return $this->cogsAmount;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function voucherId(): ?string
    {
        return $this->voucherId;
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'customer_id' => $this->customerId,
            'sales_order_id' => $this->salesOrderId,
            'amount' => $this->amount,
            'cogs_amount' => $this->cogsAmount,
            'status' => $this->status,
            'voucher_id' => $this->voucherId,
            'issued_at' => $this->issuedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            'paid_at' => $this->paidAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ];
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
