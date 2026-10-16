<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Rules;

use Tavaan\Core\Contracts\Validation\RuleInterface;
use Tavaan\Core\Facade\Facade;

/**
 * تَوان | Tavaan — Validation
 * UniqueRule — قاعدهٔ unique ناهمزمان (دیتابیس — DOC-051 §4.3).
 *
 * 🔒 Prepared Statement + allowlist شناسه (جلوگیری از SQL Injection).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class UniqueRule implements RuleInterface
{
    public function __construct(
        private readonly string $table,
        private readonly ?string $column = null,
        private readonly int|string|null $ignoreId = null,
        private readonly string $ignoreColumn = 'id',
    ) {
    }

    public function passes(string $attribute, mixed $value): bool
    {
        $db = Facade::getFacadeApplication()->make(\Tavaan\Core\Contracts\Database\ConnectionInterface::class);

        $column = $this->column ?? \substr($attribute, (int) \strrpos($attribute, '.') + 1);
        $this->assertIdentifier($this->table);
        $this->assertIdentifier($column);

        $query = $db->table($this->table)->where($column, $value);
        if ($this->ignoreId !== null && $this->ignoreId !== '') {
            $this->assertIdentifier($this->ignoreColumn);
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        return !$query->exists();
    }

    public function message(): string
    {
        return 'validation.unique';
    }

    public function implicit(): bool
    {
        return false;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid database identifier [%s].', $identifier));
        }
    }
}
