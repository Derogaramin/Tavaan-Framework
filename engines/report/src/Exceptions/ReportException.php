<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Report
 * ReportException — استثناهای موتور گزارش‌ساز (DOC-072).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class ReportException extends TavaanException
{
    public static function noDataset(string $report): self
    {
        return new self(
            messageId: 'report.no_dataset',
            context: ['report' => $report],
            hint: 'ابتدا dataset() را تنظیم کنید (Closure/QueryBuilder/آرایه/SQL).',
        );
    }

    public static function unknownFormat(string $format): self
    {
        return new self(
            messageId: 'report.unknown_format',
            context: ['format' => $format],
        );
    }

    public static function formatDisabled(string $format): self
    {
        return new self(
            messageId: 'report.format_disabled',
            context: ['format' => $format],
            hint: 'قالب در config/report.php → formats مجاز نیست.',
        );
    }

    public static function pdfNotConfigured(): self
    {
        return new self(
            messageId: 'report.pdf_not_configured',
            hint: 'pdf_driver در config/report.php تنظیم نشده (خروجی PDF اختیاری است).',
        );
    }

    public static function pdfDriverUnsupported(string $driver): self
    {
        return new self(
            messageId: 'report.pdf_driver_unsupported',
            context: ['driver' => $driver],
        );
    }

    public static function pathNotAllowed(string $path): self
    {
        return new self(
            messageId: 'report.path_not_allowed',
            context: ['path' => $path],
            hint: '🔒 مسیر export باید داخل base path پروژه باشد (sandbox).',
        );
    }

    public static function unknownField(string $report, string $field): self
    {
        return new self(
            messageId: 'report.unknown_field',
            context: ['report' => $report, 'field' => $field],
        );
    }

    public static function unknownAggregate(string $aggregate): self
    {
        return new self(
            messageId: 'report.unknown_aggregate',
            context: ['aggregate' => $aggregate],
            hint: 'sum|avg|count|min|max مجاز است.',
        );
    }

    public static function unknownFilterType(string $type): self
    {
        return new self(
            messageId: 'report.unknown_filter_type',
            context: ['type' => $type],
        );
    }

    public static function databaseUnavailable(): self
    {
        return new self(
            messageId: 'report.database_unavailable',
            hint: 'dataset کوئری/SQL به اتصال DB نیاز دارد.',
        );
    }
}
