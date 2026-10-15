<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Handlers;

use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Log
 * RotatingFileHandler — چرخش روزانه با Retention (driver: daily).
 *
 * الگوی نام: `{base}-{Y-m-d}.log`؛ حذف فایل‌های قدیمی‌تر از `days` (idempotent rename).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class RotatingFileHandler extends StreamHandler
{
    private string $currentFile = '';

    public function __construct(
        string $path,
        private readonly int $days = 14,
        int $permission = 0640,
        bool $locking = true,
    ) {
        parent::__construct($path, $permission, $locking);
    }

    public function write(string $line): void
    {
        $file = $this->rotatedPath();
        if ($file !== $this->currentFile) {
            $this->currentFile = $file;
            $this->rotate();
        }

        parent::write($line);
    }

    /** مسیر فایل امروز بر اساس الگو. */
    private function rotatedPath(): string
    {
        $dir = \dirname($this->path);
        $base = \basename($this->path, '.log');
        $date = Clock::utcNow()->format('Y-m-d');

        return $dir . '/' . $base . '-' . $date . '.log';
    }

    /** حذف فایل‌های قدیمی‌تر از retention. */
    private function rotate(): void
    {
        if ($this->days <= 0) {
            return;
        }

        $dir = \dirname($this->path);
        $base = \basename($this->path, '.log');
        $cutoff = Clock::utcNow()->modify('-' . $this->days . ' days')->format('Y-m-d');

        foreach (\glob($dir . '/' . $base . '-*.log') ?: [] as $file) {
            if (\preg_match('/-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m) === 1 && $m[1] < $cutoff) {
                @\unlink($file);
            }
        }
    }
}
