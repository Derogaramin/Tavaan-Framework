<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Log;

/**
 * قرارداد Handler نوشتن لاگ (مقصد نهایی رشتهٔ format‌شده).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
interface HandlerInterface
{
    public function write(string $line): void;

    /** @param list<string> $lines */
    public function writeBatch(array $lines): void;

    public function close(): void;
}
