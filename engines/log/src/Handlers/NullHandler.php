<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Handlers;

use Tavaan\Core\Contracts\Log\HandlerInterface;

/**
 * تَوان | Tavaan — Log
 * NullHandler — دورریختن لاگ‌ها (تست/کانال غیرفعال).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class NullHandler implements HandlerInterface
{
    public function write(string $line): void
    {
    }

    public function writeBatch(array $lines): void
    {
    }

    public function close(): void
    {
    }
}
