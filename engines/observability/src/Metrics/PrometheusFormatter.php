<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

/**
 * تَوان | Tavaan — Observability
 * PrometheusFormatter — رندر متن Prometheus (text/plain; version=0.0.4).
 *
 * قالب (DOC-067 §2.2):
 * ```
 * # HELP tavaan_http_requests_total Total HTTP requests
 * # TYPE tavaan_http_requests_total counter
 * tavaan_http_requests_total{route="users.index",status="200"} 1523
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class PrometheusFormatter
{
    private function __construct()
    {
    }

    /**
     * @param array<string, Metric> $metrics
     */
    public static function render(array $metrics, string $prefix = 'tavaan'): string
    {
        $lines = [];

        foreach ($metrics as $metric) {
            $fullName = self::fullName($metric->name, $prefix);
            if ($metric->help() !== '') {
                $lines[] = '# HELP ' . $fullName . ' ' . self::escapeHelp($metric->help());
            }
            $lines[] = '# TYPE ' . $fullName . ' ' . $metric->type();

            $samples = $metric->samples();
            if ($samples === []) {
                // سنجهٔ بی‌نمونه: فقط متادیتا (Prometheus آن را می‌پذیرد)
                continue;
            }

            match (true) {
                $metric instanceof Counter => self::renderSimple($lines, $fullName, $samples),
                $metric instanceof Gauge => self::renderSimple($lines, $fullName, $samples),
                $metric instanceof Histogram => self::renderHistogram($lines, $fullName, $metric, $samples),
                $metric instanceof Summary => self::renderSummary($lines, $fullName, $metric, $samples),
                default => null,
            };
        }

        return $lines === [] ? '' : \implode("\n", $lines) . "\n";
    }

    public static function fullName(string $name, string $prefix): string
    {
        $sanitized = self::sanitize($name);
        $prefix = self::sanitize($prefix);
        if ($prefix !== '' && !\str_starts_with($sanitized, $prefix . '_') && $sanitized !== $prefix) {
            $sanitized = $prefix . '_' . $sanitized;
        }

        return $sanitized;
    }

    /** نام معتبر Prometheus: [a-zA-Z_:][a-zA-Z0-9_:]* */
    public static function sanitize(string $name): string
    {
        $out = (string) \preg_replace('/[^a-zA-Z0-9_:]/', '_', $name);
        if ($out !== '' && \preg_match('/^[a-zA-Z0-9_:]$/', $out[0]) !== 1) {
            $out = '_' . $out;
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $samples
     * @param list<string>                        $lines
     */
    private static function renderSimple(array &$lines, string $fullName, array $samples): void
    {
        foreach ($samples as $sample) {
            $lines[] = $fullName . self::labels((array) ($sample['labels'] ?? [])) . ' ' . self::number((float) $sample['value']);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $samples
     * @param list<string>                        $lines
     */
    private static function renderHistogram(array &$lines, string $fullName, Histogram $metric, array $samples): void
    {
        $buckets = $metric->buckets();

        foreach ($samples as $sample) {
            $labels = (array) ($sample['labels'] ?? []);
            $counts = \array_map('intval', (array) ($sample['counts'] ?? []));
            $cumulative = 0;
            foreach ($buckets as $i => $bound) {
                $cumulative += $counts[$i] ?? 0;
                $lines[] = $fullName . '_bucket' . self::labels([...$labels, 'le' => self::number($bound)]) . ' ' . $cumulative;
            }
            $count = (int) ($sample['count'] ?? 0);
            $lines[] = $fullName . '_bucket' . self::labels([...$labels, 'le' => '+Inf']) . ' ' . $count;
            $lines[] = $fullName . '_sum' . self::labels($labels) . ' ' . self::number((float) ($sample['sum'] ?? 0.0));
            $lines[] = $fullName . '_count' . self::labels($labels) . ' ' . $count;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $samples
     * @param list<string>                        $lines
     */
    private static function renderSummary(array &$lines, string $fullName, Summary $metric, array $samples): void
    {
        foreach ($samples as $sample) {
            $labels = (array) ($sample['labels'] ?? []);
            $values = \array_map('floatval', (array) ($sample['values'] ?? []));
            \sort($values);

            foreach ($metric->quantiles() as $q) {
                $quantile = null;
                if ($values !== []) {
                    $rank = \max(1, \min((int) \ceil($q * \count($values)), \count($values)));
                    $quantile = $values[$rank - 1];
                }
                $lines[] = $fullName . self::labels([...$labels, 'quantile' => self::number($q)])
                    . ' ' . ($quantile === null ? 'NaN' : self::number($quantile));
            }
            $lines[] = $fullName . '_sum' . self::labels($labels) . ' ' . self::number((float) ($sample['sum'] ?? 0.0));
            $lines[] = $fullName . '_count' . self::labels($labels) . ' ' . (int) ($sample['count'] ?? 0);
        }
    }

    /** @param array<string, string> $labels */
    private static function labels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = self::sanitize((string) $k) . '="' . self::escapeLabelValue((string) $v) . '"';
        }

        return '{' . \implode(',', $parts) . '}';
    }

    private static function escapeLabelValue(string $v): string
    {
        return \str_replace(["\\", "\"", "\n"], ['\\\\', '\\"', '\\n'], $v);
    }

    private static function escapeHelp(string $h): string
    {
        return \str_replace(["\\", "\n"], ['\\\\', '\\n'], $h);
    }

    /** نمایش عدد: بدون صفرهای اضافی؛ INF → +Inf. */
    public static function number(float $v): string
    {
        if ($v === INF) {
            return '+Inf';
        }
        if ($v === -INF) {
            return '-Inf';
        }
        if ($v === NAN) {
            return 'NaN';
        }
        if ($v === \floor($v) && \abs($v) < 1.0e15) {
            return (string) (int) $v;
        }
        $s = \rtrim(\rtrim(\sprintf('%.10F', $v), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}
