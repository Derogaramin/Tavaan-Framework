<?php

declare(strict_types=1);

namespace Modules\Erp\Tests\Unit;

use Modules\Erp\Domain\Entities\Account;
use Modules\Erp\Domain\Entities\Attendance;
use Modules\Erp\Domain\Entities\Bom;
use Modules\Erp\Domain\Entities\FiscalPeriod;
use Modules\Erp\Domain\Entities\InventoryItem;
use Modules\Erp\Domain\Entities\Payslip;
use Modules\Erp\Domain\Entities\PurchaseOrder;
use Modules\Erp\Domain\Entities\PurchaseOrderItem;
use Modules\Erp\Domain\Entities\SalesOrder;
use Modules\Erp\Domain\Entities\SalesOrderItem;
use Modules\Erp\Domain\Entities\StockMovement;
use Modules\Erp\Domain\Entities\Voucher;
use Modules\Erp\Domain\Entities\VoucherLine;
use Modules\Erp\Domain\Entities\WorkOrder;
use Modules\Erp\Domain\Enums\AccountType;
use Modules\Erp\Domain\Enums\PeriodStatus;
use Modules\Erp\Domain\Enums\PurchaseOrderStatus;
use Modules\Erp\Domain\Enums\SalesOrderStatus;
use Modules\Erp\Domain\Enums\StockMovementType;
use Modules\Erp\Domain\Enums\VoucherStatus;
use Modules\Erp\Domain\Enums\WorkOrderStatus;
use Modules\Erp\Domain\Services\ApprovalRouter;
use Modules\Erp\Domain\Services\BomCosting;
use Modules\Erp\Domain\Services\PayrollCalculator;
use Modules\Erp\Domain\Services\TrialBalanceCalculator;
use Tavaan\Testing\TestCase;

/**
 * تست واحد دامنهٔ ERP — موجودیت‌ها/Enums/سرویس‌های خالص (DOC-097).
 *
 * قانون طلایی حسابداری (AC-ERP-09)، immutability سند posted، ماشین‌حالت‌ها،
 * ApprovalRouter (E2E-06 خالص)، بهایابی میانگین موزون و محاسبات گزارش.
 */
final class ErpDomainUnitTest extends TestCase
{
    // ─── Voucher — قانون طلایی (AC-ERP-09) ─────────────────────────────────

    public function testVoucherGoldenRuleBalanced(): void
    {
        $voucher = Voucher::draft('SV-1-00001', '2026-03-21', [
            VoucherLine::create(accountId: 11, debit: 5_000_000),
            VoucherLine::create(accountId: 21, credit: 5_000_000),
        ], 'سند تراز');

        self::assertTrue($voucher->isBalanced());
        self::assertSame(5_000_000, $voucher->totalDebit());
        self::assertSame(5_000_000, $voucher->totalCredit());

        $voucher->post(9);
        self::assertSame(VoucherStatus::Posted, $voucher->status());
        self::assertTrue($voucher->status()->isImmutable());
        self::assertNotNull($voucher->postedAt());
    }

