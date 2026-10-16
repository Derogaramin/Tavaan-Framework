<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Rules;

use Tavaan\Core\Contracts\Validation\RuleInterface;
use Tavaan\Core\Facade\Facade;

/**
 * تَوان | Tavaan — Validation
 * ExistsRule — قاعدهٔ exists ناهمزمان (دیتابیس — DOC-051 §4.3).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class ExistsRule implements RuleInterface
{
    public function __construct(
        private readonly string $table,
        private readonly ?string $column = null,
    ) {
    }

    public function passes(string $attribute, mixed $value): bool
    {
        $db = Facade::getFacadeApplication()->make(\Tavaan\Core\Contracts\Database\ConnectionInterface::class);

        $column = $this->column ?? \substr($attribute, (int) \strrpos($attribute, '.') + 1);
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->table) !== 1
            || \preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new \InvalidArgumentException('Invalid database identifier.');
        }

        return $db->table($this->table)->where($column, $value)->exists();
    }

    public function message(): string
    {
        return 'validation.exists';
    }

    public function implicit(): bool
    {
        return false;
    }
}
