<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ProcurementService — تدارکات: PO → تأیید → رسید → فاکتور → پرداخت (DOC-097 §2.3).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\GoodsReceipt;
use Modules\Erp\Domain\Entities\Payment;
use Modules\Erp\Domain\Entities\PurchaseOrder;
use Modules\Erp\Domain\Entities\PurchaseOrderItem;
use Modules\Erp\Domain\Entities\Supplier;
use Modules\Erp\Domain\Entities\SupplierInvoice;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Modules\Erp\Domain\Repositories\ProcurementRepositoryInterface;
use Modules\Erp\Domain\Services\ApprovalRouter;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Modules\Erp\Infrastructure\Workflow\PurchaseApprovalWorkflow;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Support\Ulid;

/**
 * ProcurementService — چرخهٔ خرید:
 *
 * ```
 * PO(draft) → submit → [workflow تأیید سلسله‌مراتبی بر اساس مبلغ] → approved
 * → رسید انبار (→ سند موجودی/پرداختنی با Outbox) → فاکتور → پرداخت (→ سند)
 * ```
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class ProcurementService
{
    public function __construct(
        private readonly ProcurementRepositoryInterface $repository,
        private readonly InventoryRepositoryInterface $inventoryRepository,
        private readonly InventoryService $inventoryService,
        private readonly AccountingService $accountingService,
        private readonly ApprovalRouter $approvalRouter,
        private readonly PurchaseApprovalWorkflow $workflow,
        private readonly OutboxPublisher $outbox,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    // ── تأمین‌کننده ────────────────────────────────────────────────────────

    public function createSupplier(string $code, string $name, ?string $phone = null, ?string $email = null, ?string $address = null): Supplier
    {
        $supplier = Supplier::create($code, $name, $phone, $email, $address);
        $this->repository->saveSupplier($supplier);

        return $supplier;
    }

    /** @return list<Supplier> */
    public function suppliers(): array
    {
        return $this->repository->suppliers();
    }

    public function findSupplier(string $id): ?Supplier
    {
        return $this->repository->findSupplier($id);
    }

    // ── سفارش خرید ─────────────────────────────────────────────────────────

    /**
     * ساخت سفارش خرید (draft).
     *
     * @param list<array{item_id: string, qty: float, unit_price: int}> $items
     */
    public function createPurchaseOrder(string $supplierId, array $items, ?int $userId = null): PurchaseOrder
    {
        if ($this->repository->findSupplier($supplierId) === null) {
            throw new \DomainException('تأمین‌کننده یافت نشد: ' . $supplierId);
        }

        $poId = Ulid::generate()->toString();
        $lineEntities = [];
        foreach ($items as $line) {
            $itemId = (string) ($line['item_id'] ?? '');
            if ($this->inventoryRepository->findItem($itemId) === null) {
                throw new \DomainException('کالا یافت نشد: ' . $itemId);
            }
            $lineEntities[] = PurchaseOrderItem::create(
                $poId,
                $itemId,
                (float) ($line['qty'] ?? 0),
                (int) ($line['unit_price'] ?? 0),
            );
        }

        $order = PurchaseOrder::create(self::number('PO'), $supplierId, $lineEntities, $userId, (string) Config::get('erp.currency', 'IRR'));
        $this->repository->savePurchaseOrder($order);

        $this->audit->record('purchase_order.created', 'erp.purchase_order', $order->id(), [
            'po_number' => $order->poNumber(),
            'supplier_id' => $supplierId,
            'total' => $order->total(),
        ], $userId);

        return $order;
    }

    /**
     * ثبت برای تأیید — مسیر از ApprovalRouter (مبلغ):
     * ≤ آستانهٔ مالی → تأیید خودکار؛ بیشتر → صف مدیر مالی (→ مدیرعامل اگر کلان).
     */
    public function submitForApproval(string $poId, ?int $userId = null): PurchaseOrder
    {
        $order = $this->mustFindOrder($poId);
        $target = $this->approvalRouter->routeOnSubmit($order->total());

        $instanceId = $this->workflow->start($poId, ['total' => $order->total()]);
        if ($instanceId !== null) {
            $order->attachWorkflow($instanceId);
        }

        $order->submitForApproval($target);
        $this->repository->savePurchaseOrder($order);

        if ($instanceId !== null) {
            if ($target->value === 'approved') {
                $this->workflow->fireAutoApprove($instanceId);
            } else {
                $this->workflow->fireToFinance($instanceId);
            }
        }

        if ($target->value === 'approved') {
            $this->publishApproved($order);
        }

        $this->audit->record('purchase_order.submitted', 'erp.purchase_order', $poId, [
            'po_number' => $order->poNumber(),
            'total' => $order->total(),
            'route' => $target->value,
        ], $userId);

        return $order;
    }

    /** تأیید مدیر مالی — اگر مبلغ > آستانهٔ CEO → صف مدیرعامل. */
    public function approveByFinance(string $poId, int $userId): PurchaseOrder
    {
        $order = $this->mustFindOrder($poId);
        $needsCeo = $this->approvalRouter->needsCeoAfterFinance($order->total());

        $order->approveByFinance($userId, $needsCeo);
        $this->repository->savePurchaseOrder($order);

        $instanceId = (string) ($order->workflowInstanceId() ?? '');
        if ($needsCeo) {
            $this->workflow->fireFinanceEscalate($instanceId);
        } else {
            $this->workflow->fireFinanceApprove($instanceId);
            $this->publishApproved($order);
        }

        $this->audit->record('purchase_order.finance_approved', 'erp.purchase_order', $poId, [
            'po_number' => $order->poNumber(),
            'by' => $userId,
            'escalated_to_ceo' => $needsCeo,
        ], $userId);

        return $order;
    }

    /** تأیید مدیرعامل (خرید کلان). */
    public function approveByCeo(string $poId, int $userId): PurchaseOrder
    {
        $order = $this->mustFindOrder($poId);

        $order->approveByCeo($userId);
        $this->repository->savePurchaseOrder($order);

        $this->workflow->fireCeoApprove((string) ($order->workflowInstanceId() ?? ''));
        $this->publishApproved($order);

        $this->audit->record('purchase_order.ceo_approved', 'erp.purchase_order', $poId, [
            'po_number' => $order->poNumber(),
            'by' => $userId,
        ], $userId);

        return $order;
    }

    public function reject(string $poId, int $userId, string $reason): PurchaseOrder
    {
        $order = $this->mustFindOrder($poId);
        $wasPendingCeo = $order->status()->value === 'pending_ceo';

        $order->reject($userId, $reason);
        $this->repository->savePurchaseOrder($order);

        if ($wasPendingCeo) {
            $this->workflow->fireRejectCeo((string) ($order->workflowInstanceId() ?? ''));
        } else {
            $this->workflow->fireReject((string) ($order->workflowInstanceId() ?? ''));
        }

        $this->audit->record('purchase_order.rejected', 'erp.purchase_order', $poId, [
            'po_number' => $order->poNumber(),
            'reason' => $reason,
            'by' => $userId,
        ], $userId);

        return $order;
    }

    // ── رسید انبار ─────────────────────────────────────────────────────────

    /**
     * ثبت رسید کالا — موجودی + (با Outbox) سند: بدهکار موجودی / بستانکار پرداختنی.
     *
     * @param list<array{item_id: string, qty: float, unit_cost?: int}> $lines
     */
    public function registerReceipt(?string $poId, string $warehouseId, array $lines, ?int $userId = null): GoodsReceipt
    {
        $supplierId = null;
        $order = null;
        if ($poId !== null) {
            $order = $this->mustFindOrder($poId);
            if (!$order->isApproved()) {
                throw new \DomainException('رسید فقط برای سفارش خرید تأییدشده ثبت می‌شود (وضعیت: ' . $order->status()->value . ').');
            }
            $supplierId = $order->supplierId();

            // بها از PO وقتی unit_cost داده نشده + کنترل مقدار سفارش
            foreach ($lines as &$line) {
                $itemId = (string) ($line['item_id'] ?? '');
                foreach ($order->items() as $poItem) {
                    if ($poItem->itemId() === $itemId) {
                        if (!isset($line['unit_cost'])) {
                            $line['unit_cost'] = $poItem->unitPrice();
                        }
                        $order->receiveItem($itemId, (float) ($line['qty'] ?? 0));
                        break;
                    }
                }
            }
            unset($line);
        }

        $receipt = GoodsReceipt::create(self::number('GR'), $warehouseId, $lines, $poId, $supplierId, $userId);

        $this->repository->saveGoodsReceipt($receipt);

        // موجودی + رویداد Outbox (سند توسط listener حسابداری)
        $this->inventoryService->receive($warehouseId, $lines, 'purchase_receipt', $receipt->id(), $userId);

        if ($order !== null) {
            $fullyReceived = true;
            foreach ($order->items() as $item) {
                if (!$item->isFullyReceived()) {
                    $fullyReceived = false;
                    break;
                }
            }
            if ($fullyReceived && $order->status()->value === 'approved') {
                $order->markReceived();
            }
            $this->repository->savePurchaseOrder($order);
        }

        $receipt->recordRegistered();
        foreach ($receipt->pullEvents() as $event) {
            $this->outbox->store($event);
        }
        $this->outbox->flushNow();

        $this->audit->record('goods_receipt.registered', 'erp.goods_receipt', $receipt->id(), [
            'receipt_number' => $receipt->receiptNumber(),
            'po_id' => $poId,
            'total_cost' => $receipt->totalCost(),
        ], $userId);

        return $receipt;
    }

    // ── فاکتور و پرداخت ────────────────────────────────────────────────────

    public function recordSupplierInvoice(string $supplierId, int $amount, ?string $poId = null, ?string $receiptId = null, ?int $userId = null): SupplierInvoice
    {
        if ($this->repository->findSupplier($supplierId) === null) {
            throw new \DomainException('تأمین‌کننده یافت نشد: ' . $supplierId);
        }

        $invoice = SupplierInvoice::create(self::number('SINV'), $supplierId, $amount, $poId, $receiptId);
        $this->repository->saveSupplierInvoice($invoice);

        if ($poId !== null) {
            $order = $this->mustFindOrder($poId);
            if ($order->status()->value === 'received') {
                $order->markInvoiced();
                $this->repository->savePurchaseOrder($order);
            }
        }

        $this->audit->record('supplier_invoice.recorded', 'erp.supplier_invoice', $invoice->id(), [
            'invoice_number' => $invoice->invoiceNumber(),
            'supplier_id' => $supplierId,
            'amount' => $amount,
        ], $userId);

        return $invoice;
    }

    /**
     * پرداخت به تأمین‌کننده — سند: بدهکار پرداختنی / بستانکار بانک یا صندوق.
     */
    public function paySupplierInvoice(string $invoiceId, string $method = 'bank', ?int $userId = null): Payment
    {
        $invoice = $this->repository->findSupplierInvoice($invoiceId);
        if ($invoice === null) {
            throw new \DomainException('فاکتور تأمین‌کننده یافت نشد: ' . $invoiceId);
        }
        if ($invoice->isPaid()) {
            throw new \DomainException('فاکتور پیش‌تر پرداخت شده است.');
        }
        if (!\in_array($method, ['cash', 'bank'], true)) {
            throw new \DomainException('روش پرداخت نامعتبر: ' . $method);
        }

        $payment = Payment::outToSupplier($invoice->supplierId(), $invoice->amount(), $method, $invoice->id(), $userId);

        $accounts = Config::get('erp.accounting.accounts', []);
        $voucher = $this->accountingService->issue(
            sourceModule: 'procurement',
            sourceId: $payment->id(),
            date: \Tavaan\Support\Time\Clock::utcNow()->format('Y-m-d'),
            description: 'پرداخت فاکتور ' . $invoice->invoiceNumber(),
            lineSpecs: [
                ['account_code' => (string) ($accounts['payable'] ?? '2101'), 'debit' => $invoice->amount(), 'description' => 'پرداخت به تأمین‌کننده'],
                ['account_code' => (string) ($accounts[$method === 'cash' ? 'cash' : 'bank'] ?? '1102'), 'credit' => $invoice->amount(), 'description' => 'پرداخت فاکتور ' . $invoice->invoiceNumber()],
            ],
            userId: $userId,
            autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
        );
        $payment->attachVoucher($voucher->id());

        $this->repository->savePayment($payment);
        $invoice->markPaid();
        $this->repository->saveSupplierInvoice($invoice);

        if ($invoice->poId() !== null) {
            $order = $this->repository->findPurchaseOrder($invoice->poId(), false);
            if ($order !== null && $order->status()->value === 'invoiced') {
                $order->markPaid();
                $this->repository->savePurchaseOrder($order);
            }
        }

        $this->audit->record('payment.out', 'erp.payment', $payment->id(), [
            'supplier_id' => $invoice->supplierId(),
            'amount' => $invoice->amount(),
            'method' => $method,
            'voucher_id' => $voucher->id(),
        ], $userId);

        return $payment;
    }

    // ── پرسش ───────────────────────────────────────────────────────────────

    public function findPurchaseOrder(string $id): ?PurchaseOrder
    {
        return $this->repository->findPurchaseOrder($id);
    }

    /**
     * @return list<PurchaseOrder>
     * @param array<string, mixed> $filters
     */
    public function purchaseOrders(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->repository->purchaseOrders($filters, $limit, $offset);
    }

    public function countPurchaseOrders(array $filters = []): int
    {
        return $this->repository->countPurchaseOrders($filters);
    }

    /** @return list<GoodsReceipt> */
    public function goodsReceipts(int $limit = 50): array
    {
        return $this->repository->goodsReceipts($limit);
    }

    public function findGoodsReceipt(string $id): ?GoodsReceipt
    {
        return $this->repository->findGoodsReceipt($id);
    }

    /** @return list<SupplierInvoice> */
    public function supplierInvoices(?string $supplierId = null, int $limit = 50): array
    {
        return $this->repository->supplierInvoices($supplierId, $limit);
    }

    public function findSupplierInvoice(string $id): ?SupplierInvoice
    {
        return $this->repository->findSupplierInvoice($id);
    }

    /** @return list<Payment> */
    public function paymentsOut(?string $supplierId = null, int $limit = 50): array
    {
        return $this->repository->paymentsOut($supplierId, $limit);
    }

    public function supplierBalance(string $supplierId): int
    {
        return $this->repository->supplierBalance($supplierId);
    }

    public function approvalRouter(): ApprovalRouter
    {
        return $this->approvalRouter;
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function publishApproved(PurchaseOrder $order): void
    {
        foreach ($order->pullEvents() as $event) {
            $this->outbox->store($event);
        }
        $this->outbox->flushNow();
    }

    private function mustFindOrder(string $id): PurchaseOrder
    {
        $order = $this->repository->findPurchaseOrder($id);
        if ($order === null) {
            throw new \DomainException('سفارش خرید یافت نشد: ' . $id);
        }

        return $order;
    }

    /** شمارهٔ یکتا: <PREFIX>-<Ymd>-<5hex> (الگوی CRM). */
    private static function number(string $prefix): string
    {
        return $prefix . '-' . \Tavaan\Support\Time\Clock::utcNow()->format('Ymd') . '-' . \strtoupper(\bin2hex(\random_bytes(3)));
    }
}
