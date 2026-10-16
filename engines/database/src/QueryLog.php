<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

use Tavaan\Core\Error\LogMasker;
use Tavaan\Core\Contracts\Log\LoggerInterface;

/**
 * تَوان | Tavaan — Database
 * QueryLog — ثبت کوئری‌ها + Slow Query Detection + N+1 Hint (DOC-047 §8).
 *
 * 🔒 فقط در dev/testing فعال شود (سربار).
 * 🔒 bindings با LogMasker ماسک می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class QueryLog
{
    /** @var list<QueryRecord> */
    private array $queries = [];

    /** @var array<string, int> شمارش SQL نرمال‌شده برای تشخیص N+1 */
    private array $seen = [];

    public function __construct(
        private readonly bool $enabled = false,
        private readonly float $slowThresholdMs = 100.0,
        private readonly int $maxEntries = 500,
        private readonly bool $n1Detection = false,
        private readonly int $n1Threshold = 5,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?\Tavaan\Core\Contracts\Events\DispatcherInterface $events = null,
    ) {
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function record(string $sql, array $bindings, float $timeMs, string $connection): void
    {
        if (!$this->enabled) {
            return;
        }

        $record = new QueryRecord($sql, LogMasker::mask($bindings), $timeMs, $connection, $this->backtrace());
        $this->queries[] = $record;

        $this->events?->dispatch('database.query', $record->toArray());

        if ($timeMs > $this->slowThresholdMs) {
            $this->logger?->warning('database.slow_query', $record->toArray());
            $this->events?->dispatch('database.slow_query', $record->toArray());
        }

        if (\count($this->queries) > $this->maxEntries) {
            \array_shift($this->queries);
        }

        if ($this->n1Detection) {
            $this->detectNPlusOne($record);
        }
    }

    /** @return list<QueryRecord> */
    public function all(): array
    {
        return $this->queries;
    }

    /** @return list<array{sql: string, bindings: array<int|string, mixed>, time: float, connection: string}> */
    public function toArray(): array
    {
        return \array_map(static fn (QueryRecord $r): array => $r->toArray(), $this->queries);
    }

    public function flush(): void
    {
        $this->queries = [];
        $this->seen = [];
    }

    private function detectNPlusOne(QueryRecord $r): void
    {
        $normalized = self::normalize($r->sql);
        $count = ($this->seen[$normalized] ?? 0) + 1;
        $this->seen[$normalized] = $count;

        if ($count === $this->n1Threshold) {
            $context = [
                'sql'   => $r->sql,
                'count' => $count,
                'trace' => $r->trace,
                'hint'  => 'از eager loading (with()) استفاده کنید.',
            ];
            $this->logger?->warning('database.n_plus_one', $context);
            $this->events?->dispatch('database.n_plus_one', $context);
        }
    }

    /** نرمال‌سازی SQL: مقادیر لیترال → ? تا کوئریهای هم‌شکل یکی شوند. */
    public static function normalize(string $sql): string
    {
        $sql = \preg_replace('/\s+/', ' ', \trim($sql)) ?? $sql;
        // رشته‌های لیترال
        $sql = (string) \preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql);
        // اعداد
        $sql = (string) \preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql);
        // گروه‌های IN (?, ?, ?) → IN (?)
        return (string) \preg_replace('/\(\s*\?(?:\s*,\s*\?)*\s*\)/', '(?)', $sql);
    }

    /** @return list<string> */
    private function backtrace(): array
    {
        $frames = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14);
        $out = [];
        foreach ($frames as $f) {
            $file = (string) ($f['file'] ?? '');
            if ($file === '' || \str_contains($file, '/engines/database/src/')) {
                continue;
            }
            $out[] = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . ' @' . \basename($file) . ':' . ($f['line'] ?? 0);
            if (\count($out) >= 4) {
                break;
            }
        }

        return $out;
    }
}
