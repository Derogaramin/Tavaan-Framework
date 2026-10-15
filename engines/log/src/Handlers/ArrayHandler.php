<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Handlers;

use Tavaan\Core\Contracts\Log\HandlerInterface;

/**
 * تَوان | Tavaan — Log
 * ArrayHandler — ذخیره در حافظه (Fake تست).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class ArrayHandler implements HandlerInterface
{
    /** @var list<string> */
    private array $lines = [];

    public function write(string $line): void
    {
        $this->lines[] = $line;
    }

    public function writeBatch(array $lines): void
    {
        foreach ($lines as $line) {
            $this->lines[] = $line;
        }
    }

    public function close(): void
    {
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function flush(): void
    {
        $this->lines = [];
    }
}
