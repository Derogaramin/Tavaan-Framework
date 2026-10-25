<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PostStockReceivedVoucherListener — سند رسید انبار (DOC-097 §9).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Modules\Erp\Application\UseCases\AccountingService;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Support\Time\Clock;

/**
 * مصرف‌کنندهٔ `inventory.stock.received` (از Outbox):
 *
 * - purchase_receipt → بدهکار موجودی (per نوع کالا) / بستانکار پرداختنی تجاری
 * - adjustment → بدهکار موجودی / بستانکار سایر هزینه‌ها (سود انبارداری)
 * - opening → بدهکار موجودی / بستانکار سرمایه
 * - work_order_output → بدون سند (سند بهای تمام‌شده را
 *   PostWorkOrderCompletedVoucherListener صادر می‌کند — از دوبل‌شماری پرهیز)
 *
 * @internal
 */
final class PostStockReceivedVoucherListener
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
        if (\in_array($referenceType, ['', 'work_order_output', 'transfer'], true)) {
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

        $creditCode = match ($referenceType) {
            'purchase_receipt' => VoucherLineBuilder::account('payable', '2101'),
            'opening' => VoucherLineBuilder::account('capital', '3101'),
            default => VoucherLineBuilder::account('other_expense', '5301'),   // adjustment — سود انبارداری
        };

        $description = match ($referenceType) {
            'purchase_receipt' => 'رسید انبار (خرید) — سند خودکار',
            'opening' => 'موجودی افتتاحیه — سند خودکار',
            default => 'اصلاح موجودی (سود انبارداری) — سند خودکار',
        };

        $lineSpecs = [];
        foreach ($sums as $code => $amount) {
            $lineSpecs[] = ['account_code' => (string) $code, 'debit' => (int) $amount, 'description' => $description];
        }
        $lineSpecs[] = ['account_code' => $creditCode, 'credit' => (int) $total, 'description' => $description];

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
            Log::error('erp.voucher.stock_received_failed', [
                'group' => $groupId,
                'error' => $e->getMessage(),
            ]);

            throw $e;   // → outbox pending/failed (بدون نشت — AC-ERP-11)
        }
    }
}
