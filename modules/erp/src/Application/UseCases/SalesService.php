<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * SalesService — فروش: سفارش → حواله → فاکتور → دریافت (DOC-097 §10 E2E-02).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\Customer;
use Modules\Erp\Domain\Entities\Payment;
use Modules\Erp\Domain\Entities\SalesInvoice;
use Modules\Erp\Domain\Entities\SalesOrder;
use Modules\Erp\Domain\Entities\SalesOrderItem;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Modules\Erp\Domain\Repositories\SalesRepositoryInterface;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * SalesService — چرخهٔ فروش:
 *
 * ```
 * SO(draft) → confirmed → delivered (حواله → سند COGS با Outbox)
 * → invoiced (فاکتور → سند درآمد/دریافتنی با Outbox) → دریافت (سند بانک)
 * ```
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class SalesService
{
    public function __construct(
        private readonly SalesRepositoryInterface $repository,
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly InventoryService $inventoryService,
        private readonly OutboxPublisher $outbox,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    // ── مشتری ──────────────────────────────────────────────────────────────

    public function createCustomer(string $code, string $name, ?string $phone = null, ?string $email = null, ?string $address = null): Customer
    {
        $customer = Customer::create($code, $name, $phone, $email, $address);
        $this->repository->saveCustomer($customer);

        return $customer;
    }

    /** @return list<Customer> */
    public function customers(): array
    {
        return $this->repository->customers();
    }

    public function findCustomer(string $id): ?Customer
    {
        return $this->repository->findCustomer($id);
    }

    // ── سفارش فروش ─────────────────────────────────────────────────────────

    /**
     * @param list<array{item_id: string, qty: float, unit_price: int}> $items
     */
    public function createOrder(string $customerId, string $warehouseId, array $items, ?int $userId = null): SalesOrder
    {
        if ($this->repository->findCustomer($customerId) === null) {
            throw new \DomainException('مشتری یافت نشد: ' . $customerId);
        }
        if ($this->inventoryRepository->findWarehouse($warehouseId) === null) {
            throw new \DomainException('انبار یافت نشد: ' . $warehouseId);
        }

        $orderId = Ulid::generate()->toString();
        $lineEntities = [];
        foreach ($items as $line) {
            $itemId = (string) ($line['item_id'] ?? '');
            $item = $this->inventoryRepository->findItem($itemId);
            if ($item === null) {
                throw new \DomainException('کالا یافت نشد: ' . $itemId);
            }
            if (!$item->isSold()) {
                throw new \DomainException('کالای «' . $item->name() . '» فروختنی نیست.');
            }
            $lineEntities[] = SalesOrderItem::create($orderId, $itemId, (float) ($line['qty'] ?? 0), (int) ($line['unit_price'] ?? 0));
        }

        $order = SalesOrder::create(self::number('SO'), $customerId, $warehouseId, $lineEntities, $userId);
        $this->repository->saveSalesOrder($order);

        return $order;
    }

    public function confirmOrder(string $orderId): SalesOrder
    {
        $order = $this->mustFindOrder($orderId);
        $order->confirm();
        $this->repository->saveSalesOrder($order);

        return $order;
    }

    /**
     * حوالهٔ سفارش — کاهش موجودی (کنترل کمبود) + snapshot بهای COGS
     * + سند بهای فروش با Outbox (بدهکار COGS / بستانکار موجودی).
     */
    public function deliver(string $orderId, ?int $userId = null): SalesOrder
    {
        $order = $this->mustFindOrder($orderId);

        $lines = [];
        foreach ($order->items() as $item) {
            $remaining = \round($item->qty() - $item->deliveredQty(), 3);
            if ($remaining <= 0) {
                continue;
            }

            $inventoryItem = $this->inventoryRepository->findItem($item->itemId());
            $unitCost = $inventoryItem !== null ? $inventoryItem->avgCost() : 0;
            $item->snapshotCost($unitCost);

            $lines[] = ['item_id' => $item->itemId(), 'qty' => $remaining];
        }

        if ($lines === []) {
            throw new \DomainException('همهٔ اقلام سفارش پیش‌تر تحویل شده‌اند.');
        }

        // کاهش موجودی (+ رویداد Outbox → سند COGS توسط listener)
        $this->inventoryService->issue($order->warehouseId(), $lines, 'sales_issue', $order->id(), $userId);

        foreach ($lines as $line) {
            $order->deliverItem((string) $line['item_id'], (float) $line['qty']);
        }
        $order->markDelivered();
        $this->repository->saveSalesOrder($order);

        $this->audit->record('sales_order.delivered', 'erp.sales_order', $order->id(), [
            'order_number' => $order->orderNumber(),
        ], $userId);

        return $order;
    }

    /**
     * صدور فاکتور — سند درآمد با Outbox (بدهکار دریافتنی / بستانکار درآمد).
     */
    public function issueInvoice(string $orderId, ?int $userId = null): SalesInvoice
    {
        $order = $this->mustFindOrder($orderId);
        if ($order->status()->value !== 'delivered') {
            throw new \DomainException('فاکتور فقط برای سفارش تحویل‌شده صادر می‌شود (وضعیت: ' . $order->status()->value . ').');
        }

        $cogs = 0;
        foreach ($order->items() as $item) {
            $cogs += (int) \round($item->deliveredQty() * $item->unitCostSnapshot());
        }

        $invoice = SalesInvoice::issue(self::number('INV'), $order->customerId(), $order->total(), $cogs, $order->id());
        $this->repository->saveSalesInvoice($invoice);

        $invoice->recordIssued();
        foreach ($invoice->pullEvents() as $event) {
            $this->outbox->store($event);
        }
        $this->outbox->flushNow();

        $order->markInvoiced();
        $this->repository->saveSalesOrder($order);

        $this->audit->record('sales_invoice.issued', 'erp.sales_invoice', $invoice->id(), [
            'invoice_number' => $invoice->invoiceNumber(),
            'customer_id' => $invoice->customerId(),
            'amount' => $invoice->amount(),
            'cogs' => $invoice->cogsAmount(),
        ], $userId);

        return $invoice;
    }

    /**
     * دریافت از مشتری — سند با Outbox (بدهکار بانک/صندوق / بستانکار دریافتنی).
     */
    public function receivePayment(string $invoiceId, string $method = 'bank', ?int $amount = null, ?int $userId = null): Payment
    {
        $invoice = $this->repository->findSalesInvoice($invoiceId);
        if ($invoice === null) {
            throw new \DomainException('فاکتور فروش یافت نشد: ' . $invoiceId);
        }
        if ($invoice->isPaid()) {
            throw new \DomainException('فاکتور پیش‌تر تسویه شده است.');
        }
        if (!\in_array($method, ['cash', 'bank'], true)) {
            throw new \DomainException('روش دریافت نامعتبر: ' . $method);
        }

        $amount ??= $invoice->amount();
        $payment = Payment::inFromCustomer($invoice->customerId(), $amount, $method, $invoice->id(), $userId);
        $this->repository->savePayment($payment);

        // رویداد → سند حسابداری (listener)
        $this->outbox->store(new \Modules\Erp\Domain\Events\PaymentWasReceived(
            $payment->id(),
            $payment->customerId() ?? '',
            $amount,
            $invoice->id(),
            $method,
            null,
        ));
        $this->outbox->flushNow();

        if ($amount >= $invoice->amount()) {
            $invoice->markPaid();
            $this->repository->saveSalesInvoice($invoice);
        }

        $this->audit->record('payment.in', 'erp.payment', $payment->id(), [
            'customer_id' => $invoice->customerId(),
            'amount' => $amount,
            'method' => $method,
        ], $userId);

        return $payment;
    }

    // ── پرسش ───────────────────────────────────────────────────────────────

    public function findOrder(string $id): ?SalesOrder
    {
        return $this->repository->findSalesOrder($id);
    }

    /**
     * @return list<SalesOrder>
     * @param array<string, mixed> $filters
     */
    public function orders(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->repository->salesOrders($filters, $limit, $offset);
    }

    public function countOrders(array $filters = []): int
    {
        return $this->repository->countSalesOrders($filters);
    }

    /** @return list<SalesInvoice> */
    public function invoices(?string $customerId = null, int $limit = 50): array
    {
        return $this->repository->salesInvoices($customerId, $limit);
    }

    public function findInvoice(string $id): ?SalesInvoice
    {
        return $this->repository->findSalesInvoice($id);
    }

    /** @return list<Payment> */
    public function paymentsIn(?string $customerId = null, int $limit = 50): array
    {
        return $this->repository->paymentsIn($customerId, $limit);
    }

    public function customerBalance(string $customerId): int
    {
        return $this->repository->customerBalance($customerId);
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function mustFindOrder(string $id): SalesOrder
    {
        $order = $this->repository->findSalesOrder($id);
        if ($order === null) {
            throw new \DomainException('سفارش فروش یافت نشد: ' . $id);
        }

        return $order;
    }

    private static function number(string $prefix): string
    {
        return $prefix . '-' . Clock::utcNow()->format('Ymd') . '-' . \strtoupper(\bin2hex(\random_bytes(3)));
    }
}
