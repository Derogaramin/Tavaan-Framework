<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit\Console;

use Tavaan\Engine\Audit\Auditor;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Audit
 * AuditExportCommand — خروجی JSON از بازهٔ زمانی (DOC-058 §8).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class AuditExportCommand extends Command
{
    protected string $signature = 'audit:export {--from= : شروع (Y-m-d)} {--to= : پایان (Y-m-d)} {--out= : فایل خروجی}';

    protected string $description = 'خروجی‌گیری رکوردهای ممیزی (JSON)';

    public function handle(): int
    {
        /** @var Auditor $auditor */
        $auditor = $this->app->make(Auditor::class);

        $from = $this->option('from');
        $to = $this->option('to');

        $rows = $auditor->export(
            \is_string($from) && $from !== '' ? new \DateTimeImmutable($from) : null,
            \is_string($to) && $to !== '' ? new \DateTimeImmutable($to . ' 23:59:59') : null,
        );

        $json = (string) \json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $out = $this->option('out');
        if (\is_string($out) && $out !== '') {
            \file_put_contents($out, $json);
            $this->info(\sprintf('%d رکورد به %s نوشته شد.', \count($rows), $out));
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
