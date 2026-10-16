<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

/**
 * تَوان | Tavaan — Tenancy
 * TenantQueryGuard — اسکن Query Log برای کوئری‌های بدون tenant scope.
 *
 * منطق: برای هر SELECT/UPDATE/DELETE، جدول اصلی استخراج می‌شود؛
 * اگر جدول در فهرست ignore نباشد و عبارت `tenant_id` (یا هر
 * `{table}.tenant_id`) در SQL غایب باشد → تخلف.
 *
 * ⚠️ heuristic مبتنی بر متن SQL — برای گزارش‌گیری/CI مناسب است،
 * نه کنترل امنیتی زمان‌اجرا (آن را TenantScope تضمین می‌کند).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantQueryGuard
{
    private function __construct()
    {
    }

    /**
     * @param list<array<string, mixed>> $queries    رکوردهای QueryLog (sql/bindings/…)
     * @param list<string>               $ignore     جداول سراسری
     * @param list<string>|null          $onlyTables محدود به این جداول (null = همه)
     *
     * @return list<array{table: string, sql: string}>
     */
    public static function scan(array $queries, array $ignore = [], ?array $onlyTables = null, string $column = 'tenant_id'): array
    {
        $violations = [];

        foreach ($queries as $record) {
            $sql = (string) ($record['sql'] ?? ($record['query'] ?? ''));
            if ($sql === '') {
                continue;
            }

            $normalized = \preg_replace('/\s+/', ' ', $sql) ?? $sql;
            if (\preg_match('/^\s*(SELECT|UPDATE|DELETE)/i', $normalized) !== 1) {
                continue;   // INSERTها با Observer پر می‌شوند؛ DDL خارج از scope
            }

            $table = self::mainTable($normalized);
            if ($table === null) {
                continue;
            }
            if (\in_array($table, $ignore, true)) {
                continue;
            }
            if ($onlyTables !== null && !\in_array($table, $onlyTables, true)) {
                continue;
            }

            // tenant_id در هر جای WHERE/HAVING کافی است (heuristic)
            if (\preg_match('/\b' . \preg_quote($column, '/') . '\b/i', $normalized) === 1) {
                continue;
            }

            $violations[] = ['table' => $table, 'sql' => \mb_substr($sql, 0, 200)];
        }

        return $violations;
    }

    /** استخراج نام جدول اصلی از SQL (FROM/UPDATE/DELETE FROM). */
    private static function mainTable(string $sql): ?string
    {
        if (\preg_match('/\bFROM\s+[`"\[]?([A-Za-z0-9_]+)[`"\]]?/i', $sql, $m) === 1) {
            return $m[1];
        }
        if (\preg_match('/^\s*UPDATE\s+[`"\[]?([A-Za-z0-9_]+)[`"\]]?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
