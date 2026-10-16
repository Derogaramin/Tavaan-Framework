<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration;

use Tavaan\Core\Contracts\Cache\LockInterface;

/**
 * تَوان | Tavaan — Migration
 * FileMigrationLock — قفل اتمیک فایل‌محور (flock) برای اجرای migration.
 *
 * بدون وابستگی به موتور cache (M3 مستقل است)؛ قرارداد LockInterface را
 * پیاده می‌کند تا در M4 بتوان قفل cache/redis را جایگزین کرد.
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class FileMigrationLock implements LockInterface
{
    /** @var resource|null */
    private $handle = null;

    private bool $acquired = false;

    public function __construct(
        private readonly string $path,
        private readonly int $seconds = 300,
        private readonly string $ownerId = '',
    ) {
    }

    public function acquire(?int $timeout = null): bool
    {
        $this->ensureFresh();

        $dir = \dirname($this->path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        $handle = \fopen($this->path, 'c');
        if ($handle === false) {
            return false;
        }

        $deadline = $timeout === null ? 0 : \microtime(true) + $timeout;

        do {
            if (\flock($handle, LOCK_EX | LOCK_NB)) {
                $this->handle = $handle;
                $this->acquired = true;
                \ftruncate($handle, 0);
                \fwrite($handle, (string) \json_encode([
                    'owner' => $this->ownerId !== '' ? $this->ownerId : self::defaultOwner(),
                    'time'  => \time(),
                    'ttl'   => $this->seconds,
                ]));
                \fflush($handle);

                return true;
            }

            if ($timeout === null) {
                \fclose($handle);

                return false;
            }

            \usleep(100_000);
        } while (\microtime(true) < $deadline);

        \fclose($handle);

        return false;
    }

    public function block(int $timeout, ?callable $callback = null): mixed
    {
        if (!$this->acquire($timeout)) {
            throw new Exceptions\MigrationLockedException();
        }

        try {
            return $callback === null ? true : $callback();
        } finally {
            $this->release();
        }
    }

    public function release(): bool
    {
        if ($this->handle !== null && $this->acquired) {
            \flock($this->handle, LOCK_UN);
            \fclose($this->handle);
        }
        $this->handle = null;
        $this->acquired = false;

        return true;
    }

    public function forceRelease(): bool
    {
        $this->release();
        if (\is_file($this->path)) {
            @\unlink($this->path);
        }

        return true;
    }

    public function owner(): string
    {
        return $this->ownerId !== '' ? $this->ownerId : self::defaultOwner();
    }

    /** قفل بی‌مالک/منقضی (crash) → حذف تا اجرای بعدی block نشود. */
    private function ensureFresh(): void
    {
        if (!\is_file($this->path)) {
            return;
        }

        $age = \time() - (int) \filemtime($this->path);
        if ($age > $this->seconds) {
            @\unlink($this->path);
        }
    }

    private static function defaultOwner(): string
    {
        return \gethostname() . ':' . \getmypid();
    }
}
