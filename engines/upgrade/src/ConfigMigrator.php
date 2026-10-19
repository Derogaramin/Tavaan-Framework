<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

/**
 * تَوان | Tavaan — Upgrade
 * ConfigMigrator — مهاجرت کلیدهای پیکربندی در `.env` (DOC-081E §4).
 *
 * انواع تغییر: `rename` / `remove` / `default` (افزودن در صورت نبود) /
 * `transform` (بازنویسی مقدار با callback). ویرایش خط‌به‌خط است تا
 * کامنت‌ها و ساختار فایل حفظ شود؛ نوشتن اتمیک (tmp + rename).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class ConfigMigrator
{
    /** @var list<string> گزارش تغییرات اعمال‌شده */
    private array $log = [];

    public function __construct(private readonly string $envFile)
    {
    }

    /**
     * @param list<array{type: string, from?: string, to?: string, key?: string, value?: string, callback?: \Closure(string): string}> $changes
     *
     * @return list<string> توضیح تغییرات اعمال‌شده
     */
    public function migrate(array $changes): array
    {
        $this->log = [];
        if ($changes === []) {
            return [];
        }

        $lines = $this->readLines();
        $changed = false;

        foreach ($changes as $change) {
            $type = (string) ($change['type'] ?? '');
            $result = match ($type) {
                'rename' => $this->rename($lines, (string) ($change['from'] ?? ''), (string) ($change['to'] ?? '')),
                'remove' => $this->remove($lines, (string) ($change['key'] ?? '')),
                'default' => $this->setDefault($lines, (string) ($change['key'] ?? ''), (string) ($change['value'] ?? '')),
                'transform' => $this->transform($lines, (string) ($change['key'] ?? ''), $change['callback'] ?? null),
                default => false,
            };
            $changed = $changed || $result;
        }

        if ($changed) {
            $this->writeLines($lines);
        }

        return $this->log;
    }

    /** @return list<string> */
    public function log(): array
    {
        return $this->log;
    }

    // ─── عملیات ───────────────────────────────────────────────────────────

    /**
     * @param list<string> $lines
     */
    private function rename(array &$lines, string $from, string $to): bool
    {
        if ($from === '' || $to === '') {
            return false;
        }

        $found = false;
        foreach ($lines as $i => $line) {
            if (self::keyOf($line) === $from) {
                $value = self::valuePart($line);
                $lines[$i] = $to . '=' . $value;
                $found = true;
            }
        }
        if ($found) {
            $this->log[] = "rename: {$from} → {$to}";
        }

        return $found;
    }

    /** @param list<string> $lines */
    private function remove(array &$lines, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $before = \count($lines);
        $lines = \array_values(\array_filter($lines, static fn (string $line): bool => self::keyOf($line) !== $key));
        $removed = \count($lines) < $before;
        if ($removed) {
            $this->log[] = "remove: {$key}";
        }

        return $removed;
    }

    /** @param list<string> $lines */
    private function setDefault(array &$lines, string $key, string $value): bool
    {
        if ($key === '') {
            return false;
        }
        foreach ($lines as $line) {
            if (self::keyOf($line) === $key) {
                return false;   // موجود → دست نمی‌زنیم
            }
        }

        $lines[] = $key . '=' . $value;
        $this->log[] = "default: {$key} افزوده شد";

        return true;
    }

    /** @param list<string> $lines */
    private function transform(array &$lines, string $key, mixed $callback): bool
    {
        if ($key === '' || !$callback instanceof \Closure) {
            return false;
        }

        $found = false;
        foreach ($lines as $i => $line) {
            if (self::keyOf($line) === $key) {
                $lines[$i] = $key . '=' . $callback(self::valuePart($line));
                $found = true;
            }
        }
        if ($found) {
            $this->log[] = "transform: {$key}";
        }

        return $found;
    }

    // ─── .env line helpers ────────────────────────────────────────────────

    /** @return list<string> */
    private function readLines(): array
    {
        if (!\is_file($this->envFile)) {
            return [];
        }
        $contents = \file_get_contents($this->envFile);

        return \is_string($contents) ? \explode("\n", $contents) : [];
    }

    /** @param list<string> $lines */
    private function writeLines(array $lines): void
    {
        $dir = \dirname($this->envFile);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        $tmp = $this->envFile . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        \file_put_contents($tmp, \implode("\n", $lines), LOCK_EX);
        if (!\rename($tmp, $this->envFile)) {
            @\unlink($tmp);
            throw new \RuntimeException(\sprintf('Cannot write env file [%s].', $this->envFile));
        }
    }

    private static function keyOf(string $line): string
    {
        $trimmed = \ltrim($line);
        if ($trimmed === '' || \str_starts_with($trimmed, '#')) {
            return '';
        }
        $position = \strpos($trimmed, '=');
        if ($position === false) {
            return '';
        }

        return \trim(\substr($trimmed, 0, $position));
    }

    private static function valuePart(string $line): string
    {
        $position = \strpos($line, '=');

        return $position === false ? '' : \substr($line, $position + 1);
    }
}
