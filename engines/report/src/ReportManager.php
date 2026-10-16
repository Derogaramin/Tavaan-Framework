<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Report\Export\CsvExporter;
use Tavaan\Engine\Report\Export\HtmlExporter;
use Tavaan\Engine\Report\Export\JsonExporter;
use Tavaan\Engine\Report\Export\PdfExporter;
use Tavaan\Engine\Report\Export\ReportExporter;
use Tavaan\Engine\Report\Export\XlsxExporter;
use Tavaan\Engine\View\ViewFactory;

/**
 * تَوان | Tavaan — Report
 * ReportManager — ریشهٔ Facade (`Report::…`): build/export/render/stream.
 *
 * ### مثال
 * ```php
 * $report = Report::build('sales')->dataset($rows)->addField('total', type: 'money', aggregate: 'sum');
 * $result = $report->run();
 * Report::export($report, 'csv');                              // → محتوا
 * Report::export($report, 'xlsx', path: storage_path('reports/sales.xlsx'));
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class ReportManager
{
    private ReportExporter $exporter;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?ConnectionManager $db = null,
        private readonly ?ViewFactory $view = null,
    ) {
        $formatter = new Formatter();
        $html = new HtmlExporter($this->view, $formatter);

        $this->exporter = new ReportExporter(
            config: $config,
            csv: new CsvExporter((array) ($config['csv'] ?? []), $formatter),
            xlsx: new XlsxExporter((array) ($config['xlsx'] ?? []), $formatter),
            html: $html,
            json: new JsonExporter($formatter),
            pdf: new PdfExporter($config, $html),
        );
    }

    /** شروع ساخت یک گزارش جدید. */
    public function build(string $name): ReportBuilder
    {
        return new ReportBuilder($name, new DatasetResolver($this->db), $this->config);
    }

    /**
     * صادرات گزارش (DOC-072 §3).
     *
     * @param ReportBuilder|ReportResult $report
     * @param array<string, mixed>       $filters فقط وقتی $report یک Builder است
     *
     * @return string محتوای خروجی یا مسیر فایل نوشته‌شده
     */
    public function export(ReportBuilder|ReportResult $report, string $format = 'csv', ?string $path = null, array $filters = []): string
    {
        $result = $report instanceof ReportResult ? $report : $report->run($filters);

        return $this->exporter->export($result, $format, $path);
    }

    /**
     * Streaming خروجی (پیش‌فرض CSV → php://output؛ حافظهٔ ثابت).
     *
     * @param ReportBuilder|ReportResult $report
     * @param resource|null              $handle
     * @param array<string, mixed>       $filters
     */
    public function stream(ReportBuilder|ReportResult $report, string $format = 'csv', $handle = null, array $filters = []): void
    {
        $result = $report instanceof ReportResult ? $report : $report->run($filters);
        $this->exporter->stream($result, $format, $handle);
    }

    /**
     * رندر HTML گزارش — Response اگر موتور HTTP حاضر باشد، وگرنه رشته.
     *
     * @param ReportBuilder|ReportResult $report
     * @param array<string, mixed>       $filters
     */
    public function render(ReportBuilder|ReportResult $report, array $filters = []): mixed
    {
        $result = $report instanceof ReportResult ? $report : $report->run($filters);
        $html = $this->exporter->export($result, 'html');

        if (\class_exists(\Tavaan\Engine\Http\Response::class)) {
            return new \Tavaan\Engine\Http\Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $html;
    }

    public function exporter(): ReportExporter
    {
        return $this->exporter;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }
}
