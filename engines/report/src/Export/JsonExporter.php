<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Report\Formatter;
use Tavaan\Engine\Report\ReportResult;

/**
 * تَوان | Tavaan — Report
 * JsonExporter — خروجی JSON (DOC-072 §3).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class JsonExporter
{
    public function __construct(private readonly Formatter $formatter = new Formatter())
    {
    }

    public function export(ReportResult $result, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $payload = $result->toArray();
        $payload['rows'] = $result->formattedRows($this->formatter);

        return \json_encode($payload, $flags) ?: '{}';
    }
}
