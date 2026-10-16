<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration;

use Tavaan\Core\Contracts\Database\MigrationInterface;

/**
 * تَوان | Tavaan — Migration
 * Migration — پایهٔ کلاس‌های مهاجرت (DOC-049 §3).
 *
 * 🔒 هر migration باید `down()` معکوس دقیق `up()` باشد.
 * 🔒 مهاجرت داده‌ای باید idempotent و chunked باشد.
 *
 * ### مثال
 * ```php
 * return new class extends Migration
 * {
 *     public function up(): void
 *     {
 *         Schema::create('orders', fn (Blueprint $t) => $t->ulid('id')->primary());
 *     }
 *     public function down(): void
 *     {
 *         Schema::dropIfExists('orders');
 *     }
 * };
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
abstract class Migration implements MigrationInterface
{
    /** نام اتصال هدف (null = پیش‌فرض). */
    protected ?string $connection = null;

    /** ماژول مالک (توسط Migrator پر می‌شود). */
    public ?string $module = null;

    public function up(): void
    {
    }

    public function down(): void
    {
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }
}
