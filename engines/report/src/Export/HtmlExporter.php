<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Report\Field;
use Tavaan\Engine\Report\Formatter;
use Tavaan\Engine\Report\ReportResult;
use Tavaan\Engine\View\ViewFactory;

/**
 * تَوان | Tavaan — Report
 * HtmlExporter — خروجی HTML با موتور View (قالب `report::table`) و
 * fallback خودساخته با escape کامل (🔒 ۰ XSS).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class HtmlExporter
{
    public function __construct(
        private readonly ?ViewFactory $view = null,
        private readonly Formatter $formatter = new Formatter(),
    ) {
    }

    public function export(ReportResult $result): string
    {
        $data = $this->viewData($result);

        if ($this->view !== null && $this->view->exists('report::table')) {
            return $this->view->render('report::table', $data);
        }

        return $this->fallback($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(ReportResult $result): array
    {
        $fields = $result->fields();
        $totals = $result->totals();

        $totalsRow = [];
        if ($totals !== [] && $fields !== []) {
            foreach ($fields as $i => $field) {
                $totalsRow[] = \array_key_exists($field->name, $totals)
                    ? $this->formatter->format($totals[$field->name], $field)
                    : ($i === 0 ? 'جمع کل' : null);
            }
        }

        $subtotalFields = [];
        foreach ($result->subtotals() as $values) {
            $subtotalFields = \array_keys($values);
            break;
        }

        return [
            'title' => $result->name(),
            'headers' => $result->headers(),
            'rows' => $result->formattedRows($this->formatter),
            'totals' => $totals,
            'totalsRow' => $totalsRow,
            'subtotals' => $result->subtotals(),
            'subtotalFields' => $subtotalFields,
            'groupField' => (string) ($result->groupField() ?? ''),
            'pivot' => $result->pivot(),
            'count' => $result->count(),
            'truncated' => $result->truncated(),
        ];
    }

    /**
     * Fallback بدون موتور View — escape صریح همهٔ مقادیر.
     *
     * @param array<string, mixed> $data
     */
    private function fallback(array $data): string
    {
        $e = static fn (mixed $v): string => \htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<title>گزارش ' . $e($data['title']) . '</title>'
            . '<style>body{font-family:Tahoma,sans-serif;margin:24px}table{border-collapse:collapse;width:100%}'
            . 'th,td{border:1px solid #cbd5e1;padding:6px 8px;font-size:13px;text-align:right}'
            . 'th{background:#e8eaf0}tfoot td{background:#eef2ff;font-weight:bold}</style></head><body>'
            . '<h1>گزارش ' . $e($data['title']) . '</h1>'
            . '<p>تعداد ردیف: ' . $e($data['count']) . '</p>';

        /** @var list<array<string, mixed>> $rows */
        $rows = $data['rows'];
        if ($rows === []) {
            $html .= '<p>داده‌ای برای نمایش وجود ندارد.</p>';
        } else {
            $html .= '<table><thead><tr>';
            foreach ($data['headers'] as $header) {
                $html .= '<th>' . $e($header) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . ($cell === null ? '—' : $e($cell)) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            if ($data['totalsRow'] !== []) {
                $html .= '<tfoot><tr>';
                foreach ($data['totalsRow'] as $cell) {
                    $html .= '<td>' . ($cell === null ? '' : $e($cell)) . '</td>';
                }
                $html .= '</tr></tfoot>';
            }
            $html .= '</table>';
        }

        return $html . '</body></html>';
    }
}
