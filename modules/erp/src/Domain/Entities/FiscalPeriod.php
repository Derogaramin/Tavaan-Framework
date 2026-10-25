<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * FiscalPeriod — دورهٔ مالی (DOC-097 §4).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\PeriodStatus;
use Modules\Erp\Domain\Events\PeriodWasClosed;

/**
 * @api 1.0
 */
final class FiscalPeriod
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private int $id,
        private readonly string $title,
        private readonly string $startDate,     // Y-m-d
        private readonly string $endDate,
        private PeriodStatus $status,
        private ?string $closeVoucherId,
        private ?int $closedBy,
        private ?\DateTimeImmutable $closedAt,
    ) {
    }

    public static function create(string $title, string $startDate, string $endDate): self
    {
        if ($startDate > $endDate) {
            throw new \DomainException('تاریخ شروع دوره باید پیش از پایان آن باشد.');
        }

        return new self(0, \trim($title), $startDate, $endDate, PeriodStatus::Open, null, null, null);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['title'] ?? ''),
            (string) ($row['start_date'] ?? ''),
            (string) ($row['end_date'] ?? ''),
            PeriodStatus::tryFrom((string) ($row['status'] ?? 'open')) ?? PeriodStatus::Open,
            isset($row['close_voucher_id']) && $row['close_voucher_id'] !== null && $row['close_voucher_id'] !== '' ? (string) $row['close_voucher_id'] : null,
            isset($row['closed_by']) && $row['closed_by'] !== null ? (int) $row['closed_by'] : null,
            self::date($row['closed_at'] ?? null),
        );
    }

    /** انتساب شناسهٔ DB پس از درج (bigint autoincrement). */
    public function assignId(int $id): void
    {
        if ($this->id === 0 && $id > 0) {
            $this->id = $id;
        }
    }

    /** آیا تاریخ سند در این دوره است؟ */
    public function containsDate(string $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    /** بستن دوره (§4 گام ۴-۵) — با سند بستن. */
    public function close(string $closeVoucherId, int $closedBy, ?\DateTimeImmutable $at = null): void
    {
        if ($this->status === PeriodStatus::Closed) {
            throw new \DomainException('دوره پیش‌تر بسته شده است.');
        }

        $this->status = PeriodStatus::Closed;
        $this->closeVoucherId = $closeVoucherId;
        $this->closedBy = $closedBy;
        $this->closedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new PeriodWasClosed($this->id, $this->title, $closeVoucherId, $closedBy));
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function startDate(): string
    {
        return $this->startDate;
    }

    public function endDate(): string
    {
        return $this->endDate;
    }

    public function status(): PeriodStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open;
    }

    public function closeVoucherId(): ?string
    {
        return $this->closeVoucherId;
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
        $row = [
            'title' => $this->title,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status->value,
            'close_voucher_id' => $this->closeVoucherId,
            'closed_by' => $this->closedBy,
            'closed_at' => self::fmt($this->closedAt),
        ];

        return $row;
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
