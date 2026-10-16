<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Observability
 * ObservabilityException — خطای عمومی موتور رصدپذیری (DOC-067).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
class ObservabilityException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function for(string $messageId, array $context = [], ?string $hint = null): static
    {
        return new static($messageId, $context, $hint);
    }

    public static function invalidMetricName(string $name): static
    {
        return new static('observability.invalid_metric_name', ['name' => $name], 'نام سنجه باید ناتهی و بدون فاصله باشد.');
    }

    public static function negativeIncrement(string $name): static
    {
        return new static('observability.negative_increment', ['name' => $name], 'Counter فقط افزایشی است؛ برای مقدار کاهنده از Gauge استفاده کنید.');
    }

    public static function unknownExporter(string $exporter): static
    {
        return new static('observability.unknown_exporter', ['exporter' => $exporter], 'exporter معتبر: array|log|otlp|jaeger|none');
    }

    public static function spanAlreadyEnded(string $name): static
    {
        return new static('observability.span_already_ended', ['name' => $name]);
    }

    public static function exportFailed(string $exporter, string $reason): static
    {
        return new static('observability.export_failed', ['exporter' => $exporter, 'reason' => $reason]);
    }
}
