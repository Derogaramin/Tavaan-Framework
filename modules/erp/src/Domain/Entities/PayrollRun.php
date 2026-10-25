<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PayrollRun — دورهٔ پردازش حقوق (draft→processed→paid).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Events\PayrollWasProcessed;

/**
 * @api 1.0
 */
final class PayrollRun
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly string $periodMonth,
        private string $status,             // draft|processed|paid
        private int $totalGross,
        private int $totalNet,
        private int $slipCount,
        private ?string $voucherId,
        private ?int $processedBy,
        private ?\DateTimeImmutable $processedAt,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $periodMonth, ?\DateTimeImmutable $at = null): self
    {
        if (!\preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodMonth)) {
            throw new \DomainException('دورهٔ حقوق باید YYYY-MM باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $periodMonth,
            'draft',
            0,
            0,
            0,
            null,
            null,
            null,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['period_month'] ?? ''),
            (string) ($row['status'] ?? 'draft'),
            (int) ($row['total_gross'] ?? 0),
            (int) ($row['total_net'] ?? 0),
            (int) ($row['slip_count'] ?? 0),
            isset($row['voucher_id']) && $row['voucher_id'] !== null && $row['voucher_id'] !== '' ? (string) $row['voucher_id'] : null,
            isset($row['processed_by']) && $row['processed_by'] !== null ? (int) $row['processed_by'] : null,
            isset($row['processed_at']) && $row['processed_at'] !== null && $row['processed_at'] !== '' ? new \DateTimeImmutable((string) $row['processed_at'], new \DateTimeZone('UTC')) : null,
            isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')) : null,
        );
    }

    public function process(int $totalGross, int $totalNet, int $slipCount, ?string $voucherId, int $processedBy): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException('فقط دورهٔ پیش‌نویس پردازش می‌شود.');
        }
        if ($slipCount <= 0) {
            throw new \DomainException('پردازش حقوق بدون فیش معنا ندارد.');
        }

        $this->status = 'processed';
        $this->totalGross = $totalGross;
        $this->totalNet = $totalNet;
        $this->slipCount = $slipCount;
        $this->voucherId = $voucherId;
        $this->processedBy = $processedBy;
        $this->processedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new PayrollWasProcessed($this->id, $this->periodMonth, $totalGross, $totalNet, $slipCount, $voucherId));
    }

    public function markPaid(): void
    {
        if ($this->status !== 'processed') {
            throw new \DomainException('فقط دورهٔ پردازش‌شده پرداخت می‌شود.');
        }
        $this->status = 'paid';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function periodMonth(): string
    {
        return $this->periodMonth;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed' || $this->status === 'paid';
    }

    public function totalGross(): int
    {
        return $this->totalGross;
    }

    public function totalNet(): int
    {
        return $this->totalNet;
    }

    public function slipCount(): int
    {
        return $this->slipCount;
    }

    public function voucherId(): ?string
    {
        return $this->voucherId;
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
            'period_month' => $this->periodMonth,
            'status' => $this->status,
            'total_gross' => $this->totalGross,
            'total_net' => $this->totalNet,
            'slip_count' => $this->slipCount,
            'voucher_id' => $this->voucherId,
            'processed_by' => $this->processedBy,
            'processed_at' => $this->processedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            'created_at' => $this->createdAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ];
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
