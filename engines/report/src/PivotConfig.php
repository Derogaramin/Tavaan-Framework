<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

/**
 * تَوان | Tavaan — Report
 * PivotConfig — پیکربندی جدول متقاطع (DOC-072 §6).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final readonly class PivotConfig
{
    /**
     * @param string|\Closure $rowField    نام فیلد یا fn(array $row): mixed
     * @param string|\Closure $columnField نام فیلد یا fn(array $row): mixed
     * @param string          $valueField  فیلد مقدار
     * @param string          $aggregate   sum|avg|count|min|max
     */
    public function __construct(
        public string|\Closure $rowField,
        public string|\Closure $columnField,
        public string $valueField,
        public string $aggregate = 'sum',
    ) {
    }

    /** @param array<string, mixed> $row */
    public function rowKey(array $row): mixed
    {
        return \is_string($this->rowField) ? ($row[$this->rowField] ?? null) : ($this->rowField)($row);
    }

    /** @param array<string, mixed> $row */
    public function columnKey(array $row): mixed
    {
        return \is_string($this->columnField) ? ($row[$this->columnField] ?? null) : ($this->columnField)($row);
    }
}
