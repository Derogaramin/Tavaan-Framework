<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Handlers;

use Tavaan\Core\Contracts\Log\HandlerInterface;
use Tavaan\Engine\Log\Exceptions\LogException;

/**
 * تَوان | Tavaan — Log
 * StreamHandler — نوشتن در یک فایل (driver: single) با append اتمیک.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
class StreamHandler implements HandlerInterface
{
    /** @var resource|null */
    protected $stream = null;

    public function __construct(
        protected readonly string $path,
        protected readonly int $permission = 0640,
        protected readonly bool $locking = true,
    ) {
    }

    public function write(string $line): void
    {
        $this->ensureDirectory();

        $flags = $this->locking ? LOCK_EX : 0;
        $result = \file_put_contents($this->path, $line, FILE_APPEND | $flags);

        if ($result === false) {
            throw LogException::writeFailed($this->path, 'file_put_contents failed (disk full?)');
        }

        $this->ensurePermission();
    }

    public function writeBatch(array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->write(\implode('', $lines));
    }

    public function close(): void
    {
        if (\is_resource($this->stream)) {
            \fclose($this->stream);
            $this->stream = null;
        }
    }

    protected function ensureDirectory(): void
    {
        $dir = \dirname($this->path);
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            throw LogException::writeFailed($this->path, 'unable to create log directory');
        }
    }

    protected function ensurePermission(): void
    {
        if (\file_exists($this->path)) {
            @\chmod($this->path, $this->permission);
        }
    }
}
