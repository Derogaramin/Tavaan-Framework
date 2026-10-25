<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Voucher — سند حسابداری دوطرفه (DOC-097 §2.1).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\VoucherStatus;
use Modules\Erp\Domain\Events\VoucherWasPosted;

/**
 * Voucher — سند حسابداری با قانون طلایی:
 *
 * 🔒 `SUM(debit) === SUM(credit)` (تراز — AC-ERP-09) — int minor-unit.
 * 🔒 سند `posted` غیرقابل تغییر است — اصلاح فقط با سند معکوس (reverse_of).
 *
 * چرخه: draft → approved → posted → (voided با سند معکوس).
 *
 * @api 1.0
 * @see modules/erp/README.md
 */
final class Voucher
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var list<VoucherLine> */
    private array $lines = [];

    private function __construct(
        private readonly string $id,
        private readonly string $voucherNumber,
        private ?int $periodId,
        private readonly string $date,              // Y-m-d
        private ?string $description,
        private VoucherStatus $status,
        private readonly string $sourceModule,
        private readonly ?string $sourceId,
        private ?string $reverseOf,
        private ?int $createdBy,
        private ?int $approvedBy,
        private ?\DateTimeImmutable $postedAt,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param list<VoucherLine> $lines */
    public static function draft(
        string $voucherNumber,
        string $date,
        array $lines,
        ?string $description = null,
        string $sourceModule = 'manual',
        ?string $sourceId = null,
        ?int $periodId = null,
        ?int $createdBy = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $voucher = new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $voucherNumber,
            $periodId,
            $date,
            $description,
            VoucherStatus::Draft,
            $sourceModule,
            $sourceId,
            null,
            $createdBy,
            null,
            null,
            $now,
            null,
        );

        foreach ($lines as $line) {
            $voucher->addLine($line);
        }

        return $voucher;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<VoucherLine> $lines
     */
    public static function fromRow(array $row, array $lines = []): self
    {
        $voucher = new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['voucher_number'] ?? ''),
            isset($row['period_id']) && $row['period_id'] !== null ? (int) $row['period_id'] : null,
            (string) ($row['date'] ?? ''),
            isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : null,
            VoucherStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? VoucherStatus::Draft,
            (string) ($row['source_module'] ?? 'manual'),
            isset($row['source_id']) && $row['source_id'] !== null && $row['source_id'] !== '' ? (string) $row['source_id'] : null,
            isset($row['reverse_of']) && $row['reverse_of'] !== null && $row['reverse_of'] !== '' ? (string) $row['reverse_of'] : null,
            isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            self::parseDate($row['posted_at'] ?? null),
            self::parseDate($row['created_at'] ?? null),
            self::parseDate($row['updated_at'] ?? null),
        );
        $voucher->lines = $lines;

        return $voucher;
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    public function addLine(VoucherLine $line): void
    {
        if ($this->status->isImmutable()) {
            throw new \DomainException('سند ثبت‌شده/باطل‌شده ردیف جدید نمی‌گیرد (immutable — DOC-097 §2.1).');
        }

        $this->lines[] = $line;
        $this->touch();
    }

    /** 🔒 قانون طلایی — SUM(debit) === SUM(credit) و حداقل دو ردیف. */
    public function isBalanced(): bool
    {
        if (\count($this->lines) < 2) {
            return false;
        }

        return $this->totalDebit() === $this->totalCredit() && $this->totalDebit() > 0;
    }

    public function totalDebit(): int
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            $sum += $line->debit();
        }

        return $sum;
    }

    public function totalCredit(): int
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            $sum += $line->credit();
        }

        return $sum;
    }

    public function approve(int $approverId): void
    {
        $this->transitionTo(VoucherStatus::Approved);
        $this->approvedBy = $approverId;
        $this->touch();
    }

    /**
     * ثبت قطعی — پس از این immutable (AC-ERP-09 تراز پیش‌نیاز است).
     */
    public function post(?int $posterId = null, ?\DateTimeImmutable $at = null): void
    {
        if (!$this->isBalanced()) {
            throw new \DomainException(\sprintf(
                'سند تراز نیست: debit=%d credit=%d (قانون طلایی — DOC-097 §2.1)',
                $this->totalDebit(),
                $this->totalCredit(),
            ));
        }

        $this->transitionTo(VoucherStatus::Posted);
        $this->postedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->approvedBy ??= $posterId;
        $this->touch();

        $this->record(new VoucherWasPosted(
            $this->id,
            $this->voucherNumber,
            $this->date,
            $this->sourceModule,
            $this->sourceId,
            $this->totalDebit(),
            $this->currencyAgnosticTotal(),
        ));
    }

    /**
     * ابطال — فقط با سند معکوس (reverseVoucher ساخت معکوس را برمی‌گرداند).
     */
    public function markVoidedBy(string $reverseVoucherId): void
    {
        if ($this->status !== VoucherStatus::Posted) {
            throw new \DomainException('فقط سند ثبت‌شده باطل می‌شود.');
        }

        $this->transitionTo(VoucherStatus::Voided);
        $this->reverseOf = $reverseVoucherId;
        $this->touch();
    }

    /**
     * ساخت سند معکوس (خطوط جابه‌جا: debit↔credit) — اصلاح بدون حذف.
     */
    public function reverseVoucher(string $voucherNumber, ?int $userId = null, ?string $date = null): self
    {
        if ($this->status !== VoucherStatus::Posted) {
            throw new \DomainException('فقط سند ثبت‌شده معکوس می‌شود.');
        }

        $reversed = [];
        foreach ($this->lines as $line) {
            $reversed[] = VoucherLine::create(
                accountId: $line->accountId(),
                debit: $line->credit(),     // 🔒 جابه‌جایی
                credit: $line->debit(),
                description: 'معکوس: ' . (string) ($line->description() ?? ''),
            );
        }

        return self::draft(
            voucherNumber: $voucherNumber,
            date: $date ?? $this->date,
            lines: $reversed,
            description: 'سند معکوس ' . $this->voucherNumber,
            sourceModule: 'manual',
            sourceId: $this->id,
            periodId: $this->periodId,
            createdBy: $userId,
        );
    }

    public function attachPeriod(int $periodId): void
    {
        $this->periodId = $periodId;
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function voucherNumber(): string
    {
        return $this->voucherNumber;
    }

    public function periodId(): ?int
    {
        return $this->periodId;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): VoucherStatus
    {
        return $this->status;
    }

    public function sourceModule(): string
    {
        return $this->sourceModule;
    }

    public function sourceId(): ?string
    {
        return $this->sourceId;
    }

    public function reverseOf(): ?string
    {
        return $this->reverseOf;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function approvedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function postedAt(): ?\DateTimeImmutable
    {
        return $this->postedAt;
    }

    /** @return list<VoucherLine> */
    public function lines(): array
    {
        return $this->lines;
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
            'voucher_number' => $this->voucherNumber,
            'period_id' => $this->periodId,
            'date' => $this->date,
            'description' => $this->description,
            'status' => $this->status->value,
            'source_module' => $this->sourceModule,
            'source_id' => $this->sourceId,
            'reverse_of' => $this->reverseOf,
            'total_debit' => $this->totalDebit(),
            'total_credit' => $this->totalCredit(),
            'created_by' => $this->createdBy,
            'approved_by' => $this->approvedBy,
            'posted_at' => self::fmt($this->postedAt),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function currencyAgnosticTotal(): int
    {
        return $this->totalDebit();
    }

    private function transitionTo(VoucherStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت سند نامجاز: %s → %s (سند %s)',
                $this->status->value,
                $target->value,
                $this->voucherNumber,
            ));
        }

        $this->status = $target;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function parseDate(mixed $value): ?\DateTimeImmutable
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
