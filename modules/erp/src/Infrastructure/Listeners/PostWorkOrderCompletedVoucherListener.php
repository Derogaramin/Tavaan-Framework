<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PostWorkOrderCompletedVoucherListener — سند بهای تمام‌شده (DOC-097 §9: production.order.completed).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Modules\Erp\Application\UseCases\AccountingService;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Support\Time\Clock;

/**
 * بدهکار محصول ساخته‌شده / بستانکار کالای در جریان ساخت (بهای مواد).
 *
 * @internal
 */
final class PostWorkOrderCompletedVoucherListener
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

        $materialCost = (int) ($payload['material_cost'] ?? 0);
        $workOrderId = (string) ($payload['work_order_id'] ?? '');
        if ($materialCost <= 0 || $workOrderId === '') {
            return;
        }

        $description = 'بهای تمام‌شدهٔ تولید ' . (string) ($payload['wo_number'] ?? '') . ' — سند خودکار';

        try {
            $this->accounting->issue(
                sourceModule: 'production',
                sourceId: $workOrderId,
                date: Clock::utcNow()->format('Y-m-d'),
                description: $description,
                lineSpecs: [
                    ['account_code' => VoucherLineBuilder::account('finished_goods', '1304'), 'debit' => $materialCost, 'description' => $description],
                    ['account_code' => VoucherLineBuilder::account('wip', '1303'), 'credit' => $materialCost, 'description' => $description],
                ],
                userId: null,
                autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
            );
        } catch (\Throwable $e) {
            Log::error('erp.voucher.work_order_failed', [
                'work_order' => $workOrderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
