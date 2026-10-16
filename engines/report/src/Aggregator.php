<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Engine\Compat\Decimal;
use Tavaan\Engine\Report\Exceptions\ReportException;

/**
 * تَوان | Tavaan — Report
 * Aggregator — تجمیع sum/avg/count/min/max (بدون float برای پول — ADR-0013).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class Aggregator
{
    public const FUNCTIONS = ['sum', 'avg', 'count', 'min', 'max'];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function aggregate(array $rows, string $field, string $function): int|float|string|null
    {
        if (!\in_array($function, self::FUNCTIONS, true)) {
            throw ReportException::unknownAggregate($function);
        }

        if ($function === 'count') {
            $count = 0;
            foreach ($rows as $row) {
                if (($row[$field] ?? null) !== null) {
                    $count++;
                }
            }

            return $count;
        }

        $values = [];
        foreach ($rows as $row) {
            $value = $row[$field] ?? null;
            if (\is_int($value) || \is_float($value) || (\is_string($value) && \is_numeric($value))) {
                $values[] = $value;
            }
        }
        if ($values === []) {
            return null;
        }

        return match ($function) {
            'sum' => self::sum($values),
            'avg' => self::avg($values),
            'min' => self::scalar(min($values)),
            'max' => self::scalar(max($values)),
            default => null,
        };
    }

    /**
     * جمع دقیق با Decimal (بدون خطای float روی مبالغ).
     *
     * @param list<int|float|string> $values
     */
    private static function sum(array $values): int|float
    {
        $total = Decimal::of(0);
        foreach ($values as $value) {
            $total = $total->add(Decimal::of((string) $value));
        }

        return self::toScalar($total);
    }

    /**
     * @param list<int|float|string> $values
     */
    private static function avg(array $values): int|float
    {
        $total = self::sum($values);

        return self::scalar(\count($values) > 0 ? $total / \count($values) : 0);
    }

    /** Decimal → int اگر صحیح، وگرنه float. */
    private static function toScalar(Decimal $decimal): int|float
    {
        $string = (string) $decimal->rescale(2);
        if (\str_ends_with(rtrim($string, '0'), '.') || $string === '' || $string === '-') {
            $string = rtrim($string, '.');
        }

        return self::scalar($string === '' ? 0 : $string + 0);
    }

    private static function scalar(int|float|string $value): int|float
    {
        if (\is_string($value)) {
            $value = (float) $value;
        }
        if (\is_float($value) && $value === \floor($value) && \abs($value) < PHP_FLOAT_MAX) {
            return (int) $value;
        }

        return $value;
    }
}
