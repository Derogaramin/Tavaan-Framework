<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Payment\Transaction;

/**
 * تَوان | Tavaan — Payment
 * PaymentListCommand — فهرست تراکنش‌ها (PAN از قبل mask است — 🔒).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentListCommand extends Command
{
    protected string $signature = 'payment:list {--status= : فیلتر وضعیت} {--gateway= : فیلتر درگاه} {--limit=25 : تعداد}';

    protected string $description = 'فهرست تراکنش‌های پرداخت';

    public function handle(): int
    {
        if (!$this->app->bound('db')) {
            $this->error('اتصال دیتابیس در دسترس نیست.');

            return self::FAILURE;
        }

        $query = Transaction::query();

        $status = $this->option('status');
        if (\is_string($status) && $status !== '') {
            $query->where('status', $status);
        }
        $gateway = $this->option('gateway');
        if (\is_string($gateway) && $gateway !== '') {
            $query->where('gateway', $gateway);
        }

        $limit = \max(1, (int) ($this->option('limit') ?? 25));
        /** @var list<Transaction> $transactions */
        $transactions = $query->orderByDesc('created_at')->limit($limit)->get();

        if ($transactions === []) {
            $this->comment('تراکنشی یافت نشد.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($transactions as $tx) {
            $rows[] = [
                \substr((string) $tx->getKey(), -8),
                $tx->gateway(),
                \number_format($tx->money()->amount) . ' ' . $tx->money()->currency,
                $tx->status(),
                (string) ($tx->authority() ?? '—'),
                (string) ($tx->cardPan() ?? '—'),
                (string) ($tx->getAttribute('created_at') ?? ''),
            ];
        }

        $this->table(['ID', 'GATEWAY', 'AMOUNT', 'STATUS', 'AUTHORITY', 'CARD', 'CREATED'], $rows);

        return self::SUCCESS;
    }
}