    public function testVoucherUnbalancedCannotPost(): void
    {
        $voucher = Voucher::draft('SV-1-00002', '2026-03-21', [
            VoucherLine::create(accountId: 11, debit: 5_000_000),
            VoucherLine::create(accountId: 21, credit: 4_999_999),   // ✗ یک ریال اختلاف
        ]);

        self::assertFalse($voucher->isBalanced());

        try {
            $voucher->post(1);
            self::fail('سند ناتراز نباید post شود.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('تراز نیست', $e->getMessage());
        }
    }

    public function testVoucherSingleLineIsNotBalanced(): void
    {
        $voucher = Voucher::draft('SV-1-00003', '2026-03-21', [
            VoucherLine::create(accountId: 11, debit: 1_000),
        ]);

        self::assertFalse($voucher->isBalanced());
    }

    public function testVoucherPostedIsImmutableAndVoidsOnlyByReversal(): void
    {
        $voucher = Voucher::draft('SV-1-00004', '2026-03-21', [
            VoucherLine::create(accountId: 11, debit: 800),
            VoucherLine::create(accountId: 41, credit: 800),
        ]);
        $voucher->post(3);

        // ردیف جدید → رد
        try {
            $voucher->addLine(VoucherLine::create(accountId: 51, debit: 10));
            self::fail('سند posted نباید ردیف بگیرد.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('immutable', $e->getMessage());
        }

        // posted → بازگشت به draft/approve ممکن نیست
        try {
            $voucher->approve(2);
            self::fail('سند posted نباید دوباره تأیید شود.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('نامجاز', $e->getMessage());
        }

        // void فقط از Posted — و وضعیت voided پایانی است
        $draft = Voucher::draft('SV-1-00099', '2026-03-21', [
            VoucherLine::create(accountId: 11, debit: 10),
            VoucherLine::create(accountId: 31, credit: 10),
        ]);
        try {
            $draft->markVoidedBy('01J0000000000000000000009');
            self::fail('سند draft نباید void شود.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('ثبت‌شده', $e->getMessage());
        }
    }

    public function testVoucherReversalSwapsSidesAndStaysBalanced(): void
    {
        $voucher = Voucher::draft('SV-1-00005', '2026-03-21', [
            VoucherLine::create(accountId: 13, debit: 1_200_000, description: 'رسید'),
            VoucherLine::create(accountId: 21, credit: 1_200_000, description: 'رسید'),
        ], 'رسید خرید');
        $voucher->post(1);

        $reversal = $voucher->reverseVoucher('SV-1-00006', 2);

        self::assertTrue($reversal->isBalanced());
        self::assertSame(1_200_000, $reversal->totalCredit());
        self::assertSame(1_200_000, $reversal->totalDebit());
        self::assertSame($voucher->id(), $reversal->sourceId());

        // جهت‌ها جابه‌جا شده‌اند
        $lines = $reversal->lines();
        self::assertSame(1_200_000, $lines[0]->credit());
        self::assertSame(0, $lines[0]->debit());
        self::assertSame(1_200_000, $lines[1]->debit());

        // ابطال با ارجاع به معکوس
        $reversal->post(2);
        $voucher->markVoidedBy($reversal->id());
        self::assertSame(VoucherStatus::Voided, $voucher->status());
        self::assertSame($reversal->id(), $voucher->reverseOf());

        // voided دیگر هیچ انتقالی ندارد
        self::assertFalse(VoucherStatus::Voided->canTransitionTo(VoucherStatus::Posted));
    }

    public function testVoucherStatusTransitions(): void
    {
        self::assertTrue(VoucherStatus::Draft->canTransitionTo(VoucherStatus::Approved));
        self::assertTrue(VoucherStatus::Draft->canTransitionTo(VoucherStatus::Posted));
        self::assertTrue(VoucherStatus::Approved->canTransitionTo(VoucherStatus::Posted));
        self::assertTrue(VoucherStatus::Posted->canTransitionTo(VoucherStatus::Voided));
        self::assertFalse(VoucherStatus::Posted->canTransitionTo(VoucherStatus::Draft));
        self::assertFalse(VoucherStatus::Voided->canTransitionTo(VoucherStatus::Draft));
        self::assertFalse(VoucherStatus::Draft->canTransitionTo(VoucherStatus::Draft));
        self::assertTrue(VoucherStatus::Posted->countsInReports());
        self::assertFalse(VoucherStatus::Draft->countsInReports());
    }

    public function testVoucherPostedEvent(): void
    {
        $voucher = Voucher::draft('SV-1-00007', '2026-03-21', [
            VoucherLine::create(accountId: 11, debit: 100),
            VoucherLine::create(accountId: 31, credit: 100),
        ], sourceModule: 'manual');
        $voucher->post(5);

        $events = $voucher->pullEvents();
        self::assertCount(1, $events);
        self::assertSame('erp.voucher.posted', $events[0]->eventName());
        self::assertSame(100, $events[0]->toPayload()['total_debit']);
        self::assertSame([], $voucher->pullEvents());   // فقط یک‌بار
    }

    // ─── VoucherLine ───────────────────────────────────────────────────────

    public function testVoucherLineOneSidedOnly(): void
    {
        $this->expectException(\DomainException::class);
        VoucherLine::create(accountId: 1, debit: 100, credit: 100);   // هر دو سمت ✗
    }

    public function testVoucherLineZeroRejected(): void
    {
        $this->expectException(\DomainException::class);
        VoucherLine::create(accountId: 1);   // هیچ سمتی ✗
    }

    public function testVoucherLineNegativeRejected(): void
    {
        $this->expectException(\DomainException::class);
        VoucherLine::create(accountId: 1, debit: -5);
    }

    // ─── Account / FiscalPeriod ────────────────────────────────────────────

    public function testAccountNormalBalanceAndPostable(): void
    {
        $asset = Account::create('1301', 'کالای تجاری', AccountType::Asset);
        $revenue = Account::create('4101', 'درآمد فروش', AccountType::Revenue);

        self::assertSame('debit', $asset->normalBalance());
        self::assertSame('credit', $revenue->normalBalance());
        self::assertTrue($asset->isPostable());

        $asset->markBranch();
        self::assertFalse($asset->isPostable());

        self::assertTrue(AccountType::Revenue->isTemporary());
        self::assertTrue(AccountType::Expense->isTemporary());
        self::assertFalse(AccountType::Asset->isTemporary());
    }

    public function testFiscalPeriodContainsDateAndClosesOnce(): void
    {
        $period = FiscalPeriod::create('سال ۱۴۰۵', '2026-03-21', '2027-03-20');

        self::assertTrue($period->containsDate('2026-06-01'));
        self::assertFalse($period->containsDate('2027-04-01'));
        self::assertTrue($period->isOpen());

        $period->close('01J0000000000000000000001', 7);
        self::assertSame(PeriodStatus::Closed, $period->status());
        self::assertSame('01J0000000000000000000001', $period->closeVoucherId());

        $events = $period->pullEvents();
        self::assertCount(1, $events);
        self::assertSame('accounting.period.closed', $events[0]->eventName());

        $this->expectException(\DomainException::class);
        $period->close('01J0000000000000000000002', 7);
    }

    public function testFiscalPeriodInvalidRangeRejected(): void
    {
        $this->expectException(\DomainException::class);
        FiscalPeriod::create('معکوس', '2027-01-01', '2026-01-01');
    }

    // ─── ApprovalRouter (E2E-06 خالص — DOC-097 §3) ────────────────────────

    public function testApprovalRouterHierarchicalThresholds(): void
    {
        $router = new ApprovalRouter(100_000_000, 500_000_000);

        // ≤ ۱۰۰M → خودکار
        self::assertSame(PurchaseOrderStatus::Approved, $router->routeOnSubmit(100_000_000));
        self::assertFalse($router->needsApproval(50_000_000));

        // ۱۰۰M < مبلغ ≤ ۵۰۰M → مدیر مالی، بدون CEO
        self::assertSame(PurchaseOrderStatus::PendingFinance, $router->routeOnSubmit(250_000_000));
        self::assertFalse($router->needsCeoAfterFinance(500_000_000));

        // > ۵۰۰M → مالی سپس مدیرعامل
        self::assertTrue($router->needsApproval(900_000_000));
        self::assertTrue($router->needsCeoAfterFinance(900_000_000));
    }

    public function testApprovalRouterInvalidThresholds(): void
    {
        $this->expectException(\DomainException::class);
        new ApprovalRouter(500, 100);   // ceo < finance ✗
    }

    // ─── PurchaseOrder — چرخه و رسید ───────────────────────────────────────

    public function testPurchaseOrderApprovalFlow(): void
    {
        $order = PurchaseOrder::create('PO-1', '01SUP', [
            PurchaseOrderItem::create('PO-1-X', '01ITM', 10.0, 60_000_000),   // ۶۰۰M
        ], 4);
        self::assertSame(600_000_000, $order->total());

        $order->submitForApproval(PurchaseOrderStatus::PendingFinance);
        self::assertSame(PurchaseOrderStatus::PendingFinance, $order->status());

        $order->approveByFinance(5, needsCeo: true);
        self::assertSame(PurchaseOrderStatus::PendingCeo, $order->status());
        self::assertSame(5, $order->financeApprovedBy());
        self::assertSame([], $order->pullEvents());   // هنوز تأیید نهایی نشده

        $order->approveByCeo(6);
        self::assertSame(PurchaseOrderStatus::Approved, $order->status());
        self::assertSame(6, $order->ceoApprovedBy());
        self::assertNotNull($order->approvedAt());

        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertSame('procurement.po.approved', $events[0]->eventName());
        self::assertSame(600_000_000, $events[0]->toPayload()['total_minor']);

        // ادامهٔ چرخه
        $order->markReceived();
        $order->markInvoiced();
        $order->markPaid();
        self::assertTrue($order->status()->isFinal());
    }

    public function testPurchaseOrderRejectRequiresReasonAndApprovalState(): void
    {
        $order = PurchaseOrder::create('PO-2', '01SUP', [
            PurchaseOrderItem::create('PO-2-X', '01ITM', 1.0, 200_000_000),
        ]);
        $order->submitForApproval(PurchaseOrderStatus::PendingFinance);

        try {
            $order->reject(3, '   ');
            self::fail('رد بدون دلیل نباید ممکن باشد.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('دلیل', $e->getMessage());
        }

        $order->reject(3, 'بودجه ندارد');
        self::assertSame(PurchaseOrderStatus::Rejected, $order->status());
        self::assertSame('بودجه ندارد', $order->rejectReason());
    }

    public function testPurchaseOrderReceiveGuardsOverReceipt(): void
    {
        $order = PurchaseOrder::create('PO-3', '01SUP', [
            PurchaseOrderItem::create('PO-3-X', '01ITM', 5.0, 1_000),
        ]);

        $order->receiveItem('01ITM', 3.0);
        self::assertFalse($order->items()[0]->isFullyReceived());
        $order->receiveItem('01ITM', 2.0);
        self::assertTrue($order->items()[0]->isFullyReceived());

        $this->expectException(\DomainException::class);
        $order->receiveItem('01ITM', 0.5);   // بیش از سفارش ✗
    }

    public function testPurchaseOrderStatusGuards(): void
    {
        $order = PurchaseOrder::create('PO-4', '01SUP', [
            PurchaseOrderItem::create('PO-4-X', '01ITM', 1.0, 10),
        ]);

        $this->expectException(\DomainException::class);
        $order->approveByFinance(1, false);   // هنوز submit نشده ✗
    }

    // ─── SalesOrder / WorkOrder — ماشین‌حالت ───────────────────────────────

    public function testSalesOrderLifecycle(): void
    {
        $order = SalesOrder::create('SO-1', '01CUS', '01WH', [
            SalesOrderItem::create('SO-1-X', '01ITM', 3.0, 900_000),
        ]);
        self::assertSame(2_700_000, $order->total());

        $order->confirm();
        self::assertSame(SalesOrderStatus::Confirmed, $order->status());
        $order->markDelivered();
        self::assertNotNull($order->deliveredAt());
        $order->markInvoiced();
        self::assertSame(SalesOrderStatus::Invoiced, $order->status());

        $this->expectException(\DomainException::class);
        $order->cancel();   // پس از فاکتور ✗
    }

    public function testWorkOrderCompletionComputesUnitCost(): void
    {
        $order = WorkOrder::plan('WO-1', '01PRD', '01BOM', '01WH', 10.0);
        $order->start();
        self::assertSame(WorkOrderStatus::InProgress, $order->status());

        $order->applyMaterialCost(23_450_000);
        $order->complete(7.0);

        self::assertSame(WorkOrderStatus::Completed, $order->status());
        self::assertSame(3_350_000, $order->unitCost());   // round(23.45M/7)
        self::assertNotNull($order->completedAt());

        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertSame('production.order.completed', $events[0]->eventName());
        self::assertSame(23_450_000, $events[0]->toPayload()['material_cost']);
    }

    // ─── بهایابی میانگین موزون ─────────────────────────────────────────────

    public function testInventoryItemWeightedAverage(): void
    {
        $item = InventoryItem::create('SKU-A', 'کالا A', initialAvgCost: 1_000);

        // ۱۰ عدد @1000 موجود + ۱۰ عدد @1400 رسید → میانگین ۱۲۰۰
        $item->applyReceiptToAverage(10.0, 10.0, 1_400);
        self::assertSame(1_200, $item->avgCost());

        // رسید در انبار خالی → همان بهای رسید
        $empty = InventoryItem::create('SKU-B', 'کالا B', initialAvgCost: 5_000);
        $empty->applyReceiptToAverage(0.0, 4.0, 900);
        self::assertSame(900, $empty->avgCost());

        // گرد‌کردن half-up
        $rounding = InventoryItem::create('SKU-C', 'کالا C', initialAvgCost: 100);
        $rounding->applyReceiptToAverage(2.0, 1.0, 101);   // (200+101)/3 = 100.33 → 100
        self::assertSame(100, $rounding->avgCost());
    }

    public function testInventoryItemReorderPoint(): void
    {
        $item = InventoryItem::create('SKU-D', 'کالا D', reorderPoint: 5.0);
        self::assertTrue($item->needsReorder(5.0));
        self::assertTrue($item->needsReorder(2.0));
        self::assertFalse($item->needsReorder(6.0));
    }

    // ─── StockMovement / Bom ───────────────────────────────────────────────

    public function testStockMovementSignedQuantityAndValue(): void
    {
        $in = StockMovement::record('WH', 'ITM', StockMovementType::Receipt, 4.0, 1_500);
        $out = StockMovement::record('WH', 'ITM', StockMovementType::Issue, 2.5, 1_500);

        self::assertSame(4.0, $in->signedQuantity());
        self::assertSame(-2.5, $out->signedQuantity());
        self::assertSame(6_000, $in->totalValue());
        self::assertSame(3_750, $out->totalValue());

        $this->expectException(\DomainException::class);
        StockMovement::record('WH', 'ITM', StockMovementType::Receipt, 0.0);
    }

    public function testBomExplodeAndSelfReferenceRejected(): void
    {
        $bom = Bom::create('PRD', [
            ['item_id' => 'RAW-1', 'qty' => 2.0],
            ['item_id' => 'RAW-2', 'qty' => 0.5],
        ]);

        $exploded = $bom->explodeFor(4.0);
        self::assertSame(8.0, $exploded[0]['qty']);
        self::assertSame(2.0, $exploded[1]['qty']);

        try {
            Bom::create('PRD', [['item_id' => 'PRD', 'qty' => 1.0]]);
            self::fail('جزء = خود محصول باید رد شود.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('چرخه', $e->getMessage());
        }
    }

    public function testBomCostingTotalsAndShortages(): void
    {
        $costing = new BomCosting();

        $result = $costing->costOf(
            [['item_id' => 'RAW-1', 'qty' => 3.0], ['item_id' => 'RAW-2', 'qty' => 2.0]],
            ['RAW-1' => 1_000, 'RAW-2' => 2_500],
            ['RAW-1' => 10.0, 'RAW-2' => 1.0],   // RAW-2 کمبود دارد
        );

        self::assertSame(8_000, $result['total_cost']);   // 3×1000 + 2×2500
        self::assertSame(['RAW-2'], $result['shortages']);
    }

    // ─── PayrollCalculator ─────────────────────────────────────────────────

    public function testPayrollCalculatorFormula(): void
    {
        $calc = new PayrollCalculator(overtimeRate: 1.4, monthlyHours: 220);

        // پایه ۲۲۰M + ۱۰ ساعت اضافه‌کاری: hourly=1M → overtime=14M
        $result = $calc->calculate(220_000_000, 10.0, 5_000_000);
        self::assertSame(14_000_000, $result['overtime']);
        self::assertSame(234_000_000, $result['gross']);
        self::assertSame(0, $result['tax']);
        self::assertSame(229_000_000, $result['net']);
    }

    public function testPayrollCalculatorTaxThreshold(): void
    {
        $calc = new PayrollCalculator(1.4, 220, taxRate: 0.1, taxThreshold: 100_000_000);

        $result = $calc->calculate(150_000_000, 0.0, 0);
        // gross=150M > 100M → tax = (150−100)×0.1 = 5M
        self::assertSame(5_000_000, $result['tax']);
        self::assertSame(145_000_000, $result['net']);
    }

    public function testPayslipNegativeNetRejected(): void
    {
        $this->expectException(\DomainException::class);
        Payslip::create('RUN', 'EMP', '2026-10', 1_000, 0, 5_000, 0);   // net < 0 ✗
    }

    public function testAttendancePeriodFormat(): void
    {
        $attendance = Attendance::record('EMP', '2026-10', 176.0, 12.5);
        self::assertSame('2026-10', $attendance->periodMonth());

        $this->expectException(\DomainException::class);
        Attendance::record('EMP', '2026/10', 100.0);
    }

    // ─── TrialBalanceCalculator (گزارش‌ها/بستن دوره) ───────────────────────

    public function testTrialBalanceNormalBalanceSigns(): void
    {
        $calculator = new TrialBalanceCalculator();

        $rows = $calculator->trialBalance([
            // دارایی: debit-nature — ۱۰۰۰ debit / ۲۰۰ credit → مانده ۸۰۰
            ['account_id' => 1, 'code' => '1102', 'title' => 'بانک', 'type' => 'asset', 'debit' => 1_000, 'credit' => 200],
            // درآمد: credit-nature — ۵۰۰ credit → مانده ۵۰۰
            ['account_id' => 2, 'code' => '4101', 'title' => 'درآمد', 'type' => 'revenue', 'debit' => 0, 'credit' => 500],
        ]);

        self::assertSame(800, $rows[0]['balance']);
        self::assertSame(500, $rows[1]['balance']);
        self::assertSame('1102', $rows[0]['code']);   // مرتب بر اساس کد
    }

    public function testStatementTotalsFundamentalEquation(): void
    {
        $calculator = new TrialBalanceCalculator();

        // ساختار تراز: دارایی ۱۰۰۰ = بدهی ۳۰۰ + سرمایه ۲۰۰ + (درآمد ۸۰۰ − هزینه ۳۰۰)
        $totals = $calculator->statementTotals([
            ['account_id' => 1, 'type' => 'asset', 'debit' => 1_000, 'credit' => 0],
            ['account_id' => 2, 'type' => 'liability', 'debit' => 0, 'credit' => 300],
            ['account_id' => 3, 'type' => 'equity', 'debit' => 0, 'credit' => 200],
            ['account_id' => 4, 'type' => 'revenue', 'debit' => 0, 'credit' => 800],
            ['account_id' => 5, 'type' => 'expense', 'debit' => 300, 'credit' => 0],
        ]);

        self::assertSame(1_000, $totals['assets']);
        self::assertSame(500, $totals['net_income']);
        self::assertTrue($totals['balanced']);

        // ناتراز
        $broken = $calculator->statementTotals([
            ['account_id' => 1, 'type' => 'asset', 'debit' => 999, 'credit' => 0],
            ['account_id' => 4, 'type' => 'revenue', 'debit' => 0, 'credit' => 800],
            ['account_id' => 5, 'type' => 'expense', 'debit' => 300, 'credit' => 0],
        ]);
        self::assertFalse($broken['balanced']);
    }

    public function testLinesAreBalancedGuard(): void
    {
        $calculator = new TrialBalanceCalculator();

        self::assertTrue($calculator->linesAreBalanced([
            ['debit' => 500, 'credit' => 0],
            ['debit' => 0, 'credit' => 500],
        ]));
        self::assertFalse($calculator->linesAreBalanced([
            ['debit' => 500, 'credit' => 0],
            ['debit' => 0, 'credit' => 400],
        ]));
    }
}
