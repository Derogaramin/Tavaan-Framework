<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Config;

/**
 * قرارداد کش/بازیابی کل پیکربندی در یک فایل.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
interface ConfigCacheInterface
{
    /** تولید فایل کش (atomic write). */
    public function cache(string $file): void;

    /** @return array<string, mixed>|null اگر کش موجود نباشد null */
    public function load(string $file): ?array;

    public function clear(string $file): void;

    public function isCached(string $file): bool;
}
