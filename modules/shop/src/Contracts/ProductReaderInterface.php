<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductReaderInterface — قرارداد عمومی خواندن محصول برای سایر ماژول‌ها.
 */

namespace Modules\Shop\Contracts;

/**
 * ارائه‌دهنده: tavaan.shop • مصرف‌کنندگان: crm/report/bpms (آینده).
 *
 * @api 1.0
 */
interface ProductReaderInterface
{
    /** @return array<string, mixed>| null — {id, title, slug, price_minor, currency, stock, status} */
    public function snapshotById(string $productId): ?array;

    public function priceOf(string $productId): int;

    public function titleOf(string $productId, string $fallback = 'محصول'): string;
}
