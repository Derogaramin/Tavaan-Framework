<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

/**
 * تَوان | Tavaan — Report
 * Field — ستون گزارش (DOC-072 §2).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final readonly class Field
{
    /**
     * @param string   $name      نام ستون در dataset
     * @param string   $label     برچسب نمایشی (پیش‌فرض = name)
     * @param string   $type      string|number|money|date|bool
     * @param string|null $format  قالب ویژه (مثلاً `jalali` برای date)
     * @param string|null $aggregate جمع کل: sum|avg|count|min|max
     * @param string   $currency  ارز برای type=money (ADR-0013)
     * @param \Closure|null $derive مقدار محاسبه‌شده: fn(array $row): mixed
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'string',
        public ?string $format = null,
        public ?string $aggregate = null,
        public string $currency = 'IRT',
        public ?\Closure $derive = null,
    ) {
    }

    public function isNumeric(): bool
    {
        return $this->type === 'number' || $this->type === 'money';
    }

    public function isDate(): bool
    {
        return $this->type === 'date';
    }
}
