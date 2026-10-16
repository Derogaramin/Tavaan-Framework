<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Report\Field;
use Tavaan\Engine\Report\Formatter;
use Tavaan\Engine\Report\ReportResult;

/**
 * تَوان | Tavaan — Report
 * CsvExporter — خروجی CSV با BOM و delimiter `;` (سازگاری Excel فارسی) +
 * Streaming برای دادهٔ بزرگ (DOC-072 §5) + 🔒 محافظ CSV Injection (§8).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class CsvExporter
{
    /**
     * @param array{delimiter?: string, bom?: bool, enclosure?: string} $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly Formatter $formatter = new Formatter(),
    ) {
    }

    private function delimiter(): string
    {
        return (string) ($this->config['delimiter'] ?? ';');
    }

    private function enclosure(): string
    {
        return (string) ($this->config['enclosure'] ?? '"');
    }

    private function bom(): bool
    {
        return (bool) ($this->config['bom'] ?? true);
    }

    /** ساخت رشتهٔ کامل CSV. */
    public function export(ReportResult $result): string
    {
        $handle = \fopen('php://temp', 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open temp stream for CSV export.');
        }

        $this->toStream($result, $handle);
        \rewind($handle);
        $content = \stream_get_contents($handle);
        \fclose($handle);

        return $content === false ? '' : $content;
    }

    /**
     * نوشتن streaming (حافظهٔ ثابت برای گزارش‌های بزرگ).
     *
     * @param resource $handle
     */
    public function toStream(ReportResult $result, $handle, int $chunkSize = 1000): void
    {
        if ($this->bom()) {
            // 🔒 BOM برای نمایش درست UTF-8/فارسی در Excel
            \fwrite($handle, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));
        }

        $fields = $result->fields();
        if ($fields !== []) {
            $this->putRow($handle, \array_map(static fn (Field $f): string => self::sanitizeCell($f->label), $fields));
        }

        foreach ($result->chunked($chunkSize) as $chunk) {
            foreach ($chunk as $row) {
                $cells = [];
                if ($fields !== []) {
                    foreach ($fields as $field) {
                        $cells[] = self::sanitizeCell($this->cellValue($row[$field->name] ?? null, $field));
                    }
                } else {
                    foreach ($row as $value) {
                        $cells[] = self::sanitizeCell(\is_scalar($value) ? (string) $value : (\json_encode($value, JSON_UNESCAPED_UNICODE) ?: ''));
                    }
                }
                $this->putRow($handle, $cells);
            }
            \fflush($handle);
        }
    }

    /**
     * 🔒 محافظ CSV Injection: سلول‌های شروع‌شده با `= + - @` با prefix apostrophe.
     */
    public static function sanitizeCell(string $value): string
    {
        if ($value !== '' && \str_contains('=+-@', $value[0])) {
            return "'" . $value;
        }

        return $value;
    }

    private function cellValue(mixed $value, Field $field): string
    {
        if ($value === null) {
            return '';
        }

        // تاریخ → رشتهٔ قالب‌شده؛ عدد/پول → raw (Excel عدد را درست می‌بیند)
        if ($field->isDate()) {
            return (string) $this->formatter->format($value, $field);
        }
        if ($field->type === 'bool') {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }

        return \json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * @param resource      $handle
     * @param list<string> $cells
     */
    private function putRow($handle, array $cells): void
    {
        \fputcsv($handle, $cells, $this->delimiter(), $this->enclosure(), '\\');
    }
}
