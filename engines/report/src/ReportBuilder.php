<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Engine\Report\Exceptions\ReportException;

/**
 * تَوان | Tavaan — Report
 * ReportBuilder — ساخت گزارش: dataset/field/filter/groupBy/pivot/orderBy/run (DOC-072 §3).
 *
 * ### مثال
 * ```php
 * $report = Report::build('sales')
 *     ->dataset(fn () => DB::table('shop_orders')->select('created_at', 'total', 'status'))
 *     ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
 *     ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
 *     ->addFilter('date_range', field: 'created_at', type: 'daterange')
 *     ->groupBy('status', subtotal: ['total' => 'sum'])
 *     ->orderBy('total', 'desc');
 * $result = $report->run(filters: ['date_range' => [$from, $to]]);
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class ReportBuilder
{
    /** @var \Closure|QueryBuilderInterface|list<array<string, mixed>>|string|null */
    private mixed $dataset = null;

    /** @var list<Field> */
    private array $fields = [];

    /** @var list<Filter> */
    private array $filters = [];

    private ?string $groupField = null;

    /** @var array<string, string> فیلد → aggregate */
    private array $subtotals = [];

    private ?PivotConfig $pivot = null;

    private ?string $orderField = null;

    private string $orderDir = 'asc';

    private ?int $limit = null;

    /**
     * @param array<string, mixed> $config
     *
     * @internal — توسط ReportManager::build ساخته می‌شود
     */
    public function __construct(
        private readonly string $reportName,
        private readonly DatasetResolver $resolver,
        private readonly array $config = [],
    ) {
    }

    public function name(): string
    {
        return $this->reportName;
    }

    /**
     * منبع داده: Closure (lazy) / QueryBuilder / آرایهٔ ردیف‌ها / SQL خام.
     */
    public function dataset(\Closure|QueryBuilderInterface|array|string $dataset): self
    {
        $this->dataset = $dataset;

        return $this;
    }

    /**
     * افزودن ستون گزارش.
     *
     * @param string      $name      نام ستون در dataset
     * @param string|null $label     برچسب نمایشی (پیش‌فرض = name)
     * @param string      $type      string|number|money|date|bool
     * @param string|null $format    مثلاً `jalali` برای date
     * @param string|null $aggregate جمع کل: sum|avg|count|min|max
     */
    public function addField(
        string $name,
        ?string $label = null,
        string $type = 'string',
        ?string $format = null,
        ?string $aggregate = null,
        string $currency = 'IRT',
        ?\Closure $derive = null,
    ): self {
        if ($aggregate !== null && !\in_array($aggregate, Aggregator::FUNCTIONS, true)) {
            throw ReportException::unknownAggregate($aggregate);
        }

        $this->fields[] = new Field($name, $label ?? $name, $type, $format, $aggregate, $currency, $derive);

        return $this;
    }

    /**
     * افزودن فیلتر پویا (🔒 فقط فیلدهای اعلام‌شده فیلترپذیرند).
     *
     * @param list<mixed>|\UnitEnum[] $options مقادیر مجاز select
     */
    public function addFilter(
        string $name,
        ?string $field = null,
        string $type = 'text',
        array $options = [],
        mixed $default = null,
    ): self {
        $this->filters[] = new Filter($name, $field ?? $name, $type, $options, $default);

        return $this;
    }

    /**
     * گروه‌بندی + جمع جزء.
     *
     * @param array<string, string> $subtotal فیلد → aggregate (مثلاً `['total' => 'sum']`)
     */
    public function groupBy(string $field, array $subtotal = []): self
    {
        foreach ($subtotal as $subField => $function) {
            if (!\in_array($function, Aggregator::FUNCTIONS, true)) {
                throw ReportException::unknownAggregate((string) $function);
            }
        }

        $this->groupField = $field;
        $this->subtotals = $subtotal;

        return $this;
    }

    /** جدول متقاطع (DOC-072 §6). */
    public function pivot(
        string|\Closure $rows,
        string|\Closure $columns,
        string $values,
        string $aggregate = 'sum',
    ): self {
        if (!\in_array($aggregate, Aggregator::FUNCTIONS, true)) {
            throw ReportException::unknownAggregate($aggregate);
        }

        $this->pivot = new PivotConfig($rows, $columns, $values, $aggregate);

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->orderField = $field;
        $this->orderDir = \strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function limit(int $rows): self
    {
        $this->limit = \max(1, $rows);

        return $this;
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return list<Filter> */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * اجرای گزارش.
     *
     * @param array<string, mixed> $filters مقادیر فیلتر (کلیدهای ناشناخته 🔒 نادیده)
     */
    public function run(array $filters = []): ReportResult
    {
        if ($this->dataset === null) {
            throw ReportException::noDataset($this->reportName);
        }

        // نرمال‌سازی مقادیر فیلتر (فقط allowlist)
        $values = [];
        foreach ($this->filters as $filter) {
            $values[$filter->name] = \array_key_exists($filter->name, $filters)
                ? $filter->normalize($filters[$filter->name])
                : $filter->normalize(null);
        }

        $maxRows = $this->effectiveMaxRows();
        $knownFields = $this->fields === []
            ? []
            : \array_map(static fn (Field $f): string => $f->name, $this->fields);

        ['rows' => $rows, 'truncated' => $truncated] = $this->resolver->resolve(
            $this->dataset,
            $this->filters,
            $values,
            $this->orderField,
            $this->orderDir,
            $maxRows,
            $knownFields,
        );

        // تجمیع‌ها روی ردیف‌های خام (پیش از projection) — فیلدهای pivot/group
        // ممکن است در فیلدهای نمایشی اعلام نشده باشند.
        $totals = $this->computeTotals($rows);
        $subtotals = $this->groupField === null ? [] : $this->computeSubtotals($rows);
        $pivot = $this->pivot === null ? null : (new PivotBuilder())->build($rows, $this->pivot);

        $rows = $this->project($rows);

        return new ReportResult(
            name: $this->reportName,
            rows: $rows,
            fields: $this->fields,
            totals: $totals,
            subtotals: $subtotals,
            pivot: $pivot,
            groupField: $this->groupField,
            truncated: $truncated,
            maxRows: $maxRows,
        );
    }

    /**
     * projection روی فیلدهای اعلام‌شده + محاسبهٔ derive.
     *
     * اگر فیلدی اعلام نشده باشد، ردیف‌ها همان ستون‌های خام dataset را دارند.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function project(array $rows): array
    {
        if ($this->fields === []) {
            return $rows;
        }

        $out = [];
        foreach ($rows as $row) {
            $projected = [];
            foreach ($this->fields as $field) {
                $projected[$field->name] = $field->derive !== null
                    ? ($field->derive)($row)
                    : ($row[$field->name] ?? null);
            }
            $out[] = $projected;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int|float|string|null>
     */
    private function computeTotals(array $rows): array
    {
        $totals = [];
        foreach ($this->fields as $field) {
            if ($field->aggregate !== null) {
                $totals[$field->name] = Aggregator::aggregate($rows, $field->name, $field->aggregate);
            }
        }

        return $totals;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, int|float>>
     */
    private function computeSubtotals(array $rows): array
    {
        if ($this->groupField === null || $this->subtotals === []) {
            return [];
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$this->groupField] ?? '');
            $groups[$key][] = $row;
        }

        $subtotals = [];
        foreach ($groups as $key => $groupRows) {
            foreach ($this->subtotals as $field => $function) {
                $value = Aggregator::aggregate($groupRows, (string) $field, (string) $function);
                $subtotals[$key][(string) $field] = $value === null ? 0 : ($value + 0);
            }
        }

        return $subtotals;
    }

    private function effectiveMaxRows(): int
    {
        $configured = (int) ($this->config['max_rows'] ?? 100_000);

        return $this->limit === null ? \max(1, $configured) : \min($this->limit, \max(1, $configured));
    }
}
