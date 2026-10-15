<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Handlers;

use Tavaan\Core\Contracts\Log\HandlerInterface;

/**
 * تَوان | Tavaan — Log
 * StderrHandler — خروجی به STDERR (Docker/CLI).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class StderrHandler implements HandlerInterface
{
    public function write(string $line): void
    {
        \fwrite(STDERR, $line);
    }

    public function writeBatch(array $lines): void
    {
        \fwrite(STDERR, \implode('', $lines));
    }

    public function close(): void
    {
    }
}
