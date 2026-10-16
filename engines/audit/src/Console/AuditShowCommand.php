<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit\Console;

use Tavaan\Engine\Audit\Auditor;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Audit
 * AuditShowCommand — نمایش یک رکورد ممیزی (DOC-058 §8).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class AuditShowCommand extends Command
{
    protected string $signature = 'audit:show {id : شناسهٔ رکورد (audit_id)}';

    protected string $description = 'نمایش جزئیات یک رکورد ممیزی';

    public function handle(): int
    {
        /** @var Auditor $auditor */
        $auditor = $this->app->make(Auditor::class);

        $record = $auditor->find((string) $this->argument('id'));
        if ($record === null) {
            $this->error('رکورد یافت نشد.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($record as $key => $value) {
            $rows[] = [(string) $key, \is_scalar($value) || $value === null ? (string) $value : (string) \json_encode($value, JSON_UNESCAPED_UNICODE)];
        }

        $this->table(['فیلد', 'مقدار'], $rows);

        return self::SUCCESS;
    }
}
