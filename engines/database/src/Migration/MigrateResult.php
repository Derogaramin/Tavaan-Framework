<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration;

/**
 * تَوان | Tavaan — Migration
 * MigrateResult — نتیجهٔ اجرای migrate/rollback (value object).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final readonly class MigrateResult
{
    /**
     * @param list<string> $migrations نام migrationهای اجرا/لیست‌شده
     */
    private function __construct(
        public array $migrations,
        public bool $dryRun,
        public int $batch,
        public bool $success,
    ) {
    }

    /** @param list<string> $migrations */
    public static function success(array $migrations, int $batch): self
    {
        return new self($migrations, false, $batch, true);
    }

    /** @param list<string> $migrations */
    public static function dryRun(array $migrations): self
    {
        return new self($migrations, true, 0, true);
    }

    /** @param list<string> $migrations */
    public static function nothing(array $migrations = []): self
    {
        return new self($migrations, false, 0, true);
    }

    public function count(): int
    {
        return \count($this->migrations);
    }
}
