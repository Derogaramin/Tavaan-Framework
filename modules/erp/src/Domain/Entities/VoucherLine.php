<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * VoucherLine — ردیف سند حسابداری (DOC-097 §2.1).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * VoucherLine — یک ردیف بدهکار/بستانکار.
 *
 * 🔒 هر ردیف یا debit یا credit (هر دو باهم ≠ ۰ مجاز نیست) — مبالغ
 * int minor-unit و نامنفی.
 *
 * @api 1.0
 */
final class VoucherLine
{
    private function __construct(
        private readonly string $id,
        private readonly int $accountId,
        private readonly int $debit,
        private readonly int $credit,
        private readonly ?string $description,
        private readonly ?int $costCenterId,
        private readonly ?string $projectId,
    ) {
    }

    public static function create(
        int $accountId,
        int $debit = 0,
        int $credit = 0,
        ?string $description = null,
        ?int $costCenterId = null,
        ?string $projectId = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if ($accountId <= 0) {
            throw new \DomainException('ردیف سند بدون حساب معتبر ساخته نمی‌شود.');
        }
        if ($debit < 0 || $credit < 0) {
            throw new \DomainException('مبلغ ردیف سند نمی‌تواند منفی باشد.');
        }
        if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
            throw new \DomainException('هر ردیف سند دقیقاً یک سمت (بدهکار یا بستانکار) دارد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $accountId,
            $debit,
            $credit,
            $description === null || \trim($description) === '' ? null : \trim($description),
            $costCenterId,
            $projectId,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (int) ($row['account_id'] ?? 0),
            (int) ($row['debit'] ?? 0),
            (int) ($row['credit'] ?? 0),
            isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : null,
            isset($row['cost_center_id']) && $row['cost_center_id'] !== null ? (int) $row['cost_center_id'] : null,
            isset($row['project_id']) && $row['project_id'] !== null && $row['project_id'] !== '' ? (string) $row['project_id'] : null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function debit(): int
    {
        return $this->debit;
    }

    public function credit(): int
    {
        return $this->credit;
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function costCenterId(): ?int
    {
        return $this->costCenterId;
    }

    public function projectId(): ?string
    {
        return $this->projectId;
    }

    /** @return array<string, mixed> */
    public function toRow(string $voucherId): array
    {
        return [
            'id' => $this->id,
            'voucher_id' => $voucherId,
            'account_id' => $this->accountId,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'description' => $this->description,
            'cost_center_id' => $this->costCenterId,
            'project_id' => $this->projectId,
        ];
    }
}
