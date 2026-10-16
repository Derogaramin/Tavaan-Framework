<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Engine\Compat\Decimal;

/**
 * تَوان | Tavaan — Report
 * PivotBuilder — جدول متقاطع (DOC-072 §6).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class PivotBuilder
{
    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array{columns: list<string>, data: array<string, array<string, int|float|null>>}
     */
    public function build(array $rows, PivotConfig $config): array
    {
        $columns = [];
        /** @var array<string, array<string, list<int|float>>> $cells */
        $cells = [];

        foreach ($rows as $row) {
            $columnKey = $config->columnKey($row);
            if ($columnKey === null) {
                continue;
            }
            $columnKey = (string) $columnKey;
            $rowKey = (string) $config->rowKey($row);
            $columns[$columnKey] = true;

            $value = $row[$config->valueField] ?? null;
            $cells[$rowKey][$columnKey][] = \is_int($value) || \is_float($value)
                || (\is_string($value) && \is_numeric($value))
                ? $value + 0
                : ($value === null ? 0 : 1);
        }

        $columnList = \array_keys($columns);
        \sort($columnList);

        /** @var array<string, array<string, int|float|null>> $data */
        $data = [];
        foreach ($cells as $rowKey => $rowCells) {
            foreach ($columnList as $column) {
                $data[$rowKey][$column] = isset($rowCells[$column])
                    ? $this->aggregate($rowCells[$column], $config->aggregate)
                    : null;
            }
        }
        \ksort($data);

        return ['columns' => $columnList, 'data' => $data];
    }

    /** @param list<int|float> $values */
    private function aggregate(array $values, string $function): int|float
    {
        return match ($function) {
            'count' => \count($values),
            'min' => $this->scalar(\min($values)),
            'max' => $this->scalar(\max($values)),
            'avg' => $this->avg($values),
            default => $this->sum($values),   // sum
        };
    }

    /** @param list<int|float> $values */
    private function sum(array $values): int|float
    {
        $total = Decimal::of(0);
        foreach ($values as $value) {
            $total = $total->add(Decimal::of((string) $value));
        }

        return $this->scalar((string) $total);
    }

    /** @param list<int|float> $values */
    private function avg(array $values): int|float
    {
        if ($values === []) {
            return 0;
        }

        return $this->scalar(\round($this->sum($values) / \count($values), 2));
    }

    private function scalar(int|float|string $value): int|float
    {
        if (\is_string($value)) {
            $value = (float) $value;
        }
        if (\is_float($value) && $value === \floor($value) && \abs($value) < 9.0e+15) {
            return (int) $value;
        }

        return $value;
    }
}
