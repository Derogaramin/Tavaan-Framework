<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PostPayrollProcessedVoucherListener — سند هزینهٔ حقوق (DOC-097 §9: hr.payroll.processed).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Modules\Erp\Application\UseCases\AccountingService;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Support\Time\Clock;

/**
 * بدهکار هزینهٔ حقوق (gross) / بستانکار حقوق پرداختنی.
 *
 * @internal
 */
final class PostPayrollProcessedVoucherListener
{
    public function __construct(private readonly AccountingService $accounting)
    {
    }

    /** @param array<string, mixed> $payload */
    public function handle(string $event, mixed $payload): void
    {
        if (!\is_array($payload)) {
            return;
        }

        $gross = (int) ($payload['total_gross'] ?? 0);
        $runId = (string) ($payload['payroll_run_id'] ?? '');
        if ($gross <= 0 || $runId === '') {
            return;
        }

        $description = 'حقوق ' . (string) ($payload['period_month'] ?? '') . ' — سند خودکار';

        try {
            $this->accounting->issue(
                sourceModule: 'hr',
                sourceId: $runId,
                date: Clock::utcNow()->format('Y-m-d'),
                description: $description,
                lineSpecs: [
                    ['account_code' => VoucherLineBuilder::account('salary_expense', '5201'), 'debit' => $gross, 'description' => $description],
                    ['account_code' => VoucherLineBuilder::account('salary_payable', '2201'), 'credit' => $gross, 'description' => $description],
                ],
                userId: null,
                autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
            );
        } catch (\Throwable $e) {
            Log::error('erp.voucher.payroll_failed', [
                'run' => $runId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
