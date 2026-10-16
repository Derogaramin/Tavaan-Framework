<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

/**
 * Fixture تست ORM — Domain Entity خالص (الگوی Repository — DOC-050 §7).
 *
 * 🔒 بدون extends Model؛ Mapper در Infrastructure.
 *
 * @api internal
 */
final class OrderFixture
{
    private function __construct(
        private readonly string $id,
        private readonly string $customerId,
        private readonly int $total,
        private string $status,
    ) {
    }

    public static function place(string $id, string $customerId, int $total): self
    {
        return new self($id, $customerId, $total, 'pending');
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['customer_id'],
            (int) $row['total'],
            (string) $row['status'],
        );
    }

    public function markPaid(): void
    {
        $this->status = 'paid';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'total' => $this->total,
            'status' => $this->status,
        ];
    }
}
