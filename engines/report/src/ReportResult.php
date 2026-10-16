<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

/**
 * تَوان | Tavaan — Report
 * ReportResult — نتیجهٔ اجرای گزارش (DOC-072 §3): rows/totals/subtotals/pivot.
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class ReportResult
{
    /**
     * @param list<array<string, mixed>>              $rows      ردیف‌های خام (image فقط فیلدهای اعلام‌شده)
     * @param list<Field>                             $fields
     * @param array<string, int|float|string|null>    $totals    جمع کل فیلدهای aggregateدار
     * @param array<string, array<string, int|float>> $subtotals جمع جزء per group
     * @param array{columns: list<string>, data: array<string, array<string, int|float|null>>}|null $pivot
     */
    public function __construct(
        private readonly string $name,
        private readonly array $rows,
        private readonly array $fields,
        private readonly array $totals = [],
        private readonly array $subtotals = [],
        private readonly ?array $pivot = null,
        private readonly ?string $groupField = null,
        private readonly bool $truncated = false,
        private readonly int $maxRows = 0,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<array<string, mixed>> ردیف‌های خام */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return list<string> برچسب ستون‌ها */
    public function headers(): array
    {
        return \array_map(static fn (Field $f): string => $f->label, $this->fields);
    }

    /** @return array<string, int|float|string|null> */
    public function totals(): array
    {
        return $this->totals;
    }

    /** @return array<string, array<string, int|float>> */
    public function subtotals(): array
    {
        return $this->subtotals;
    }

    /** @return array{columns: list<string>, data: array<string, array<string, int|float|null>>}|null */
    public function pivot(): ?array
    {
        return $this->pivot;
    }

    public function groupField(): ?string
    {
        return $this->groupField;
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    /** آیا نتیجه به‌خاطر max_rows بریده شده؟ */
    public function truncated(): bool
    {
        return $this->truncated;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * پیمایش تکه‌ای (Streaming برای دادهٔ بزرگ — DOC-072 §5).
     *
     * @return \Generator<int, list<array<string, mixed>>>
     */
    public function chunked(int $size = 1000): \Generator
    {
        $size = \max(1, $size);
        foreach (\array_chunk($this->rows, $size) as $chunk) {
            yield $chunk;
        }
    }

    /** ردیفهای نمایشی (قالب‌بندی‌شده با Formatter). @return list<array<string, mixed>> */
    public function formattedRows(?Formatter $formatter = null): array
    {
        $formatter ??= new Formatter();
        $out = [];
        foreach ($this->rows as $row) {
            $formatted = [];
            foreach ($this->fields as $field) {
                $formatted[$field->name] = $formatter->format($row[$field->name] ?? null, $field);
            }
            $out[] = $formatted;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return \array_filter([
            'name' => $this->name,
            'headers' => $this->headers(),
            'rows' => $this->rows,
            'totals' => $this->totals === [] ? null : $this->totals,
            'subtotals' => $this->subtotals === [] ? null : $this->subtotals,
            'pivot' => $this->pivot,
            'group_field' => $this->groupField,
            'count' => $this->count(),
            'truncated' => $this->truncated ?: null,
            'max_rows' => $this->maxRows,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
