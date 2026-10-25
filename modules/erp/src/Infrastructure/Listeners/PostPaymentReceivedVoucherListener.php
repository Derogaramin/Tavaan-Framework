<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PostPaymentReceivedVoucherListener — سند دریافت از مشتری (DOC-097 §9).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Modules\Erp\Application\UseCases\AccountingService;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Support\Time\Clock;

/**
 * بدهکار بانک/صندوق / بستانکار حساب‌های دریافتنی.
 *
 * @internal
 */
final class PostPaymentReceivedVoucherListener
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
        $paymentId = (string) ($payload['payment_id'] ?? '');
        if ($amount <= 0 || $paymentId === '') {
            return;
        }

        $method = (string) ($payload['method'] ?? 'bank');
        $description = 'دریافت از مشتری — سند خودکار';

        try {
            $this->accounting->issue(
                sourceModule: 'sales',
                sourceId: $paymentId,
                date: Clock::utcNow()->format('Y-m-d'),
                description: $description,
                lineSpecs: [
                    ['account_code' => VoucherLineBuilder::account($method === 'cash' ? 'cash' : 'bank', '1102'), 'debit' => $amount, 'description' => $description],
                    ['account_code' => VoucherLineBuilder::account('receivable', '1201'), 'credit' => $amount, 'description' => $description],
                ],
                userId: null,
                autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
            );
        } catch (\Throwable $e) {
            Log::error('erp.voucher.payment_received_failed', [
                'payment' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
