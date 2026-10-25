<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ProcurementRepositoryInterface — پورت بافتهٔ تدارکات.
 */

namespace Modules\Erp\Domain\Repositories;

use Modules\Erp\Domain\Entities\GoodsReceipt;
use Modules\Erp\Domain\Entities\Payment;
use Modules\Erp\Domain\Entities\PurchaseOrder;
use Modules\Erp\Domain\Entities\Supplier;
use Modules\Erp\Domain\Entities\SupplierInvoice;

/**
 * @api 1.0
 */
interface ProcurementRepositoryInterface
{
    // ── تأمین‌کنندگان ──────────────────────────────────────────────────────

    /** @return list<Supplier> */
    public function suppliers(): array;

    public function findSupplier(string $id): ?Supplier;

    public function saveSupplier(Supplier $supplier): void;

    // ── سفارش خرید ─────────────────────────────────────────────────────────

    public function findPurchaseOrder(string $id, bool $withItems = true): ?PurchaseOrder;

    /**
     * @return list<PurchaseOrder>
     * @param array{status?: string, supplier_id?: string, q?: string} $filters
     */
    public function purchaseOrders(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function countPurchaseOrders(array $filters = []): int;

    public function savePurchaseOrder(PurchaseOrder $order): void;

    /** ماندهٔ حساب تأمین‌کننده = فاکتورهای open − پرداخت‌ها (minor). */
    public function supplierBalance(string $supplierId): int;

    // ── رسید/فاکتور/پرداخت ─────────────────────────────────────────────────

    public function saveGoodsReceipt(GoodsReceipt $receipt): void;

    public function findGoodsReceipt(string $id): ?GoodsReceipt;

    /** @return list<GoodsReceipt> */
    public function goodsReceipts(int $limit = 50): array;

    public function saveSupplierInvoice(SupplierInvoice $invoice): void;

    public function findSupplierInvoice(string $id): ?SupplierInvoice;

    /** @return list<SupplierInvoice> */
    public function supplierInvoices(?string $supplierId = null, int $limit = 50): array;

    public function savePayment(Payment $payment): void;

    /** @return list<Payment> */
    public function paymentsOut(?string $supplierId = null, int $limit = 50): array;
}
