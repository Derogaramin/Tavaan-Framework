<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log;

use Tavaan\Core\Contracts\Log\HandlerInterface;

/**
 * تَوان | Tavaan — Log
 * Buffer — بافر نوشتن برای کاهش I/O (flush در shutdown/terminate).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class Buffer
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly int $maxLines = 50,
        private readonly bool $flushOnShutdown = true,
    ) {
        if ($this->flushOnShutdown) {
            \register_shutdown_function($this->flush(...));
        }
    }

    public function write(string $line): void
    {
        $this->lines[] = $line;
        if (\count($this->lines) >= $this->maxLines) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->lines === []) {
            return;
        }

        $batch = $this->lines;
        $this->lines = [];

        try {
            $this->handler->writeBatch($batch);
        } catch (\Throwable) {
            // در shutdown امکان گزارش نیست — سکوت برای جلوگیری از حلقه
        }
    }

    public function pending(): int
    {
        return \count($this->lines);
    }
}
