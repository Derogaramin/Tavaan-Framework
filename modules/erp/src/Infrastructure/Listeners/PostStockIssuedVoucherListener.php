<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PostStockIssuedVoucherListener — سند حوالهٔ انبار (DOC-097 §9).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Modules\Erp\Application\UseCases\AccountingService;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Support\Time\Clock;

/**
 * مصرف‌کنندهٔ `inventory.stock.issued` (از Outbox):
 *
 * - sales_issue → بدهکار بهای کالای فروش‌رفته / بستانکار موجودی (per نوع)
 * - work_order_consume → بدهکار کالای در جریان ساخت / بستانکار مواد اولیه
 * - adjustment → بدهکار سایر هزینه‌ها (زیان انبارداری) / بستانکار موجودی
 *
 * @internal
 */
final class PostStockIssuedVoucherListener
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

        $referenceType = (string) ($payload['reference_type'] ?? '');
        if (\in_array($referenceType, ['', 'transfer'], true)) {
            return;
        }

        $lines = $payload['lines'] ?? [];
        if (!\is_array($lines) || $lines === []) {
            return;
        }

        $sums = VoucherLineBuilder::sumByInventoryAccount($lines);
        if ($sums === []) {
            return;
        }

        $total = \array_sum($sums);
        if ($total <= 0) {
            return;
        }

        $groupId = (string) ($payload['movement_group_id'] ?? '');

        $debitCode = match ($referenceType) {
            'sales_issue' => VoucherLineBuilder::account('cogs', '5101'),
            'work_order_consume' => VoucherLineBuilder::account('wip', '1303'),
            default => VoucherLineBuilder::account('other_expense', '5301'),   // adjustment — زیان انبارداری
        };

        $description = match ($referenceType) {
            'sales_issue' => 'حوالهٔ فروش (بهای تمام‌شده) — سند خودکار',
            'work_order_consume' => 'مصرف مواد در تولید — سند خودکار',
            default => 'اصلاح موجودی (زیان انبارداری) — سند خودکار',
        };

        $lineSpecs = [['account_code' => $debitCode, 'debit' => (int) $total, 'description' => $description]];
        foreach ($sums as $code => $amount) {
            $lineSpecs[] = ['account_code' => (string) $code, 'credit' => (int) $amount, 'description' => $description];
        }

        try {
            $this->accounting->issue(
                sourceModule: 'inventory',
                sourceId: $groupId,
                date: Clock::utcNow()->format('Y-m-d'),
                description: $description,
                lineSpecs: $lineSpecs,
                userId: null,
                autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
            );
        } catch (\Throwable $e) {
            Log::error('erp.voucher.stock_issued_failed', [
                'group' => $groupId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
