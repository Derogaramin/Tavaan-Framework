<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DatabaseSalesRepository — پایداری بافتهٔ فروش.
 */

namespace Modules\Erp\Infrastructure\Persistence;

use Modules\Erp\Domain\Entities\Customer;
use Modules\Erp\Domain\Entities\Payment;
use Modules\Erp\Domain\Entities\SalesInvoice;
use Modules\Erp\Domain\Entities\SalesOrder;
use Modules\Erp\Domain\Entities\SalesOrderItem;
use Modules\Erp\Domain\Repositories\SalesRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;

/**
 * @internal
 */
final class DatabaseSalesRepository implements SalesRepositoryInterface
{
    // ── مشتریان ────────────────────────────────────────────────────────────

    public function customers(): array
    {
        $rows = DB::table('erp_customers')->orderBy('code')->get();

        return \array_map(static fn (array $row): Customer => Customer::fromRow($row), $rows);
    }

    public function findCustomer(string $id): ?Customer
    {
        $row = DB::table('erp_customers')->where('id', '=', $id)->first();

        return $row !== null ? Customer::fromRow($row) : null;
    }

    public function saveCustomer(Customer $customer): void
    {
        $exists = DB::table('erp_customers')->where('id', '=', $customer->id())->count() > 0;
        $row = $customer->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            DB::table('erp_customers')->where('id', '=', $customer->id())->update($row);

            return;
        }

        DB::table('erp_customers')->insert($row);
    }

    // ── سفارش فروش ─────────────────────────────────────────────────────────

    public function findSalesOrder(string $id, bool $withItems = true): ?SalesOrder
    {
        $row = DB::table('erp_sales_orders')->where('id', '=', $id)->first();
        if ($row === null) {
            return null;
        }

        $items = [];
        if ($withItems) {
            $itemRows = DB::table('erp_sales_order_items')->where('order_id', '=', $id)->get();
            foreach ($itemRows as $itemRow) {
                $items[] = SalesOrderItem::fromRow($itemRow);
            }
        }

        return SalesOrder::fromRow($row, $items);
    }

    public function salesOrders(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('erp_sales_orders');
        $this->applyOrderFilters($query, $filters);

        $rows = $query->orderByDesc('created_at')->limit($limit)->offset($offset)->get();

        $out = [];
        foreach ($rows as $row) {
            $items = [];
            $itemRows = DB::table('erp_sales_order_items')->where('order_id', '=', (string) $row['id'])->get();
            foreach ($itemRows as $itemRow) {
                $items[] = SalesOrderItem::fromRow($itemRow);
            }
            $out[] = SalesOrder::fromRow($row, $items);
        }

        return $out;
    }

    public function countSalesOrders(array $filters = []): int
    {
        $query = DB::table('erp_sales_orders');
        $this->applyOrderFilters($query, $filters);

        return $query->count();
    }

    public function saveSalesOrder(SalesOrder $order): void
    {
        $exists = DB::table('erp_sales_orders')->where('id', '=', $order->id())->count() > 0;
        $row = $order->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('erp_sales_orders')->where('id', '=', $order->id())->update($row);
        } else {
            DB::table('erp_sales_orders')->insert($row);
        }

        foreach ($order->items() as $item) {
            $itemRow = $item->toRow();
            $itemExists = DB::table('erp_sales_order_items')->where('id', '=', $item->id())->count() > 0;

            if ($itemExists) {
                DB::table('erp_sales_order_items')->where('id', '=', $item->id())->update($itemRow);
            } else {
                DB::table('erp_sales_order_items')->insert($itemRow);
            }
        }
    }

    public function customerBalance(string $customerId): int
    {
        $invoices = DB::table('erp_sales_invoices')
            ->where('customer_id', '=', $customerId)
            ->where('status', '<>', 'canceled')
            ->sum('amount');

        $payments = DB::table('erp_payments')
            ->where('customer_id', '=', $customerId)
            ->where('direction', '=', 'in')
            ->sum('amount');

        return (int) $invoices - (int) $payments;
    }

    // ── فاکتور/دریافت ──────────────────────────────────────────────────────

    public function saveSalesInvoice(SalesInvoice $invoice): void
    {
        $exists = DB::table('erp_sales_invoices')->where('id', '=', $invoice->id())->count() > 0;
        $row = $invoice->toRow();

        if ($exists) {
            unset($row['id'], $row['issued_at']);
            DB::table('erp_sales_invoices')->where('id', '=', $invoice->id())->update($row);

            return;
        }

        DB::table('erp_sales_invoices')->insert($row);
    }

    public function findSalesInvoice(string $id): ?SalesInvoice
    {
        $row = DB::table('erp_sales_invoices')->where('id', '=', $id)->first();

        return $row !== null ? SalesInvoice::fromRow($row) : null;
    }

    public function salesInvoices(?string $customerId = null, int $limit = 50): array
    {
        $query = DB::table('erp_sales_invoices');
        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', '=', $customerId);
        }

        $rows = $query->orderByDesc('issued_at')->limit($limit)->get();

        return \array_map(static fn (array $row): SalesInvoice => SalesInvoice::fromRow($row), $rows);
    }

    public function savePayment(Payment $payment): void
    {
        DB::table('erp_payments')->insert($payment->toRow());
    }

    public function paymentsIn(?string $customerId = null, int $limit = 50): array
    {
        $query = DB::table('erp_payments')->where('direction', '=', 'in');
        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', '=', $customerId);
        }

        $rows = $query->orderByDesc('paid_at')->limit($limit)->get();

        return \array_map(static fn (array $row): Payment => Payment::fromRow($row), $rows);
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $filters */
    private function applyOrderFilters(mixed $query, array $filters): void
    {
        foreach (['status', 'customer_id'] as $key) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where($key, '=', $value);
            }
        }

        $q = $filters['q'] ?? null;
        if (\is_string($q) && \trim($q) !== '') {
            $query->where('order_number', 'like', '%' . \trim($q) . '%');
        }
    }
}
