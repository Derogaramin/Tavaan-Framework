<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit\Console;

use Tavaan\Engine\Audit\Auditor;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Audit
 * AuditVerifyCommand — بازبینی یکپارچگی زنجیرهٔ هش (DOC-058 §8).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class AuditVerifyCommand extends Command
{
    protected string $signature = 'audit:verify';

    protected string $description = 'بازبینی یکپارچگی زنجیرهٔ هش ممیزی';

    public function handle(): int
    {
        /** @var Auditor $auditor */
        $auditor = $this->app->make(Auditor::class);

        $start = \microtime(true);
        $result = $auditor->verify();
        $elapsed = (\microtime(true) - $start) * 1000;

        if ($result->intact) {
            $this->info(\sprintf('✓ زنجیره سالم است — %d رکورد در %.0fms بررسی شد.', $result->checked, $elapsed));

            return self::SUCCESS;
        }

        $this->error(\sprintf(
            '✗ دستکاری تشخیص داده شد! رکورد [%s]: %s (پس از %d رکورد)',
            (string) $result->tamperedAt,
            (string) $result->reason,
            $result->checked,
        ));

        return self::FAILURE;
    }
}
