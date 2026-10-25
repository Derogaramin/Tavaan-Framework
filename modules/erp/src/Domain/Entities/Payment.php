<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Payment — پرداخت به تأمین‌کننده (out) یا دریافت از مشتری (in).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * @api 1.0
 */
final class Payment
{
    private function __construct(
        private readonly string $id,
        private readonly string $direction,       // out|in
        private readonly ?string $supplierId,
        private readonly ?string $customerId,
        private readonly ?string $supplierInvoiceId,
        private readonly ?string $salesInvoiceId,
        private readonly int $amount,
        private readonly string $method,           // cash|bank
        private ?string $voucherId,
        private readonly ?int $userId,
        private readonly \DateTimeImmutable $paidAt,
    ) {
    }

    public static function outToSupplier(string $supplierId, int $amount, string $method = 'bank', ?string $supplierInvoiceId = null, ?int $userId = null, ?\DateTimeImmutable $at = null): self
    {
        if ($amount <= 0) {
            throw new \DomainException('مبلغ پرداخت باید مثبت باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            'out',
            $supplierId,
            null,
            $supplierInvoiceId,
            null,
            $amount,
            $method,
            null,
            $userId,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    public static function inFromCustomer(string $customerId, int $amount, string $method = 'bank', ?string $salesInvoiceId = null, ?int $userId = null, ?\DateTimeImmutable $at = null): self
    {
        if ($amount <= 0) {
            throw new \DomainException('مبلغ دریافت باید مثبت باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            'in',
            null,
            $customerId,
            null,
            $salesInvoiceId,
            $amount,
            $method,
            null,
            $userId,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['direction'] ?? 'out'),
            isset($row['supplier_id']) && $row['supplier_id'] !== null && $row['supplier_id'] !== '' ? (string) $row['supplier_id'] : null,
            isset($row['customer_id']) && $row['customer_id'] !== null && $row['customer_id'] !== '' ? (string) $row['customer_id'] : null,
            isset($row['supplier_invoice_id']) && $row['supplier_invoice_id'] !== null && $row['supplier_invoice_id'] !== '' ? (string) $row['supplier_invoice_id'] : null,
            isset($row['sales_invoice_id']) && $row['sales_invoice_id'] !== null && $row['sales_invoice_id'] !== '' ? (string) $row['sales_invoice_id'] : null,
            (int) ($row['amount'] ?? 0),
            (string) ($row['method'] ?? 'bank'),
            isset($row['voucher_id']) && $row['voucher_id'] !== null && $row['voucher_id'] !== '' ? (string) $row['voucher_id'] : null,
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            isset($row['paid_at']) ? new \DateTimeImmutable((string) $row['paid_at'], new \DateTimeZone('UTC')) : new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    public function attachVoucher(string $voucherId): void
    {
        $this->voucherId ??= $voucherId;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'in';
    }

    public function supplierId(): ?string
    {
        return $this->supplierId;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function supplierInvoiceId(): ?string
    {
        return $this->supplierInvoiceId;
    }

    public function salesInvoiceId(): ?string
    {
        return $this->salesInvoiceId;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function voucherId(): ?string
    {
        return $this->voucherId;
    }

    public function paidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'supplier_id' => $this->supplierId,
            'customer_id' => $this->customerId,
            'supplier_invoice_id' => $this->supplierInvoiceId,
            'sales_invoice_id' => $this->salesInvoiceId,
            'amount' => $this->amount,
            'method' => $this->method,
            'voucher_id' => $this->voucherId,
            'user_id' => $this->userId,
            'paid_at' => $this->paidAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ];
    }
}
