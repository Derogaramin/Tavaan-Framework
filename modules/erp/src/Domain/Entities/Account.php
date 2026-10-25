<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Account — سرفصل حساب (chart of accounts — DOC-097 §2.1).
 */

namespace Modules\Erp\Domain\Entities;

use Modules\Erp\Domain\Enums\AccountType;

/**
 * @api 1.0
 */
final class Account
{
    private function __construct(
        private readonly int $id,
        private readonly string $code,
        private string $title,
        private readonly ?int $parentId,
        private readonly AccountType $type,
        private readonly int $level,
        private bool $isLeaf,
    ) {
    }

    public static function create(string $code, string $title, AccountType $type, ?int $parentId = null, int $level = 1, bool $isLeaf = true): self
    {
        if (\trim($code) === '' || \trim($title) === '') {
            throw new \DomainException('کد و عنوان حساب الزامی است.');
        }

        return new self(0, \trim($code), \trim($title), $parentId, $type, \max(1, $level), $isLeaf);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['code'] ?? ''),
            (string) ($row['title'] ?? ''),
            isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            AccountType::tryFromString((string) ($row['type'] ?? 'asset')),
            (int) ($row['level'] ?? 1),
            (bool) ($row['is_leaf'] ?? true),
        );
    }

    public function rename(string $title): void
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان حساب نمی‌تواند خالی باشد.');
        }
        $this->title = \trim($title);
    }

    public function markBranch(): void
    {
        $this->isLeaf = false;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function type(): AccountType
    {
        return $this->type;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function isLeaf(): bool
    {
        return $this->isLeaf;
    }

    /** 🔒 فقط حساب تفصیلی (leaf) ردیف سند می‌گیرد. */
    public function isPostable(): bool
    {
        return $this->isLeaf;
    }

    public function normalBalance(): string
    {
        return $this->type->normalBalance();
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'parent_id' => $this->parentId,
            'type' => $this->type->value,
            'level' => $this->level,
            'is_leaf' => $this->isLeaf,
            'normal_balance' => $this->normalBalance(),
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
            'updated_at' => null,
        ];
    }
}
