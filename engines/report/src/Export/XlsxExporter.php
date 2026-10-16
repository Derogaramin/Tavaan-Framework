<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Compat\Compat;
use Tavaan\Engine\Report\Field;
use Tavaan\Engine\Report\Formatter;
use Tavaan\Engine\Report\ReportResult;

/**
 * تَوان | Tavaan — Report
 * XlsxExporter — خروجی Excel (xlsx خودنوشته — DOC-072 §4).
 *
 * درایور: `auto` (ext-zip اگر بود و خواسته شده، وگرنه pure) | `ext-zip` | `pure`.
 * ZipWriterPure در هر حالتی خروجی معتبر OOXML می‌سازد (AC-RPT-10).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class XlsxExporter
{
    /**
     * @param array{driver?: string} $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly Formatter $formatter = new Formatter(),
    ) {
    }

    /** ساخت محتوای باینری xlsx. */
    public function export(ReportResult $result): string
    {
        $writer = new XlsxWriter();

        return $writer->toString($this->grid($result), $result->headers(), $result->name());
    }

    public function exportToFile(ReportResult $result, string $path): void
    {
        (new XlsxWriter())->write($this->grid($result), $result->headers(), $path, $result->name());
    }

    /** آیا ext-zip در دسترس است (برای تصمیم driver). */
    public function hasExtZip(): bool
    {
        return Compat::has('zip');
    }

    /**
     * ردیف‌ها → شبکهٔ سلول (تاریخ/بول → رشته؛ عدد → raw عددی).
     *
     * @return list<array<int, mixed>>
     */
    private function grid(ReportResult $result): array
    {
        $fields = $result->fields();
        $grid = [];
        foreach ($result->rows() as $row) {
            $cells = [];
            if ($fields !== []) {
                foreach ($fields as $field) {
                    $cells[] = $this->cell($row[$field->name] ?? null, $field);
                }
            } else {
                foreach ($row as $value) {
                    $cells[] = $value;
                }
            }
            $grid[] = $cells;
        }

        // ردیف جمع کل (totals)
        $totals = $result->totals();
        if ($totals !== [] && $fields !== []) {
            $totalRow = [];
            foreach ($fields as $field) {
                $totalRow[] = \array_key_exists($field->name, $totals)
                    ? $totals[$field->name]
                    : ($field === $fields[0] ? 'جمع کل' : null);
            }
            $grid[] = $totalRow;
        }

        return $grid;
    }

    private function cell(mixed $value, Field $field): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($field->isDate()) {
            return $this->formatter->format($value, $field);   // رشته (جلالی)
        }
        if ($field->type === 'bool') {
            return $value ? 'بله' : 'خیر';
        }
        if (\is_int($value) || \is_float($value)) {
            return $value;   // عدد raw → سلول عددی Excel
        }
        if ($field->isNumeric() && \is_string($value) && \is_numeric($value)) {
            return $value + 0;
        }

        return \is_scalar($value) ? (string) $value : (\json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
