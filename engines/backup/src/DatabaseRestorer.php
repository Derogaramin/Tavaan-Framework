<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

use Tavaan\Core\Contracts\Database\ConnectionInterface;

/**
 * تَوان | Tavaan — Backup
 * DatabaseRestorer — اجرای dump SQL تولیدشده توسط DatabaseDumper.
 *
 * 🔒 تفکیک گزاره‌ها با state-machine آگاه از quote — `;` داخل رشته‌ها
 * مرز گزاره نیست (هر دو سبک escape: '' و \').
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class DatabaseRestorer
{
    // 🔒 حالت پیمایش — بین chunkهای streaming حفظ می‌شود
    private bool $inSingle = false;

    private bool $inDouble = false;

    private bool $inLineComment = false;

    private bool $inBlockComment = false;

    /**
     * اجرای کل dump (رشته) روی اتصال.
     *
     * @return int تعداد گزاره‌های اجراشده
     */
    public function restoreFromString(ConnectionInterface $conn, string $sql): int
    {
        $count = 0;
        foreach ($this->splitStatements($sql) as $statement) {
            if ($this->shouldSkip($statement)) {
                continue;
            }
            $conn->statement($statement);
            $count++;
        }

        return $count;
    }

    /** اجرای dump از مسیر فایل (streaming — حافظهٔ ثابت). */
    public function restoreFromFile(ConnectionInterface $conn, string $path): int
    {
        $handle = \fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open dump file: ' . $path);
        }

        $this->resetState();

        try {
            $count = 0;
            $buffer = '';
            while (!\feof($handle)) {
                $chunk = \fread($handle, 65536);
                if ($chunk === false) {
                    break;
                }
                $buffer .= $chunk;

                // گزاره‌های کامل را از buffer خارج و اجرا کن
                [$statements, $buffer] = $this->extractComplete($buffer);
                foreach ($statements as $statement) {
                    if (!$this->shouldSkip($statement)) {
                        $conn->statement($statement);
                        $count++;
                    }
                }
            }

            // باقی‌ماندهٔ نهایی
            foreach ($this->splitStatements($buffer) as $statement) {
                if (!$this->shouldSkip($statement)) {
                    $conn->statement($statement);
                    $count++;
                }
            }

            return $count;
        } finally {
            \fclose($handle);
        }
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * تفکیک رشتهٔ SQL به گزاره‌ها (quote-aware).
     *
     * @return list<string>
     */
    public function splitStatements(string $sql): array
    {
        $this->resetState();
        [$statements, $remainder] = $this->extractComplete($sql);

        if (\trim($remainder) !== '') {
            $statements[] = $remainder;
        }

        return \array_values(\array_filter($statements, static fn (string $s): bool => \trim($s) !== ''));
    }

    /**
     * پیمایش stream و استخراج گزاره‌های کامل.
     *
     * 🔒 حالت quote در propertyهای نمونه نگه داشته می‌شود تا رشتهٔ
     * quote‌دارِ spanning بین دو chunk درست پیمایش شود.
     *
     * @return array{0: list<string>, 1: string} [گزاره‌های کامل, باقی‌ماندهٔ ناتمام]
     */
    private function extractComplete(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = \strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($this->inLineComment) {
                $current .= $char;
                if ($char === "\n") {
                    $this->inLineComment = false;
                }
                continue;
            }

            if ($this->inBlockComment) {
                $current .= $char;
                if ($char === '*' && $next === '/') {
                    $current .= $next;
                    $i++;
                    $this->inBlockComment = false;
                }
                continue;
            }

            if ($this->inSingle) {
                $current .= $char;
                if ($char === '\\') {
                    // escape backslash (MySQL) — کاراکتر بعدی مصرف می‌شود
                    if ($next !== '') {
                        $current .= $next;
                        $i++;
                    }
                    continue;
                }
                if ($char === "'") {
                    if ($next === "'") {
                        $current .= $next;   // '' escape (SQL استاندارد)
                        $i++;
                        continue;
                    }
                    $this->inSingle = false;
                }
                continue;
            }

            if ($this->inDouble) {
                $current .= $char;
                if ($char === '\\') {
                    if ($next !== '') {
                        $current .= $next;
                        $i++;
                    }
                    continue;
                }
                if ($char === '"') {
                    if ($next === '"') {
                        $current .= $next;
                        $i++;
                        continue;
                    }
                    $this->inDouble = false;
                }
                continue;
            }

            // خارج از quote
            if ($char === '-' && $next === '-') {
                $this->inLineComment = true;
                $current .= $char;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $this->inBlockComment = true;
                $current .= $char;
                continue;
            }
            if ($char === "'") {
                $this->inSingle = true;
                $current .= $char;
                continue;
            }
            if ($char === '"') {
                $this->inDouble = true;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        return [$statements, $current];
    }

    private function resetState(): void
    {
        $this->inSingle = false;
        $this->inDouble = false;
        $this->inLineComment = false;
        $this->inBlockComment = false;
    }

    /** گزاره‌های غیرقابل‌اجرا (comment-only / SET NAMES در sqlite). */
    private function shouldSkip(string $statement): bool
    {
        $clean = $this->stripComments($statement);

        return \trim($clean) === '';
    }

    private function stripComments(string $sql): string
    {
        $lines = [];
        foreach (\explode("\n", $sql) as $line) {
            $trimmed = \ltrim($line);
            if (\str_starts_with($trimmed, '--')) {
                continue;
            }
            $lines[] = $line;
        }

        return \implode("\n", $lines);
    }
}
