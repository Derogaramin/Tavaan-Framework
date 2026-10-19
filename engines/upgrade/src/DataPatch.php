<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

/**
 * تَوان | Tavaan — Upgrade
 * DataPatch — پایهٔ patchهای داده (DOC-081E §3).
 *
 * ```php
 * // upgrades/1.1.0/patch_001_backfill_order_status.php
 * return new class extends DataPatch {
 *     public string $version = '1.1.0';
 *     public string $description = 'Backfill order status';
 *     public function up(): void { … }    // 🔒 idempotent + chunked
 *     public function down(): void { … }
 * };
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
abstract class DataPatch
{
    /** نسخهٔ هدف (باید با دایرکتوری پدر یکی باشد) */
    public string $version = '';

    public string $description = '';

    /** اجرای patch — 🔒 باید idempotent باشد (اجرای مجدد بی‌اثر). */
    abstract public function up(): void;

    /** بازگردانی patch (اختیاری — برای rollback نرم). */
    public function down(): void
    {
    }
}
