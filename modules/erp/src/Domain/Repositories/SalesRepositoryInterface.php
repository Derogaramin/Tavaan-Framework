<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesRepositoryInterface — پورت بافتهٔ فروش.
 */

namespace Modules\Erp\Domain\Repositories;

use Modules\Erp\Domain\Entities\Customer;
use Modules\Erp\Domain\Entities\Payment;
use Modules\Erp\Domain\Entities\SalesInvoice;
use Modules\Erp\Domain\Entities\SalesOrder;

/**
 * @api 1.0
 */
interface SalesRepositoryInterface
{
    // ── مشتریان ────────────────────────────────────────────────────────────

    /** @return list<Customer> */
    public function customers(): array;

    public function findCustomer(string $id): ?Customer;

    public function saveCustomer(Customer $customer): void;

    // ── سفارش فروش ─────────────────────────────────────────────────────────

    public function findSalesOrder(string $id, bool $withItems = true): ?SalesOrder;

    /**
     * @return list<SalesOrder>
     * @param array{status?: string, customer_id?: string, q?: string} $filters
     */
    public function salesOrders(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function countSalesOrders(array $filters = []): int;

    public function saveSalesOrder(SalesOrder $order): void;

    /** ماندهٔ حساب مشتری = فاکتورهای issued − دریافت‌ها (minor). */
    public function customerBalance(string $customerId): int;

    // ── فاکتور/دریافت ──────────────────────────────────────────────────────

    public function saveSalesInvoice(SalesInvoice $invoice): void;

    public function findSalesInvoice(string $id): ?SalesInvoice;

    /** @return list<SalesInvoice> */
    public function salesInvoices(?string $customerId = null, int $limit = 50): array;

    public function savePayment(Payment $payment): void;

    /** @return list<Payment> */
    public function paymentsIn(?string $customerId = null, int $limit = 50): array;
}
