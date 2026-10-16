<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade گزارش‌ساز (Report — DOC-072).
 *
 * @method static \Tavaan\Engine\Report\ReportBuilder build(string $name)
 * @method static string export(\Tavaan\Engine\Report\ReportBuilder|\Tavaan\Engine\Report\ReportResult $report, string $format = 'csv', ?string $path = null, array $filters = [])
 * @method static void stream(\Tavaan\Engine\Report\ReportBuilder|\Tavaan\Engine\Report\ReportResult $report, string $format = 'csv', $handle = null, array $filters = [])
 * @method static mixed render(\Tavaan\Engine\Report\ReportBuilder|\Tavaan\Engine\Report\ReportResult $report, array $filters = [])
 * @method static \Tavaan\Engine\Report\Export\ReportExporter exporter()
 * @method static array<string, mixed> config()
 * @mixin \Tavaan\Engine\Report\ReportManager
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class Report extends Facade
{
    protected static function accessor(): string
    {
        return 'report';
    }
}
