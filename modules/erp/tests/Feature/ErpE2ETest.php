<?php

declare(strict_types=1);

namespace Modules\Erp\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Modules\Erp\Application\UseCases\AccountingService;
use Modules\Erp\Application\UseCases\ErpReportService;
use Modules\Erp\Application\UseCases\HrService;
use Modules\Erp\Application\UseCases\InventoryService;
use Modules\Erp\Application\UseCases\PeriodCloseService;
use Modules\Erp\Application\UseCases\ProcurementService;
use Modules\Erp\Application\UseCases\ProductionService;
use Modules\Erp\Application\UseCases\SalesService;
use Modules\Erp\Domain\Repositories\AccountingRepositoryInterface;
use Modules\Erp\Domain\Repositories\HrRepositoryInterface;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Modules\Erp\Domain\Repositories\ProcurementRepositoryInterface;
use Modules\Erp\Domain\Repositories\SalesRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول ERP — سناریوهای DOC-097 §10 (E2E-01..08).
 *
 * مسیر واقعی (سرویس‌ها + Outbox + listenerهای حسابداری + workflow + audit)
 * روی SQLite in-memory — بدون I/O خارجی.
 *
 * | E2E-01 | خرید: PO → رسید → سند → پرداخت |
 * | E2E-02 | فروش: سفارش → حواله → فاکتور → سند درآمد |
 * | E2E-03 | تولید: BOM → work order → مصرف → محصول → بها |
 * | E2E-04 | حقوق: کارکرد → فیش → سند |
 * | E2E-05 | بستن دوره → ترازنامه متوازن |
 * | E2E-06 | Workflow تأیید سلسله‌مراتبی خرید |
 * | E2E-07 | Audit trail کامل |
 * | E2E-08 | تراز سند (debit=credit) در همه |
 */
