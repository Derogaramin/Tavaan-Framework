<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Report\Exceptions\ReportException;

/**
 * تَوان | Tavaan — Report
 * DatasetResolver — تبدیل dataset (Closure/QueryBuilder/SQL/آرایه) به ردیف‌ها
 * + اعمال فیلترهای allowlist‌شده (🔒 DOC-072 §8).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class DatasetResolver
{
    public function __construct(private readonly ?ConnectionManager $db = null)
    {
    }

    /**
     * @param \Closure|QueryBuilderInterface|array<string>|list<array<string, mixed>>|string $dataset
     * @param list<Filter>        $filters      تعریف فیلترها (allowlist)
     * @param array<string, mixed> $values      مقادیر نرمال‌شده (فقط کلیدهای اعلام‌شده)
     * @param list<string>        $knownFields  نام فیلدهای مجاز برای orderBy
     *
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     */
    public function resolve(
        mixed $dataset,
        array $filters,
        array $values,
        ?string $orderField,
        string $orderDir,
        int $maxRows,
        array $knownFields = [],
    ): array {
        if ($dataset instanceof \Closure) {
            return $this->resolve(
                $dataset(),
                $filters,
                $values,
                $orderField,
                $orderDir,
                $maxRows,
                $knownFields,
            );
        }

        if ($dataset instanceof QueryBuilderInterface) {
            return $this->resolveQuery($dataset, $filters, $values, $orderField, $orderDir, $maxRows, $knownFields);
        }

        if (\is_string($dataset)) {
            // SQL خام (توسعه‌دهنده مسئول است) — فیلترها سمت PHP اعمال می‌شوند
            $rows = $this->selectRaw($dataset);

            return $this->postProcess($rows, $filters, $values, $orderField, $orderDir, $maxRows, $knownFields);
        }

        if (\is_array($dataset)) {
            /** @var list<array<string, mixed>> $rows */
            $rows = \array_values($dataset);

            return $this->postProcess($rows, $filters, $values, $orderField, $orderDir, $maxRows, $knownFields);
        }

        throw ReportException::noDataset('-');
    }

    /**
     * @param list<Filter>         $filters
     * @param array<string, mixed> $values
     * @param list<string>         $knownFields
     *
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     */
    private function resolveQuery(
        QueryBuilderInterface $query,
        array $filters,
        array $values,
        ?string $orderField,
        string $orderDir,
        int $maxRows,
        array $knownFields,
    ): array {
        foreach ($filters as $filter) {
            if (!\array_key_exists($filter->name, $values) || $values[$filter->name] === null) {
                continue;
            }
            $this->applyQueryFilter($query, $filter, $values[$filter->name]);
        }

        if ($orderField !== null && $this->isSortable($orderField, $knownFields)) {
            $query->orderBy($orderField, \strtolower($orderDir) === 'desc' ? 'desc' : 'asc');
        }

        $rows = $query->limit($maxRows + 1)->get();
        $truncated = \count($rows) > $maxRows;
        if ($truncated) {
            $rows = \array_slice($rows, 0, $maxRows);
        }

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * @param list<Filter>         $filters
     * @param array<string, mixed> $values
     * @param list<array<string, mixed>> $rows
     * @param list<string>         $knownFields
     *
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     */
    private function postProcess(
        array $rows,
        array $filters,
        array $values,
        ?string $orderField,
        string $orderDir,
        int $maxRows,
        array $knownFields,
    ): array {
        foreach ($filters as $filter) {
            if (!\array_key_exists($filter->name, $values) || $values[$filter->name] === null) {
                continue;
            }
            $rows = $this->applyPhpFilter($rows, $filter, $values[$filter->name]);
        }

        if ($orderField !== null && $this->isSortable($orderField, $knownFields)) {
            $desc = \strtolower($orderDir) === 'desc';
            \usort($rows, static function (array $a, array $b) use ($orderField, $desc): int {
                $left = $a[$orderField] ?? null;
                $right = $b[$orderField] ?? null;
                $cmp = self::compareScalars($left, $right);

                return $desc ? -$cmp : $cmp;
            });
        }

        $truncated = \count($rows) > $maxRows;
        if ($truncated) {
            $rows = \array_slice($rows, 0, $maxRows);
        }

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * 🔒 فیلتر روی Query Builder — prepared (بدون الحاق مقدار).
     */
    private function applyQueryFilter(QueryBuilderInterface $query, Filter $filter, mixed $value): void
    {
        $field = $filter->field;
        if (!$this->isIdentifier($field)) {
            return;
        }

        switch ($filter->type) {
            case 'daterange':
                /** @var array{0: string, 1: string} $value */
                [$from, $to] = $value;
                if ($from !== '' && $to !== '') {
                    $query->whereBetween($field, [$this->startOfDay($from), $this->endOfDay($to)]);
                } elseif ($from !== '') {
                    $query->where($field, '>=', $this->startOfDay($from));
                } elseif ($to !== '') {
                    $query->where($field, '<=', $this->endOfDay($to));
                }
                break;

            case 'select':
                if (\is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, '=', $value);
                }
                break;

            case 'bool':
                $query->where($field, '=', $value ? 1 : 0);
                break;

            case 'number':
                $query->where($field, '=', $value);
                break;

            default:   // text → LIKE (با escape نویسه‌های wildcard)
                $escaped = \str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $value);
                $query->where($field, 'like', '%' . $escaped . '%');
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function applyPhpFilter(array $rows, Filter $filter, mixed $value): array
    {
        $field = $filter->field;

        return \array_values(\array_filter($rows, static function (array $row) use ($filter, $field, $value): bool {
            $cell = $row[$field] ?? null;

            return match ($filter->type) {
                'daterange' => self::inRange($cell, $value),
                'select' => \is_array($value)
                    ? \in_array($cell, $value, false)
                    : self::compareScalars($cell, $value) === 0,
                'bool' => (bool) $cell === (bool) $value,
                'number' => \is_numeric((string) $cell) && self::compareScalars($cell, $value) === 0,
                default => \str_contains(\strtolower((string) $cell), \strtolower((string) $value)),
            };
        }));
    }

    /** @param array{0: string, 1: string} $range */
    private static function inRange(mixed $cell, array $range): bool
    {
        $date = null;
        if ($cell instanceof \DateTimeInterface) {
            $date = $cell->format('Y-m-d H:i:s');
        } elseif (\is_string($cell) && $cell !== '') {
            $date = $cell;
        } elseif (\is_int($cell)) {
            $date = \date('Y-m-d H:i:s', $cell);
        }
        if ($date === null) {
            return false;
        }

        [$from, $to] = $range;
        if ($from !== '' && \strcmp($date, self::startOfDayStatic($from)) < 0) {
            return false;
        }
        if ($to !== '' && \strcmp($date, self::endOfDayStatic($to)) > 0) {
            return false;
        }

        return true;
    }

    private function startOfDay(string $date): string
    {
        return self::startOfDayStatic($date);
    }

    private function endOfDay(string $date): string
    {
        return self::endOfDayStatic($date);
    }

    private static function startOfDayStatic(string $date): string
    {
        return \strlen($date) === 10 ? $date . ' 00:00:00' : $date;
    }

    private static function endOfDayStatic(string $date): string
    {
        return \strlen($date) === 10 ? $date . ' 23:59:59' : $date;
    }

    private static function compareScalars(mixed $a, mixed $b): int
    {
        if (\is_numeric($a) && \is_numeric($b)) {
            return ($a + 0) <=> ($b + 0);
        }

        return \strcmp((string) $a, (string) $b);
    }

    /** 🔒 فقط شناسه‌های ستونی ساده (ضد تزریق در orderBy/فیلتر). */
    private function isIdentifier(string $field): bool
    {
        return \preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) === 1;
    }

    /**
     * @param list<string> $knownFields
     */
    private function isSortable(string $field, array $knownFields): bool
    {
        if (!$this->isIdentifier($field)) {
            return false;
        }

        return $knownFields === [] || \in_array($field, $knownFields, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectRaw(string $sql): array
    {
        if ($this->db === null) {
            throw ReportException::databaseUnavailable();
        }

        return $this->db->connection()->select($sql);
    }
}
