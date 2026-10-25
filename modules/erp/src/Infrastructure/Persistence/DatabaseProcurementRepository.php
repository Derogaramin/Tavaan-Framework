<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DatabaseProcurementRepository — پایداری بافتهٔ تدارکات.
 */

namespace Modules\Erp\Infrastructure\Persistence;

use Modules\Erp\Domain\Entities\GoodsReceipt;
use Modules\Erp\Domain\Entities\Payment;
use Modules\Erp\Domain\Entities\PurchaseOrder;
use Modules\Erp\Domain\Entities\PurchaseOrderItem;
use Modules\Erp\Domain\Entities\Supplier;
use Modules\Erp\Domain\Entities\SupplierInvoice;
use Modules\Erp\Domain\Repositories\ProcurementRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;

/**
 * @internal
 */
final class DatabaseProcurementRepository implements ProcurementRepositoryInterface
{
    // ── تأمین‌کنندگان ──────────────────────────────────────────────────────

    public function suppliers(): array
    {
        $rows = DB::table('erp_suppliers')->orderBy('code')->get();

        return \array_map(static fn (array $row): Supplier => Supplier::fromRow($row), $rows);
    }

    public function findSupplier(string $id): ?Supplier
    {
        $row = DB::table('erp_suppliers')->where('id', '=', $id)->first();

        return $row !== null ? Supplier::fromRow($row) : null;
    }

    public function saveSupplier(Supplier $supplier): void
    {
        $exists = DB::table('erp_suppliers')->where('id', '=', $supplier->id())->count() > 0;
        $row = $supplier->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            DB::table('erp_suppliers')->where('id', '=', $supplier->id())->update($row);

            return;
        }

        DB::table('erp_suppliers')->insert($row);
    }

    // ── سفارش خرید ─────────────────────────────────────────────────────────

    public function findPurchaseOrder(string $id, bool $withItems = true): ?PurchaseOrder
    {
        $row = DB::table('erp_purchase_orders')->where('id', '=', $id)->first();
        if ($row === null) {
            return null;
        }

        $items = [];
        if ($withItems) {
            $itemRows = DB::table('erp_purchase_order_items')->where('po_id', '=', $id)->get();
            foreach ($itemRows as $itemRow) {
                $items[] = PurchaseOrderItem::fromRow($itemRow);
            }
        }

        return PurchaseOrder::fromRow($row, $items);
    }

    public function purchaseOrders(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('erp_purchase_orders');
        $this->applyPoFilters($query, $filters);

        $rows = $query->orderByDesc('created_at')->limit($limit)->offset($offset)->get();

        $out = [];
        foreach ($rows as $row) {
            $items = [];
            $itemRows = DB::table('erp_purchase_order_items')->where('po_id', '=', (string) $row['id'])->get();
            foreach ($itemRows as $itemRow) {
                $items[] = PurchaseOrderItem::fromRow($itemRow);
            }
            $out[] = PurchaseOrder::fromRow($row, $items);
        }

        return $out;
    }

    public function countPurchaseOrders(array $filters = []): int
    {
        $query = DB::table('erp_purchase_orders');
        $this->applyPoFilters($query, $filters);

        return $query->count();
    }

    public function savePurchaseOrder(PurchaseOrder $order): void
    {
        $exists = DB::table('erp_purchase_orders')->where('id', '=', $order->id())->count() > 0;
        $row = $order->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('erp_purchase_orders')->where('id', '=', $order->id())->update($row);
        } else {
            DB::table('erp_purchase_orders')->insert($row);
        }

        // اقلام — upsert
        foreach ($order->items() as $item) {
            $itemRow = $item->toRow();
            $itemExists = DB::table('erp_purchase_order_items')->where('id', '=', $item->id())->count() > 0;

            if ($itemExists) {
                DB::table('erp_purchase_order_items')->where('id', '=', $item->id())->update($itemRow);
            } else {
                DB::table('erp_purchase_order_items')->insert($itemRow);
            }
        }
    }

    public function supplierBalance(string $supplierId): int
    {
        // بدهی به تأمین‌کننده در **رسید کالا** شناسایی می‌شود (سند پرداختنی) —
        // فاکتور تأمین‌کننده مدرک تسویه است، نه رویداد شناسایی بدهی.
        $receipts = DB::table('erp_goods_receipts')
            ->where('supplier_id', '=', $supplierId)
            ->sum('total_cost');

        $payments = DB::table('erp_payments')
            ->where('supplier_id', '=', $supplierId)
            ->where('direction', '=', 'out')
            ->sum('amount');

        return (int) $receipts - (int) $payments;
    }

    // ── رسید/فاکتور/پرداخت ─────────────────────────────────────────────────

    public function saveGoodsReceipt(GoodsReceipt $receipt): void
    {
        DB::table('erp_goods_receipts')->insert($receipt->toRow());
    }

    public function findGoodsReceipt(string $id): ?GoodsReceipt
    {
        $row = DB::table('erp_goods_receipts')->where('id', '=', $id)->first();

        return $row !== null ? GoodsReceipt::fromRow($row) : null;
    }

    public function goodsReceipts(int $limit = 50): array
    {
        $rows = DB::table('erp_goods_receipts')->orderByDesc('created_at')->limit($limit)->get();

        return \array_map(static fn (array $row): GoodsReceipt => GoodsReceipt::fromRow($row), $rows);
    }

    public function saveSupplierInvoice(SupplierInvoice $invoice): void
    {
        $exists = DB::table('erp_supplier_invoices')->where('id', '=', $invoice->id())->count() > 0;
        $row = $invoice->toRow();

        if ($exists) {
            unset($row['id'], $row['issued_at']);
            DB::table('erp_supplier_invoices')->where('id', '=', $invoice->id())->update($row);

            return;
        }

        DB::table('erp_supplier_invoices')->insert($row);
    }

    public function findSupplierInvoice(string $id): ?SupplierInvoice
    {
        $row = DB::table('erp_supplier_invoices')->where('id', '=', $id)->first();

        return $row !== null ? SupplierInvoice::fromRow($row) : null;
    }

    public function supplierInvoices(?string $supplierId = null, int $limit = 50): array
    {
        $query = DB::table('erp_supplier_invoices');
        if ($supplierId !== null && $supplierId !== '') {
            $query->where('supplier_id', '=', $supplierId);
        }

        $rows = $query->orderByDesc('issued_at')->limit($limit)->get();

        return \array_map(static fn (array $row): SupplierInvoice => SupplierInvoice::fromRow($row), $rows);
    }

    public function savePayment(Payment $payment): void
    {
        DB::table('erp_payments')->insert($payment->toRow());
    }

    public function paymentsOut(?string $supplierId = null, int $limit = 50): array
    {
        $query = DB::table('erp_payments')->where('direction', '=', 'out');
        if ($supplierId !== null && $supplierId !== '') {
            $query->where('supplier_id', '=', $supplierId);
        }

        $rows = $query->orderByDesc('paid_at')->limit($limit)->get();

        return \array_map(static fn (array $row): Payment => Payment::fromRow($row), $rows);
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $filters */
    private function applyPoFilters(mixed $query, array $filters): void
    {
        foreach (['status', 'supplier_id'] as $key) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where($key, '=', $value);
            }
        }

        $q = $filters['q'] ?? null;
        if (\is_string($q) && \trim($q) !== '') {
            $query->where('po_number', 'like', '%' . \trim($q) . '%');
        }
    }
}
