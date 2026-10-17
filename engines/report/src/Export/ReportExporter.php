<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Report\Exceptions\ReportException;
use Tavaan\Engine\Report\ReportResult;

/**
 * تَوان | Tavaan — Report
 * ReportExporter — صادرات بر اساس قالب: csv/xlsx/html/json/pdf + stream (DOC-072 §3).
 *
 * 🔒 مسیر نوشتن باید داخل base path پروژه بماند (sandbox).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class ReportExporter
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly CsvExporter $csv = new CsvExporter(),
        private readonly XlsxExporter $xlsx = new XlsxExporter(),
        private readonly HtmlExporter $html = new HtmlExporter(),
        private readonly JsonExporter $json = new JsonExporter(),
        private readonly ?PdfExporter $pdf = null,
    ) {
    }

    /**
     * صادرات نتیجه — بازگرداندن محتوا یا نوشتن در مسیر.
     *
     * @return string محتوای خروجی (وقتی path=null) یا مسیر نوشته‌شده
     */
    public function export(ReportResult $result, string $format, ?string $path = null): string
    {
        $format = \strtolower($format);
        $this->assertFormatAllowed($format);

        if ($path !== null) {
            $this->writeFile($result, $format, $path);

            return $path;
        }

        return match ($format) {
            'csv' => $this->csv->export($result),
            'xlsx', 'excel' => $this->xlsx->export($result),
            'html' => $this->html->export($result),
            'json' => $this->json->export($result),
            'pdf' => ($this->pdf ?? new PdfExporter($this->config, $this->html))->export($result),
            default => throw ReportException::unknownFormat($format),
        };
    }

    /**
     * Streaming مستقیم (CSV — حافظهٔ ثابت؛ DOC-072 §5).
     *
     * @param resource|null $handle پیش‌فرض php://output
     */
    public function stream(ReportResult $result, string $format = 'csv', $handle = null): void
    {
        $format = \strtolower($format);
        $this->assertFormatAllowed($format);
        if ($format !== 'csv') {
            // غیر csv: یک‌جا تولید و write می‌شود (streaming واقعی فقط CSV)
            $handle ??= \fopen('php://output', 'wb');
            \fwrite($handle, $this->export($result, $format));

            return;
        }

        $handle ??= \fopen('php://output', 'wb');
        $chunk = (int) ($this->config['streaming_threshold'] ?? 1000);
        $this->csv->toStream($result, $handle, $chunk > 0 ? $chunk : 1000);
    }

    private function writeFile(ReportResult $result, string $format, string $path): void
    {
        $this->assertPathSandboxed($path);

        $dir = \dirname($path);
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Unable to create export directory: %s', $dir));
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $this->xlsx->exportToFile($result, $path);

            return;
        }

        $content = $this->export($result, $format);
        if (\file_put_contents($path, $content) === false) {
            throw new \RuntimeException(\sprintf('Unable to write report file: %s', $path));
        }
    }

    /** 🔒 sandbox: مسیر مقصد باید داخل base path بماند (PathGuard — cross-platform). */
    private function assertPathSandboxed(string $path): void
    {
        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : null;
        if ($base === null) {
            return;
        }

        if (!\Tavaan\Support\PathGuard::isInside($path, $base)) {
            throw ReportException::pathNotAllowed($path);
        }
    }

    private function assertFormatAllowed(string $format): void
    {
        $known = ['csv', 'xlsx', 'excel', 'html', 'json', 'pdf'];
        if (!\in_array($format, $known, true)) {
            throw ReportException::unknownFormat($format);
        }

        $formats = (array) ($this->config['formats'] ?? []);
        if ($formats !== []) {
            $normalized = $format === 'excel' ? 'xlsx' : $format;
            if (!\in_array($normalized, $formats, true)) {
                throw ReportException::formatDisabled($format);
            }
        }
    }
}
