<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Workflow\WorkflowManager;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowListCommand — فهرست تعاریف فرایند + آمار instanceها.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowListCommand extends Command
{
    protected string $signature = 'workflow:list {--diagram= : تولید نمودار DOT برای یک فرایند}';

    protected string $description = 'فهرست گردش‌کارهای ثبت‌شده';

    public function handle(): int
    {
        /** @var WorkflowManager $workflows */
        $workflows = $this->app->make('workflow');

        $diagram = $this->option('diagram');
        if (\is_string($diagram) && $diagram !== '') {
            $definition = $workflows->definition($diagram);
            $this->line((new \Tavaan\Engine\Workflow\Visualizer())->toDot($definition));

            return self::SUCCESS;
        }

        $definitions = $workflows->definitions();
        if ($definitions === []) {
            $this->comment('هیچ فرایندی تعریف نشده است.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($definitions as $row) {
            $rows[] = [
                (string) ($row['name'] ?? ''),
                (string) ($row['version'] ?? '1'),
                !empty($row['is_active']) ? 'yes' : 'no',
            ];
        }

        $this->table(['NAME', 'VERSION', 'ACTIVE'], $rows);
        $this->comment(\sprintf('مجموع: %d فرایند فعال', \count($rows)));

        return self::SUCCESS;
    }
}
