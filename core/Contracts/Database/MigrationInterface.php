<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * MigrationInterface — قرارداد یک مهاجرت (DOC-049).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
interface MigrationInterface
{
    /** اعمال تغییرات (جلو). */
    public function up(): void;

    /** معکوس دقیق up() (برای rollback). */
    public function down(): void;
}
