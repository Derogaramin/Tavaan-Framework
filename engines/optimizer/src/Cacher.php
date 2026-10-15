<?php

declare(strict_types=1);

namespace Tavaan\Engine\Optimizer;

/**
 * تَوان | Tavaan — Optimizer
 * Cacher — قرارداد یک تولیدکنندهٔ کش (config/routes/views/autoload/…).
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
interface Cacher
{
    /** تولید کش. */
    public function cache(): void;

    /** حذف کش. */
    public function clear(): void;

    /** آیا کش فعلی تازه است؟ (invalidation خودکار) */
    public function isFresh(): bool;
}