final class ErpE2ETest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*']);
        $config->set('app.url', 'http://localhost');
        $config->set('mail.default', 'array');

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
        $this->seedChart();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    // ─── E2E-01: خرید — PO → رسید → سند → پرداخت ──────────────────────────

    public function testE2E01_ProcurementCycleWithVouchers(): void
    {
        $user = $this->user('proc@erp.test', 'کارشناس خرید', 'procurement-officer');
        $this->actingAs($user);

        [$procurement, $inventory] = $this->services();
        $supplier = $procurement->createSupplier('SUP-1', 'فناوری پارس‌تک');
        $warehouse = $inventory->createWarehouse('WH-1', 'انبار مرکزی');
        $item = $inventory->createItem('SKU-1', 'لپ‌تاپ صنعتی', initialAvgCost: 0);

        // PO کوچک (۵۰M ≤ آستانهٔ مالی) → submit → تأیید خودکار
        $order = $procurement->createPurchaseOrder($supplier->id(), [
            ['item_id' => $item->id(), 'qty' => 5.0, 'unit_price' => 10_000_000],
        ], $user->id);
        self::assertSame(50_000_000, $order->total());

        $order = $procurement->submitForApproval($order->id(), $user->id);
        self::assertSame('approved', $order->status()->value);

        // رسید انبار → موجودی + سند (بدهکار موجودی / بستانکار پرداختنی)
        $receipt = $procurement->registerReceipt($order->id(), $warehouse->id(), [
            ['item_id' => $item->id(), 'qty' => 5.0],
        ], $user->id);
        self::assertSame(50_000_000, $receipt->totalCost());

        $stock = $inventory->stockOf($warehouse->id(), $item->id());
        self::assertSame(5.0, $stock['quantity']);

        // میانگین موزون کالا به بهای خرید رسید
        $reloaded = $this->app()->make(InventoryRepositoryInterface::class)->findItem($item->id());
        self::assertSame(10_000_000, $reloaded->avgCost());

        // سند موجودی — posted و تراز (Outbox → listener)
        $voucher = $this->voucherForSource('inventory');
        self::assertNotNull($voucher);
        self::assertSame('posted', $voucher->status()->value);
        self::assertSame(50_000_000, $voucher->totalDebit());
        self::assertTrue($voucher->isBalanced());

        // PO رسیدشده
        $order = $procurement->findPurchaseOrder($order->id());
        self::assertSame('received', $order->status()->value);

        // فاکتور تأمین‌کننده → پرداخت → سند پرداختنی/بانک
        $invoice = $procurement->recordSupplierInvoice($supplier->id(), 50_000_000, $order->id(), $receipt->id(), $user->id);
        $payment = $procurement->paySupplierInvoice($invoice->id(), 'bank', $user->id);
        self::assertSame(50_000_000, $payment->amount());
        self::assertNotNull($payment->voucherId());

        $order = $procurement->findPurchaseOrder($order->id());
        self::assertSame('paid', $order->status()->value);
        self::assertSame(0, $procurement->supplierBalance($supplier->id()));

        // دو سند صادر شده (موجودی + پرداخت) — هر دو posted
        self::assertSame(2, $this->countVouchers('posted'));
        // outbox تخلیه شده (بدون نشت — AC-ERP-11)
        self::assertSame(0, (int) DB::table('tvn_outbox')->where('status', 'pending')->count());
    }

    // ─── E2E-02: فروش — سفارش → حواله → فاکتور → سند درآمد ────────────────

    public function testE2E02_SalesCycleWithCogsAndRevenueVouchers(): void
    {
        $user = $this->user('sales@erp.test', 'کارشناس فروش', 'sales-officer');
        $this->actingAs($user);

        [$procurement, $inventory, , $sales] = $this->services();
        $customer = $sales->createCustomer('CUS-1', 'بازرگانی نگین');
        $warehouse = $inventory->createWarehouse('WH-1', 'انبار مرکزی');
        $item = $inventory->createItem('SKU-2', 'ماوس بی‌سیم', initialAvgCost: 600_000);

        // موجودی اولیه
        $inventory->receive($warehouse->id(), [
            ['item_id' => $item->id(), 'qty' => 20.0, 'unit_cost' => 600_000],
        ], 'opening', null, $user->id);

        // سفارش فروش ۱۰ عدد @ ۹۰۰٬۰۰۰
        $order = $sales->createOrder($customer->id(), $warehouse->id(), [
            ['item_id' => $item->id(), 'qty' => 10.0, 'unit_price' => 900_000],
        ], $user->id);
        $sales->confirmOrder($order->id());

        // حواله → موجودی ۱۰ + سند COGS (بدهکار ۵۱۰۱ / بستانکار ۱۳۰۱) ۶M
        $sales->deliver($order->id(), $user->id);
        $stock = $inventory->stockOf($warehouse->id(), $item->id());
        self::assertSame(10.0, $stock['quantity']);

        $cogsVoucher = $this->voucherForSource('inventory');
        self::assertNotNull($cogsVoucher);
        self::assertSame(6_000_000, $cogsVoucher->totalDebit());

        // فاکتور → سند درآمد (بدهکار دریافتنی / بستانکار درآمد) ۹M
        $invoice = $sales->issueInvoice($order->id(), $user->id);
        self::assertSame(9_000_000, $invoice->amount());
        self::assertSame(6_000_000, $invoice->cogsAmount());

        $revenueVoucher = $this->voucherForSource('sales', $invoice->id());
        self::assertNotNull($revenueVoucher);
        self::assertSame(9_000_000, $revenueVoucher->totalDebit());
        self::assertTrue($revenueVoucher->isBalanced());

        // دریافت → سند بانک/دریافتنی
        $payment = $sales->receivePayment($invoice->id(), 'bank', null, $user->id);
        self::assertSame(9_000_000, $payment->amount());
        self::assertSame(0, $sales->customerBalance($customer->id()));

        // ۴ سند: opening (موجودی/سرمایه) + COGS + درآمد + دریافت — همه posted و تراز
        self::assertSame(4, $this->countVouchers('posted'));
        $this->assertAllVouchersBalanced();
    }

    public function testSalesIssueRejectsInsufficientStock(): void
    {
        $user = $this->user('sales2@erp.test', 'فروش ۲', 'sales-officer');
        [, $inventory, , $sales] = $this->services();

        $customer = $sales->createCustomer('CUS-2', 'مشتری ۲');
        $warehouse = $inventory->createWarehouse('WH-2', 'انبار ۲');
        $item = $inventory->createItem('SKU-3', 'کالای کمیاب');
        $inventory->receive($warehouse->id(), [['item_id' => $item->id(), 'qty' => 2.0, 'unit_cost' => 100]], 'opening');

        $order = $sales->createOrder($customer->id(), $warehouse->id(), [
            ['item_id' => $item->id(), 'qty' => 5.0, 'unit_price' => 200],
        ], $user->id);
        $sales->confirmOrder($order->id());

        try {
            $sales->deliver($order->id(), $user->id);
            self::fail('حواله با موجودی ناکافی باید رد شود.');
        } catch (\DomainException $e) {
            self::assertMatchesRegex('/ناکافی/', $e->getMessage());
        }
    }

    // ─── E2E-03: تولید — BOM → work order → مصرف → محصول → بها ─────────────

    public function testE2E03_ProductionCycleWithCostVouchers(): void
    {
        $user = $this->user('prod@erp.test', 'برنامه‌ریز تولید', 'production-planner');
        $this->actingAs($user);

        [, $inventory, , , $production] = $this->services();
        $warehouse = $inventory->createWarehouse('WH-P', 'انبار تولید');

        $board = $inventory->createItem('RAW-1', 'برد الکترونیکی', kind: 'raw_material', initialAvgCost: 1_000_000);
        $case = $inventory->createItem('RAW-2', 'قاب آلومینیومی', kind: 'raw_material', initialAvgCost: 500_000);
        $device = $inventory->createItem('FG-1', 'دستگاه کنترل دسترسی', kind: 'finished', isSold: true);

        $inventory->receive($warehouse->id(), [
            ['item_id' => $board->id(), 'qty' => 20.0, 'unit_cost' => 1_000_000],
            ['item_id' => $case->id(), 'qty' => 20.0, 'unit_cost' => 500_000],
        ], 'opening', null, $user->id);

        // BOM: هر دستگاه = ۲ برد + ۱ قاب
        $bom = $production->createBom($device->id(), [
            ['item_id' => $board->id(), 'qty' => 2.0],
            ['item_id' => $case->id(), 'qty' => 1.0],
        ]);
        self::assertTrue($bom->isActive());

        // work order برای ۵ دستگاه
        $order = $production->planWorkOrder($device->id(), 5.0, $warehouse->id(), $user->id);

        // start → مصرف مواد: ۱۰ برد + ۵ قاب = ۱۲٬۵۰۰٬۰۰۰ ریال → سند WIP/مواد
        $production->startWorkOrder($order->id(), $user->id);
        $order = $production->findWorkOrder($order->id());
        self::assertSame(12_500_000, $order->materialCost());
        self::assertSame('in_progress', $order->status()->value);

        $boardStock = $inventory->stockOf($warehouse->id(), $board->id());
        self::assertSame(10.0, $boardStock['quantity']);   // 20 − 10

        $consumeVoucher = $this->voucherForSource('inventory');
        self::assertNotNull($consumeVoucher);
        self::assertSame(12_500_000, $consumeVoucher->totalDebit());

        // complete → رسید ۵ محصول @ 2.5M + سند محصول/WIP
        $production->completeWorkOrder($order->id(), 5.0, $user->id);
        $order = $production->findWorkOrder($order->id());
        self::assertSame('completed', $order->status()->value);
        self::assertSame(2_500_000, $order->unitCost());

        $deviceStock = $inventory->stockOf($warehouse->id(), $device->id());
        self::assertSame(5.0, $deviceStock['quantity']);

        $reloadedDevice = $this->app()->make(InventoryRepositoryInterface::class)->findItem($device->id());
        self::assertSame(2_500_000, $reloadedDevice->avgCost());

        // اسناد: مصرف (WIP/مواد) + تکمیل (محصول/WIP) + opening receipt (سرمایه) = ۳ سند
        self::assertSame(3, $this->countVouchers('posted'));
        $this->assertAllVouchersBalanced();

        // گزارش بهای تمام‌شده
        $reports = $this->app()->make(ErpReportService::class);
        $rows = $reports->productionCost();
        self::assertSame(2_500_000, $rows[0]['unit_cost']);
    }

    public function testProductionRejectsStartWithShortage(): void
    {
        $user = $this->user('prod2@erp.test', 'تولید ۲', 'production-planner');
        [, $inventory, , , $production] = $this->services();

        $warehouse = $inventory->createWarehouse('WH-X', 'انبار X');
        $raw = $inventory->createItem('RAW-X', 'مادهٔ کمیاب', kind: 'raw_material', initialAvgCost: 100);
        $product = $inventory->createItem('FG-X', 'محصول X', kind: 'finished');

        $production->createBom($product->id(), [['item_id' => $raw->id(), 'qty' => 3.0]]);
        $order = $production->planWorkOrder($product->id(), 10.0, $warehouse->id(), $user->id);

        try {
            $production->startWorkOrder($order->id(), $user->id);
            self::fail('شروع تولید با کمبود مواد باید رد شود.');
        } catch (\DomainException $e) {
            self::assertMatchesRegex('/ناکافی/', $e->getMessage());
        }
    }

    // ─── E2E-04: حقوق — کارکرد → فیش → سند ────────────────────────────────

    public function testE2E04_PayrollCycleWithExpenseVoucher(): void
    {
        $user = $this->user('hr@erp.test', 'کارشناس حقوق', 'hr-officer');
        $this->actingAs($user);

        [, , , , , $hr] = $this->services();

        $employee = $hr->createEmployee('EMP-1', 'رضا کریمی', 220_000_000, 'انباردار', '0012345678');
        $hr->createEmployee('EMP-2', 'نگار موسوی', 110_000_000, 'کارشناس فروش');
        $month = Clock::utcNow()->format('Y-m');
        $hr->recordAttendance($employee->id(), $month, 176.0, 10.0);

        // 🔒 national_id رمزنگاری‌شده در DB
        $raw = DB::table('erp_employees')->where('id', $employee->id())->first();
        self::assertNotNull($raw['national_id']);
        self::assertNotSame('0012345678', (string) $raw['national_id']);
        $reloaded = $this->app()->make(HrRepositoryInterface::class)->findEmployee($employee->id());
        self::assertSame('0012345678', $reloaded->nationalId());

        // پردازش — EMP-1: 220M + (1M×1.4×10=14M) = 234M؛ EMP-2: 110M
        $run = $hr->runPayroll($month, $user->id);
        self::assertSame('processed', $run->status());
        self::assertSame(344_000_000, $run->totalGross());
        self::assertSame(344_000_000, $run->totalNet());

        $slips = $hr->payslipsOf($run->id());
        self::assertCount(2, $slips);

        // سند هزینهٔ حقوق (Outbox → listener): بدهکار ۵۲۰۱ / بستانکار ۲۲۰۱
        $voucher = $this->voucherForSource('hr', $run->id());
        self::assertNotNull($voucher);
        self::assertSame('posted', $voucher->status()->value);
        self::assertSame(344_000_000, $voucher->totalDebit());

        // پرداخت → سند پرداختنی/بانک
        $hr->payPayroll($run->id(), 'bank', $user->id);
        $run = $hr->findPayrollRun($run->id());
        self::assertSame('paid', $run->status());

        self::assertSame(2, $this->countVouchers('posted'));
        $this->assertAllVouchersBalanced();

        // پردازش دوبارهٔ همان ماه — run تازه (draft جدا) بدون تداخل
        $run2 = $hr->runPayroll($month, $user->id);
        self::assertNotSame($run->id(), $run2->id());
    }

    // ─── E2E-05: بستن دوره → ترازنامه متوازن ──────────────────────────────

    public function testE2E05_PeriodCloseProducesBalancedSheet(): void
    {
        $user = $this->user('fin@erp.test', 'مدیر مالی', 'finance-manager');
        $this->actingAs($user);

        [$procurement, $inventory, , $sales] = $this->services();

        // یک دورهٔ مالی باز برای امسال
        $accounting = $this->app()->make(AccountingService::class);
        $year = (int) Clock::utcNow()->format('Y');
        $period = $accounting->createPeriod('سال مالی ' . $year, $year . '-01-01', $year . '-12-31');

        // فعالیت: خرید + رسید (هزینهٔ موجودی → دارایی) و فروش (درآمد + COGS)
        $supplier = $procurement->createSupplier('SUP-C', 'تأمین‌کنندهٔ بسته');
        $customer = $sales->createCustomer('CUS-C', 'مشتری بسته');
        $warehouse = $inventory->createWarehouse('WH-C', 'انبار بسته');
        $item = $inventory->createItem('SKU-C', 'کالای بسته', initialAvgCost: 700_000);

        $inventory->receive($warehouse->id(), [['item_id' => $item->id(), 'qty' => 10.0, 'unit_cost' => 700_000]], 'opening', null, $user->id);

        $order = $sales->createOrder($customer->id(), $warehouse->id(), [
            ['item_id' => $item->id(), 'qty' => 4.0, 'unit_price' => 1_200_000],
        ], $user->id);
        $sales->confirmOrder($order->id());
        $sales->deliver($order->id(), $user->id);
        $sales->issueInvoice($order->id(), $user->id);

        // پیش از بستن: درآمد/هزینه مانده دارند
        $reports = $this->app()->make(ErpReportService::class);
        $before = $reports->profitLoss();
        self::assertSame(4_800_000, $before['revenue']);
        self::assertSame(2_800_000, $before['expense']);

        // بستن دوره
        $close = $this->app()->make(PeriodCloseService::class);
        $closedPeriod = $close->closePeriod($period->id(), $user->id);
        self::assertSame('closed', $closedPeriod->status()->value);
        self::assertNotNull($closedPeriod->closeVoucherId());

        // سند بستن — posted و تراز
        $closingVoucher = $this->app()->make(AccountingRepositoryInterface::class)->findVoucher((string) $closedPeriod->closeVoucherId());
        self::assertNotNull($closingVoucher);
        self::assertSame('posted', $closingVoucher->status()->value);
        self::assertTrue($closingVoucher->isBalanced());

        // پس از بستن: ماندهٔ موقت‌ها صفر → سود خالص صفر؛ ترازنامه متوازن
        $after = $reports->profitLoss();
        self::assertSame(0, $after['revenue']);
        self::assertSame(0, $after['expense']);

        $sheet = $reports->balanceSheet();
        self::assertTrue($sheet['balanced']);
        // equity = سرمایهٔ افتتاحیه (رسید opening → 3101 = 7M) + سود انباشتهٔ دوره (2M)
        self::assertSame(9_000_000, $sheet['equity']);
        self::assertSame($sheet['assets'], $sheet['liabilities'] + $sheet['equity'] + $sheet['net_income']);

        // 🔒 قفل دوره — سند جدید در بازهٔ بسته رد می‌شود
        try {
            $accounting->issue('manual', null, $year . '-06-15', 'سند در دورهٔ بسته', [
                ['account_code' => '1101', 'debit' => 100],
                ['account_code' => '4101', 'credit' => 100],
            ], $user->id);
            self::fail('سند در دورهٔ بسته باید رد شود.');
        } catch (\DomainException $e) {
            self::assertMatchesRegex('/بسته است/', $e->getMessage());
        }
    }

    // ─── E2E-06: تأیید سلسله‌مراتبی خرید (workflow + guardها) ─────────────

    public function testE2E06_HierarchicalApprovalWorkflow(): void
    {
        $officer = $this->user('officer@erp.test', 'کارشناس خرید', 'procurement-officer');
        $finance = $this->user('finance@erp.test', 'مدیر مالی', 'finance-manager');
        $ceo = $this->user('ceo@erp.test', 'مدیرعامل', 'erp-admin');

        [$procurement, $inventory] = $this->services();
        $supplier = $procurement->createSupplier('SUP-W', 'تأمین‌کنندهٔ workflow');
        $item = $inventory->createItem('SKU-W', 'کالای workflow');

        // خرید ۲۵۰M → فقط مدیر مالی
        $this->actingAs($officer);
        $mid = $procurement->createPurchaseOrder($supplier->id(), [
            ['item_id' => $item->id(), 'qty' => 1.0, 'unit_price' => 250_000_000],
        ], $officer->id);
        $mid = $procurement->submitForApproval($mid->id(), $officer->id);
        self::assertSame('pending_finance', $mid->status()->value);
        self::assertNotNull($mid->workflowInstanceId());

        // نمونهٔ workflow واقعی ساخته شده (AC — موتور workflow)
        self::assertTrue(DB::table('tvn_process_instances')->where('id', $mid->workflowInstanceId())->exists());

        // کارشناس خرید نمی‌تواند تأیید مالی کند (HTTP 403)
        $this->actingAs($officer);
        $this->post('/admin/erp/purchases/' . $mid->id() . '/approve-finance')->assertStatus(403);

        // مدیر مالی تأیید می‌کند → approved (زیر آستانهٔ CEO)
        $this->actingAs($finance);
        $this->post('/admin/erp/purchases/' . $mid->id() . '/approve-finance')->assertRedirect();
        $mid = $procurement->findPurchaseOrder($mid->id());
        self::assertSame('approved', $mid->status()->value);
        self::assertSame($finance->id, $mid->financeApprovedBy());
        self::assertNull($mid->ceoApprovedBy());

        // خرید ۹۰۰M → مالی سپس مدیرعامل
        $this->actingAs($officer);
        $big = $procurement->createPurchaseOrder($supplier->id(), [
            ['item_id' => $item->id(), 'qty' => 1.0, 'unit_price' => 900_000_000],
        ], $officer->id);
        $big = $procurement->submitForApproval($big->id(), $officer->id);
        self::assertSame('pending_finance', $big->status()->value);

        $this->actingAs($finance);
        $procurement->approveByFinance($big->id(), $finance->id);
        $big = $procurement->findPurchaseOrder($big->id());
        self::assertSame('pending_ceo', $big->status()->value);

        // مدیر مالی اجازهٔ تأیید CEO ندارد (403)
        $this->actingAs($finance);
        $this->post('/admin/erp/purchases/' . $big->id() . '/approve-ceo')->assertStatus(403);

        $this->actingAs($ceo);
        $this->post('/admin/erp/purchases/' . $big->id() . '/approve-ceo')->assertRedirect();
        $big = $procurement->findPurchaseOrder($big->id());
        self::assertSame('approved', $big->status()->value);
        self::assertSame($ceo->id, $big->ceoApprovedBy());

        // مسیر رد
        $rejected = $procurement->createPurchaseOrder($supplier->id(), [
            ['item_id' => $item->id(), 'qty' => 1.0, 'unit_price' => 150_000_000],
        ], $officer->id);
        $rejected = $procurement->submitForApproval($rejected->id(), $officer->id);
        $this->actingAs($finance);
        $procurement->reject($rejected->id(), $finance->id, 'بودجهٔ فصل تمام شده');
        $rejected = $procurement->findPurchaseOrder($rejected->id());
        self::assertSame('rejected', $rejected->status()->value);

        // رسید فقط برای تأییدشده
        $this->actingAs($officer);
        $this->expectException(\DomainException::class);
        $procurement->registerReceipt($rejected->id(), 'WH-?', [['item_id' => $item->id(), 'qty' => 1.0]]);
    }

    // ─── E2E-07: Audit trail کامل (AC-ERP-10) ─────────────────────────────

    public function testE2E07_AuditTrailWithHashChain(): void
    {
        $user = $this->user('acc@erp.test', 'حسابدار', 'accountant');
        $this->actingAs($user);

        $accounting = $this->app()->make(AccountingService::class);

        // سند دستی → تأیید → ثبت
        $voucher = $accounting->issue('manual', null, Clock::utcNow()->format('Y-m-d'), 'سند آزمایشی', [
            ['account_code' => '1101', 'debit' => 1_000_000, 'description' => 'واریز'],
            ['account_code' => '3101', 'credit' => 1_000_000, 'description' => 'سرمایه'],
        ], $user->id);
        $accounting->approve($voucher->id(), $user->id);
        $accounting->post($voucher->id(), $user->id);

        // ردپا: created/approved/posted در tvn_audit_log
        $created = DB::table('tvn_audit_logs')->where('event', 'voucher.created')->count();
        $posted = DB::table('tvn_audit_logs')->where('event', 'voucher.posted')->count();
        self::assertGreaterThanOrEqual(1, $created);
        self::assertGreaterThanOrEqual(1, $posted);

        // زنجیرهٔ hash سالم
        $verify = DB::table('tvn_audit_logs')->count();
        self::assertGreaterThan(2, $verify);
        $audit = $this->app()->make('audit');
        $result = $audit->verify();
        self::assertTrue($result->intact);

        // ابطال با سند معکوس → ردپای voided
        $accounting->voidWithReversal($voucher->id(), $user->id);
        self::assertGreaterThanOrEqual(1, DB::table('tvn_audit_logs')->where('event', 'voucher.voided')->count());

        // 🔒 دستکاری ردیف → زنجیره آسیب‌دیده (tamper-evident)
        $row = DB::table('tvn_audit_logs')->where('event', 'voucher.created')->first();
        DB::table('tvn_audit_logs')->where('id', $row['id'])->update([
            'new_values' => '{"tampered":true}',
        ]);
        $tampered = $audit->verify();
        self::assertFalse($tampered->intact);
    }

    // ─── E2E-08: قانون طلایی در همهٔ اسناد (AC-ERP-09) ────────────────────

    public function testE2E08_GoldenRuleAcrossAllVouchers(): void
    {
        $user = $this->user('admin@erp.test', 'مدیر ERP', 'erp-admin');
        $this->actingAs($user);

        // جریان‌های مختلط از هر بافته
        $this->runMixedActivity($user);

        // همهٔ اسناد DB — بدون استثنا تراز
        $rows = DB::table('erp_vouchers')->get();
        self::assertGreaterThan(3, \count($rows));
        foreach ($rows as $row) {
            self::assertSame(
                (int) $row['total_debit'],
                (int) $row['total_credit'],
                'سند ناتراز: ' . $row['voucher_number'],
            );
            self::assertGreaterThan(0, (int) $row['total_debit']);
        }

        // ردیف‌ها هم تراز هستند (مبلغ ردیف‌ها = جمع سند)
        foreach ($rows as $row) {
            $sum = DB::table('erp_voucher_lines')->where('voucher_id', $row['id'])->sum('debit');
            self::assertSame((int) $row['total_debit'], (int) $sum);
        }

        // سند ناتراز دستی → رد (سطح سرویس)
        $accounting = $this->app()->make(AccountingService::class);
        try {
            $accounting->issue('manual', null, Clock::utcNow()->format('Y-m-d'), 'ناتراز', [
                ['account_code' => '1101', 'debit' => 500],
                ['account_code' => '4101', 'credit' => 400],
            ], $user->id);
            self::fail('سند ناتراز نباید ذخیره شود.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('AC-ERP-09', $e->getMessage());
        }

        // تراز آزمایشی کل — جمع بدهکار = جمع بستانکار
        $reports = $this->app()->make(ErpReportService::class);
        $trial = $reports->trialBalance();
        self::assertTrue($trial['balanced']);
    }

    // ─── Outbox — AC-ERP-11 (بدون نشت) ─────────────────────────────────────

    public function testOutboxEventsPersistedAndRelayed(): void
    {
        $user = $this->user('ops@erp.test', 'انباردار', 'warehouse-keeper');
        [, $inventory] = $this->services();

        $warehouse = $inventory->createWarehouse('WH-O', 'انبار outbox');
        $item = $inventory->createItem('SKU-O', 'کالای outbox', initialAvgCost: 1_000);

        $inventory->receive($warehouse->id(), [['item_id' => $item->id(), 'qty' => 3.0, 'unit_cost' => 1_000]], 'opening', null, $user->id);

        // رویداد در tvn_outbox ثبت و رله شده (done)
        $row = DB::table('tvn_outbox')->where('event_name', 'inventory.stock.received')->first();
        self::assertNotNull($row);
        self::assertSame('done', (string) $row['status']);
        self::assertSame('pending', 'pending');   // هیچ pending باقی نمانده
        self::assertSame(0, (int) DB::table('tvn_outbox')->where('status', 'pending')->count());

        // مصرف‌کننده سند صادر کرده
        self::assertSame(1, $this->countVouchers('posted'));
    }

    // ─── HTTP — پنل و مجوزها ───────────────────────────────────────────────

    public function testAdminPagesRenderAndGuardPermissions(): void
    {
        $admin = $this->user('panel@erp.test', 'مدیر ERP پنل', 'erp-admin');
        $this->actingAs($admin);

        // صفحات اصلی ۲۰۰
        foreach ([
            '/admin/erp',
            '/admin/erp/accounts',
            '/admin/erp/vouchers',
            '/admin/erp/periods',
            '/admin/erp/inventory',
            '/admin/erp/inventory/movements',
            '/admin/erp/purchases',
            '/admin/erp/sales',
            '/admin/erp/production',
            '/admin/erp/hr',
            '/admin/erp/reports',
            '/admin/erp/reports/trial-balance',
            '/admin/erp/reports/balance-sheet',
            '/admin/erp/audit',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        // کاربر بدون مجوز → 403
        $stranger = $this->user('nobody@erp.test', 'بی‌مجوز', 'sales-officer');
        $this->actingAs($stranger);
        $this->get('/admin/erp/reports')->assertStatus(403);
        $this->get('/admin/erp/audit')->assertStatus(403);

        // guest → redirect به ورود
        $this->logoutActor();
        $this->get('/admin/erp/vouchers')->assertRedirect();
    }

    public function testVoucherCreateViaHttpWithUnbalancedLinesRejected(): void
    {
        $user = $this->user('acc2@erp.test', 'حسابدار ۲', 'accountant');
        $this->actingAs($user);

        $before = (int) DB::table('erp_vouchers')->count();

        // ردیف‌های ناتراز → flash خطا، بدون سند جدید
        $this->post('/admin/erp/vouchers', [
            'date' => Clock::utcNow()->format('Y-m-d'),
            'description' => 'تلاش ناتراز',
            'account_code' => ['1101', '4101'],
            'debit' => [1000, 0],
            'credit' => [0, 500],
        ])->assertRedirect();

        self::assertSame($before, (int) DB::table('erp_vouchers')->count());

        // ردیف‌های تراز → سند draft ایجاد می‌شود
        $this->post('/admin/erp/vouchers', [
            'date' => Clock::utcNow()->format('Y-m-d'),
            'description' => 'واریز سرمایه',
            'account_code' => ['1102', '3101'],
            'debit' => [25_000_000, 0],
            'credit' => [0, 25_000_000],
        ])->assertRedirect();

        self::assertSame($before + 1, (int) DB::table('erp_vouchers')->count());
        $voucher = DB::table('erp_vouchers')->orderByDesc('created_at')->first();
        self::assertSame('draft', (string) $voucher['status']);
        self::assertSame(25_000_000, (int) $voucher['total_debit']);
    }

    public function testPostedVoucherImmutableAtServiceLevel(): void
    {
        $user = $this->user('acc3@erp.test', 'حسابدار ۳', 'accountant');
        $accounting = $this->app()->make(AccountingService::class);

        $voucher = $accounting->issue('manual', null, Clock::utcNow()->format('Y-m-d'), 'سند قطعی', [
            ['account_code' => '5301', 'debit' => 900],
            ['account_code' => '1101', 'credit' => 900],
        ], $user->id, autoPost: true);

        $reloaded = $accounting->findVoucher($voucher->id());
        self::assertSame('posted', $reloaded->status()->value);

        // post مجدد → رد (ماشین‌حالت)
        try {
            $accounting->post($voucher->id(), $user->id);
            self::fail('سند posted دوباره post نمی‌شود.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('نامجاز', $e->getMessage());
        }
    }

    public function testCsvReportsDownload(): void
    {
        $user = $this->user('rep@erp.test', 'گزارش‌گیر', 'finance-manager');
        $this->actingAs($user);

        $response = $this->get('/admin/erp/reports/trial-balance.csv');
        $response->assertOk();
        self::assertStringContainsString('text/csv', (string) $response->header('Content-Type'));
        self::assertStringContainsString('code,title,type', $response->content());

        $this->get('/admin/erp/reports/balance-sheet.csv')->assertOk();
    }

    // ─── نشست واقعی HTTP — رگرسیون فیکس SessionGuard ─────────────────────

    /**
     * احراز هویت نشست‌محور روی kernel واقعی با کوکی نشست — بدون actingAs.
     *
     * رگرسیون باگ موتور auth: StartSessionMiddleware نشست را به clone
     * request می‌بندد ولی RequestStack درخواست اصلی را نگه می‌دارد →
     * SessionGuard نشست را نمی‌دید (fallback به instance نشستِ container).
     */
    public function testSessionCookieAuthenticatesThroughRealKernel(): void
    {
        $user = $this->user('session@erp.test', 'کاربر نشست', 'erp-admin');

        // درایور نشست برای این تست file (مانند production) — setUp آن را array گذاشته
        $this->app()->make('config')->set('session.driver', 'file');

        $loginKey = 'login_web_' . \substr(\hash('sha256', \Tavaan\Engine\Auth\Guards\SessionGuard::class), 0, 16);
        $sessionId = \bin2hex(\random_bytes(20));
        $sessionFile = \dirname(__DIR__, 4) . '/storage/framework/sessions/sess_' . $sessionId;

        // مسیر/قالب فایل نشست — همسان با FileHandler موتور session
        $dir = \dirname($sessionFile);
        if (!\is_dir($dir)) {
            self::markTestSkipped('storage/framework/sessions در دسترس نیست.');
        }

        \file_put_contents($sessionFile, (string) \json_encode([
            $loginKey => $user->id,
            '_token' => \bin2hex(\random_bytes(16)),
        ]));

        try {
            $response = $this->call('GET', '/admin/erp/vouchers', [], ['Cookie' => 'tavaan_session=' . $sessionId]);
            self::assertSame(200, $response->status(), 'نشست واقعی باید به پنل برسد (نه 302)');
        } finally {
            @\unlink($sessionFile);
        }
    }

    // ─── کمکی‌ها ───────────────────────────────────────────────────────────

    /** @return array{0: ProcurementService, 1: InventoryService, 2: AccountingService, 3: SalesService, 4: ProductionService, 5: HrService} */
    private function services(): array
    {
        $app = $this->app();

        return [
            $app->make(ProcurementService::class),
            $app->make(InventoryService::class),
            $app->make(AccountingService::class),
            $app->make(SalesService::class),
            $app->make(ProductionService::class),
            $app->make(HrService::class),
        ];
    }

    /** جریان مختلط همهٔ بافته‌ها (برای E2E-08). */
    private function runMixedActivity(User $user): void
    {
        [$procurement, $inventory, , $sales, $production, $hr] = $this->services();

        $supplier = $procurement->createSupplier('SUP-M', 'تأمین‌کنندهٔ مختلط');
        $customer = $sales->createCustomer('CUS-M', 'مشتری مختلط');
        $warehouse = $inventory->createWarehouse('WH-M', 'انبار مختلط');

        $trade = $inventory->createItem('SKU-M1', 'کالای تجاری', initialAvgCost: 0);
        $raw = $inventory->createItem('SKU-M2', 'مادهٔ اولیه', kind: 'raw_material', initialAvgCost: 300_000);
        $product = $inventory->createItem('SKU-M3', 'محصول', kind: 'finished');

        // خرید → رسید
        $po = $procurement->createPurchaseOrder($supplier->id(), [
            ['item_id' => $trade->id(), 'qty' => 6.0, 'unit_price' => 1_000_000],
        ], $user->id);
        $procurement->submitForApproval($po->id(), $user->id);
        $procurement->registerReceipt($po->id(), $warehouse->id(), [
            ['item_id' => $trade->id(), 'qty' => 6.0],
        ], $user->id);

        // مواد اولیه موجود
        $inventory->receive($warehouse->id(), [
            ['item_id' => $raw->id(), 'qty' => 12.0, 'unit_cost' => 300_000],
        ], 'opening', null, $user->id);

        // فروش
        $so = $sales->createOrder($customer->id(), $warehouse->id(), [
            ['item_id' => $trade->id(), 'qty' => 3.0, 'unit_price' => 1_500_000],
        ], $user->id);
        $sales->confirmOrder($so->id());
        $sales->deliver($so->id(), $user->id);
        $invoice = $sales->issueInvoice($so->id(), $user->id);
        $sales->receivePayment($invoice->id(), 'bank', null, $user->id);

        // تولید
        $production->createBom($product->id(), [['item_id' => $raw->id(), 'qty' => 2.0]]);
        $wo = $production->planWorkOrder($product->id(), 4.0, $warehouse->id(), $user->id);
        $production->startWorkOrder($wo->id(), $user->id);
        $production->completeWorkOrder($wo->id(), 4.0, $user->id);

        // حقوق
        $employee = $hr->createEmployee('EMP-M', 'پرسنل مختلط', 100_000_000);
        $month = Clock::utcNow()->format('Y-m');
        $hr->recordAttendance($employee->id(), $month, 176.0, 4.0);
        $run = $hr->runPayroll($month, $user->id);
        $hr->payPayroll($run->id(), 'bank', $user->id);

        // اصلاح موجودی
        $inventory->adjust($warehouse->id(), $trade->id(), -1.0, 'شکستگی', $user->id);
    }

    private function voucherForSource(string $module, ?string $sourceId = null): ?object
    {
        $repository = $this->app()->make(AccountingRepositoryInterface::class);

        if ($sourceId !== null) {
            return $repository->findVoucherBySource($module, $sourceId);
        }

        $vouchers = $repository->vouchers(['source_module' => $module, 'sort' => 'latest'], 1);

        return $vouchers[0] ?? null;
    }

    private function countVouchers(string $status): int
    {
        return $this->app()->make(AccountingRepositoryInterface::class)->countVouchers(['status' => $status]);
    }

    private function assertAllVouchersBalanced(): void
    {
        $rows = DB::table('erp_vouchers')->get();
        foreach ($rows as $row) {
            self::assertSame((int) $row['total_debit'], (int) $row['total_credit'], 'سند ناتراز: ' . $row['voucher_number']);
        }
    }

    private function seedChart(): void
    {
        // chart of accounts + دورهٔ باز جاری (مانند seed دمو — idempotent در هر تست fresh)
        $now = Clock::utcNow();
        $fmt = 'Y-m-d H:i:s.v';

        $accounts = [
            ['1101', 'صندوق', 'asset', 'debit'],
            ['1102', 'بانک', 'asset', 'debit'],
            ['1201', 'حساب‌های دریافتنی تجاری', 'asset', 'debit'],
            ['1301', 'کالای تجاری', 'asset', 'debit'],
            ['1302', 'مواد اولیه', 'asset', 'debit'],
            ['1303', 'کالای در جریان ساخت', 'asset', 'debit'],
            ['1304', 'محصول ساخته‌شده', 'asset', 'debit'],
            ['2101', 'حساب‌های پرداختنی تجاری', 'liability', 'credit'],
            ['2201', 'حقوق پرداختنی', 'liability', 'credit'],
            ['3101', 'سرمایهٔ ثبت‌شده', 'equity', 'credit'],
            ['3201', 'خلاصهٔ سود و زیان', 'equity', 'credit'],
            ['3301', 'سود انباشته', 'equity', 'credit'],
            ['4101', 'درآمد فروش کالا', 'revenue', 'credit'],
            ['5101', 'بهای کالای فروش‌رفته', 'expense', 'debit'],
            ['5201', 'هزینهٔ حقوق', 'expense', 'debit'],
            ['5301', 'سایر هزینه‌ها', 'expense', 'debit'],
        ];

        foreach ($accounts as [$code, $title, $type, $normal]) {
            DB::table('erp_accounts')->insert([
                'code' => $code,
                'title' => $title,
                'parent_id' => null,
                'type' => $type,
                'level' => 3,
                'is_leaf' => true,
                'normal_balance' => $normal,
                'created_at' => $now->format($fmt),
                'updated_at' => null,
            ]);
        }
    }

    private function seedRbac(): void
    {
        $permissions = [
            'erp.account.view', 'erp.account.manage',
            'erp.voucher.view', 'erp.voucher.create', 'erp.voucher.approve', 'erp.voucher.post', 'erp.voucher.void',
            'erp.period.manage', 'erp.report.view',
            'erp.warehouse.manage', 'erp.stock.receive', 'erp.stock.issue', 'erp.stock.adjust',
            'erp.supplier.manage', 'erp.customer.manage',
            'erp.purchase.create', 'erp.purchase.approve-finance', 'erp.purchase.approve-ceo',
            'erp.purchase.receive', 'erp.purchase.invoice', 'erp.payment.create',
            'erp.sales.create', 'erp.sales.deliver', 'erp.sales.invoice',
            'erp.production.manage',
            'erp.hr.manage', 'erp.payroll.process',
            'erp.audit.view',
        ];
        foreach ($permissions as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.erp');
            }
        }

        $rolePerms = [
            'accountant' => ['erp.account.view', 'erp.voucher.view', 'erp.voucher.create'],
            'finance-manager' => ['erp.account.view', 'erp.voucher.view', 'erp.voucher.approve', 'erp.voucher.post', 'erp.period.manage', 'erp.report.view', 'erp.purchase.approve-finance'],
            'warehouse-keeper' => ['erp.stock.receive', 'erp.stock.issue', 'erp.stock.adjust', 'erp.warehouse.manage'],
            'procurement-officer' => ['erp.purchase.create', 'erp.purchase.receive', 'erp.purchase.invoice', 'erp.supplier.manage', 'erp.payment.create'],
            'sales-officer' => ['erp.sales.create', 'erp.sales.deliver', 'erp.sales.invoice', 'erp.customer.manage', 'erp.payment.create'],
            'hr-officer' => ['erp.hr.manage', 'erp.payroll.process'],
            'production-planner' => ['erp.production.manage', 'erp.stock.receive', 'erp.stock.issue'],
            'auditor' => ['erp.voucher.view', 'erp.report.view', 'erp.audit.view', 'erp.account.view'],
            'erp-admin' => $permissions,
            'admin' => $permissions,
        ];

        foreach ($rolePerms as $roleKey => $perms) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
            foreach ($perms as $perm) {
                $this->roles->grantPermissionToRole($roleKey, $perm);
            }
        }
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    /** حذف بازیگر جاری (برای درخواست‌های مهمان — الگوی CRM). */
    private function logoutActor(): void
    {
        $this->app()->make(\Tavaan\Engine\Auth\AuthManager::class)->guard('web')->logout();
    }
}
