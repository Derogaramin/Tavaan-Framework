<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Observability;

/**
 * تَوان | Tavaan — Contracts
 * SpanExporterInterface — قرارداد صادرکنندهٔ Spanها (DOC-067 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
interface SpanExporterInterface
{
    /** دریافت یک Span پایان‌یافته (معمولاً buffer می‌شود). */
    public function export(SpanInterface $span): void;

    /**
     * ارسال bufferها به مقصد.
     *
     * @return int تعداد Spanهای صادرشده
     */
    public function flush(): int;

    /** نام exporter (برای پیکربندی/لاگ). */
    public function name(): string;
}
