<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Report\Exceptions\ReportException;
use Tavaan\Engine\Report\ReportResult;

/**
 * تَوان | Tavaan — Report
 * PdfExporter — خروجی PDF (اختیاری — DOC-072 §7).
 *
 * بدون درایور پیکربندی‌شده → `report.pdf_not_configured`.
 * درایور `html`: HTML آمادهٔ چاپ (print-to-PDF در مرورگر).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class PdfExporter
{
    /**
     * @param array{pdf_driver?: string|null} $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?HtmlExporter $html = null,
    ) {
    }

    public function export(ReportResult $result): string
    {
        $driver = $this->config['pdf_driver'] ?? null;

        if ($driver === null || $driver === '') {
            throw ReportException::pdfNotConfigured();
        }

        if ($driver === 'html') {
            // HTML آمادهٔ چاپ (@media print) — تبدیل نهایی با چاپ مرورگر/wkhtmltopdf
            $html = ($this->html ?? new HtmlExporter())->export($result);

            return \str_replace(
                '</head>',
                '<style media="print">@page { size: A4 landscape; margin: 12mm; }</style></head>',
                $html,
            );
        }

        throw ReportException::pdfDriverUnsupported((string) $driver);
    }
}
