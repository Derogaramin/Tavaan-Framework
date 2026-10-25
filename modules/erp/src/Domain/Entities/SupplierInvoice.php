<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SupplierInvoice — فاکتور تأمین‌کننده (باز — paid).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * @api 1.0
 */
final class SupplierInvoice
{
    private function __construct(
        private readonly string $id,
        private readonly string $invoiceNumber,
        private readonly string $supplierId,
        private readonly ?string $poId,
        private readonly ?string $receiptId,
        private readonly int $amount,
        private string $status,          // open|paid
        private readonly \DateTimeImmutable $issuedAt,
        private ?\DateTimeImmutable $paidAt,
    ) {
    }

    public static function create(string $invoiceNumber, string $supplierId, int $amount, ?string $poId = null, ?string $receiptId = null, ?\DateTimeImmutable $at = null): self
    {
        if ($amount <= 0) {
            throw new \DomainException('مبلغ فاکتور باید مثبت باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $invoiceNumber,
            $supplierId,
            $poId,
            $receiptId,
            $amount,
            'open',
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
            (string) ($row['supplier_id'] ?? ''),
            isset($row['po_id']) && $row['po_id'] !== null && $row['po_id'] !== '' ? (string) $row['po_id'] : null,
            isset($row['receipt_id']) && $row['receipt_id'] !== null && $row['receipt_id'] !== '' ? (string) $row['receipt_id'] : null,
            (int) ($row['amount'] ?? 0),
            (string) ($row['status'] ?? 'open'),
            isset($row['issued_at']) ? new \DateTimeImmutable((string) $row['issued_at'], new \DateTimeZone('UTC')) : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            isset($row['paid_at']) && $row['paid_at'] !== null && $row['paid_at'] !== '' ? new \DateTimeImmutable((string) $row['paid_at'], new \DateTimeZone('UTC')) : null,
        );
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

    public function supplierId(): string
    {
        return $this->supplierId;
    }

    public function poId(): ?string
    {
        return $this->poId;
    }

    public function receiptId(): ?string
    {
        return $this->receiptId;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'supplier_id' => $this->supplierId,
            'po_id' => $this->poId,
            'receipt_id' => $this->receiptId,
            'amount' => $this->amount,
            'status' => $this->status,
            'issued_at' => $this->issuedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            'paid_at' => $this->paidAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ];
    }
}
