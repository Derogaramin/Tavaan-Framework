<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Engine\Report\Exceptions\ReportException;

/**
 * تَوان | Tavaan — Report
 * Filter — فیلتر پویا گزارش (DOC-072 §2/§3).
 *
 * 🔒 فقط فیلدهای اعلام‌شده (allowlist) قابل فیلترند.
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final readonly class Filter
{
    public const TYPES = ['text', 'select', 'daterange', 'number', 'bool'];

    /**
     * @param string              $name    نام فیلتر (کلید ورودی run())
     * @param string              $field   ستون هدف (پیش‌فرض = name)
     * @param string              $type    text|select|daterange|number|bool
     * @param list<mixed>         $options مقادیر مجاز select (خالی = بدون محدودیت)
     * @param mixed               $default مقدار پیش‌فرض
     */
    public function __construct(
        public string $name,
        public string $field,
        public string $type = 'text',
        public array $options = [],
        public mixed $default = null,
    ) {
        if (!\in_array($type, self::TYPES, true)) {
            throw ReportException::unknownFilterType($type);
        }
    }

    /**
     * نرمال‌سازی مقدار ورودی کاربر (null/خالی → اعمال نشود).
     *
     * 🔒 select با options: مقدار خارج از فهرست → null (رد بی‌صدا).
     */
    public function normalize(mixed $value): mixed
    {
        $value ??= $this->default;
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($this->type) {
            'daterange' => $this->normalizeRange($value),
            'select' => $this->normalizeSelect($value),
            'bool' => \is_bool($value) ? $value : \in_array((string) $value, ['1', 'true', 'yes', 'on'], true),
            'number' => \is_numeric($value) ? $value + 0 : null,
            default => \is_scalar($value) ? (string) $value : null,
        };
    }

    /** @return array{0: string, 1: string}|null */
    private function normalizeRange(mixed $value): ?array
    {
        if (\is_array($value)) {
            $from = $value['from'] ?? $value[0] ?? null;
            $to = $value['to'] ?? $value[1] ?? null;
        } else {
            return null;
        }

        if ($from === null && $to === null) {
            return null;
        }

        return [
            $from === null ? '' : (string) $from,
            $to === null ? '' : (string) $to,
        ];
    }

    /** @return mixed */
    private function normalizeSelect(mixed $value): mixed
    {
        if ($this->options === []) {
            return \is_array($value) ? \array_values($value) : $value;
        }

        $allowed = [];
        foreach ($this->options as $option) {
            // پشتیبانی از enum (DOC-072 §3: options: OrderStatus::cases())
            $allowed[] = $option instanceof \UnitEnum
                ? ($option instanceof \BackedEnum ? $option->value : $option->name)
                : $option;
        }

        if (\is_array($value)) {
            $filtered = \array_values(\array_filter($value, static fn (mixed $v): bool => \in_array($v, $allowed, false)));

            return $filtered === [] ? null : $filtered;
        }

        return \in_array($value, $allowed, false) ? $value : null;
    }
}
