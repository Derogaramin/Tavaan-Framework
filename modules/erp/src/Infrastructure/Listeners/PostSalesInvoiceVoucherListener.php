<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PostSalesInvoiceVoucherListener — سند درآمد فروش (DOC-097 §9: sales.invoice.issued).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Modules\Erp\Application\UseCases\AccountingService;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Support\Time\Clock;

/**
 * بدهکار حساب‌های دریافتنی / بستانکار درآمد فروش.
 *
 * @internal
 */
final class PostSalesInvoiceVoucherListener
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

        $amount = (int) ($payload['amount_minor'] ?? 0);
        $invoiceId = (string) ($payload['invoice_id'] ?? '');
        if ($amount <= 0 || $invoiceId === '') {
            return;
        }

        $invoiceNumber = (string) ($payload['invoice_number'] ?? '');
        $description = 'فاکتور فروش ' . $invoiceNumber . ' — سند خودکار';

        try {
            $this->accounting->issue(
                sourceModule: 'sales',
                sourceId: $invoiceId,
                date: Clock::utcNow()->format('Y-m-d'),
                description: $description,
                lineSpecs: [
                    ['account_code' => VoucherLineBuilder::account('receivable', '1201'), 'debit' => $amount, 'description' => $description],
                    ['account_code' => VoucherLineBuilder::account('revenue', '4101'), 'credit' => $amount, 'description' => $description],
                ],
                userId: null,
                autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
            );
        } catch (\Throwable $e) {
            Log::error('erp.voucher.sales_invoice_failed', [
                'invoice' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
