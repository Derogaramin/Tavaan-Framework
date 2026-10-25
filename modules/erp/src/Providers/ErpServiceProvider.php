<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ErpServiceProvider — bindingها + routes/lang + listenerهای Outbox.
 */

namespace Modules\Erp\Providers;

use Modules\Erp\Application\UseCases\AccountingService;
use Modules\Erp\Application\UseCases\ErpAuditRecorder;
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
use Modules\Erp\Domain\Repositories\ProductionRepositoryInterface;
use Modules\Erp\Domain\Repositories\SalesRepositoryInterface;
use Modules\Erp\Domain\Services\ApprovalRouter;
use Modules\Erp\Domain\Services\BomCosting;
use Modules\Erp\Domain\Services\PayrollCalculator;
use Modules\Erp\Domain\Services\TrialBalanceCalculator;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Modules\Erp\Infrastructure\Listeners\PostPaymentReceivedVoucherListener;
use Modules\Erp\Infrastructure\Listeners\PostPayrollProcessedVoucherListener;
use Modules\Erp\Infrastructure\Listeners\PostSalesInvoiceVoucherListener;
use Modules\Erp\Infrastructure\Listeners\PostStockIssuedVoucherListener;
use Modules\Erp\Infrastructure\Listeners\PostStockReceivedVoucherListener;
use Modules\Erp\Infrastructure\Listeners\PostWorkOrderCompletedVoucherListener;
use Modules\Erp\Infrastructure\Persistence\DatabaseAccountingRepository;
use Modules\Erp\Infrastructure\Persistence\DatabaseHrRepository;
use Modules\Erp\Infrastructure\Persistence\DatabaseInventoryRepository;
use Modules\Erp\Infrastructure\Persistence\DatabaseProcurementRepository;
use Modules\Erp\Infrastructure\Persistence\DatabaseProductionRepository;
use Modules\Erp\Infrastructure\Persistence\DatabaseSalesRepository;
use Modules\Erp\Infrastructure\Workflow\PurchaseApprovalWorkflow;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول ERP
 *
 * 🔒 ARCH-003: فقط Core + Support + AuditEvent (allow صریح SDK — DOC-097 §7).
 *
 * یکپارچگی بین‌بافته‌ها (AC-ERP-11): سرویس‌ها رویداد را درون تراکنش در
 * tvn_outbox می‌گذارند؛ listenerهای حسابداری (پایین) پس از رله اسناد دفتر کل
 * را صادر می‌کنند — آمادهٔ تجزیه به سرویس مستقل (AC-ERP-12).
 *
 * @api 1.0
 * @see modules/erp/README.md
 */
final class ErpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = fn (): array => $this->app->bound('config')
            ? (array) $this->app->make('config')->get('erp', [])
            : [];

        $this->extendAuditEventAllowlist();

        // ── Infrastructure: مخزن‌ها (هر بافته یک پورت) ────────────────────
        $this->app->singleton(AccountingRepositoryInterface::class, static fn (): AccountingRepositoryInterface => new DatabaseAccountingRepository());
        $this->app->singleton(InventoryRepositoryInterface::class, static fn (): InventoryRepositoryInterface => new DatabaseInventoryRepository());
        $this->app->singleton(ProcurementRepositoryInterface::class, static fn (): ProcurementRepositoryInterface => new DatabaseProcurementRepository());
        $this->app->singleton(SalesRepositoryInterface::class, static fn (): SalesRepositoryInterface => new DatabaseSalesRepository());
        $this->app->singleton(ProductionRepositoryInterface::class, static fn (): ProductionRepositoryInterface => new DatabaseProductionRepository());
        $this->app->singleton(HrRepositoryInterface::class, static fn (): HrRepositoryInterface => new DatabaseHrRepository());

        // ── Integration: Outbox (ADR-0009) ────────────────────────────────
        $this->app->singleton(OutboxPublisher::class, fn (ContainerInterface $c): OutboxPublisher => new OutboxPublisher(
            $c->make(ConnectionInterface::class),
        ));

        // ── Domain Services (خالص) ────────────────────────────────────────
        $this->app->singleton(TrialBalanceCalculator::class, static fn (): TrialBalanceCalculator => new TrialBalanceCalculator());
        $this->app->singleton(BomCosting::class, static fn (): BomCosting => new BomCosting());
        $this->app->singleton(ApprovalRouter::class, static fn (): ApprovalRouter => new ApprovalRouter(
            (int) ($config()['procurement']['finance_threshold'] ?? 100_000_000),
            (int) ($config()['procurement']['ceo_threshold'] ?? 500_000_000),
        ));
        $this->app->singleton(PayrollCalculator::class, static fn (): PayrollCalculator => new PayrollCalculator(
            (float) ($config()['payroll']['overtime_rate'] ?? 1.4),
            (int) ($config()['payroll']['monthly_hours'] ?? 220),
            (float) ($config()['payroll']['tax_rate'] ?? 0.0),
            (int) ($config()['payroll']['tax_threshold'] ?? 0),
        ));

        // ── Workflow تأیید خرید ───────────────────────────────────────────
        $this->app->singleton(PurchaseApprovalWorkflow::class, static fn (): PurchaseApprovalWorkflow => new PurchaseApprovalWorkflow(
            (string) ($config()['procurement']['workflow'] ?? 'erp-purchase-approval'),
        ));

        // ── Application: UseCaseها ────────────────────────────────────────
        $this->app->singleton(ErpAuditRecorder::class, fn (ContainerInterface $c): ErpAuditRecorder => new ErpAuditRecorder($c));

        $this->app->singleton(AccountingService::class, fn (ContainerInterface $c): AccountingService => new AccountingService(
            $c->make(AccountingRepositoryInterface::class),
            $c->make(ErpAuditRecorder::class),
        ));

        $this->app->singleton(InventoryService::class, fn (ContainerInterface $c): InventoryService => new InventoryService(
            $c->make(InventoryRepositoryInterface::class),
            $c->make(OutboxPublisher::class),
        ));

        $this->app->singleton(ProcurementService::class, fn (ContainerInterface $c): ProcurementService => new ProcurementService(
            $c->make(ProcurementRepositoryInterface::class),
            $c->make(InventoryRepositoryInterface::class),
            $c->make(InventoryService::class),
            $c->make(AccountingService::class),
            $c->make(ApprovalRouter::class),
            $c->make(PurchaseApprovalWorkflow::class),
            $c->make(OutboxPublisher::class),
            $c->make(ErpAuditRecorder::class),
        ));

        $this->app->singleton(SalesService::class, fn (ContainerInterface $c): SalesService => new SalesService(
            $c->make(SalesRepositoryInterface::class),
            $c->make(InventoryRepositoryInterface::class),
            $c->make(InventoryService::class),
            $c->make(OutboxPublisher::class),
            $c->make(ErpAuditRecorder::class),
        ));

        $this->app->singleton(ProductionService::class, fn (ContainerInterface $c): ProductionService => new ProductionService(
            $c->make(ProductionRepositoryInterface::class),
            $c->make(InventoryRepositoryInterface::class),
            $c->make(InventoryService::class),
            $c->make(BomCosting::class),
            $c->make(OutboxPublisher::class),
            $c->make(ErpAuditRecorder::class),
        ));

        $this->app->singleton(HrService::class, fn (ContainerInterface $c): HrService => new HrService(
            $c->make(HrRepositoryInterface::class),
            $c->make(PayrollCalculator::class),
            $c->make(AccountingService::class),
            $c->make(OutboxPublisher::class),
            $c->make(ErpAuditRecorder::class),
        ));

        $this->app->singleton(PeriodCloseService::class, fn (ContainerInterface $c): PeriodCloseService => new PeriodCloseService(
            $c->make(AccountingRepositoryInterface::class),
            $c->make(AccountingService::class),
            $c->make(TrialBalanceCalculator::class),
            $c->make(OutboxPublisher::class),
            $c->make(ErpAuditRecorder::class),
        ));

        $this->app->singleton(ErpReportService::class, fn (ContainerInterface $c): ErpReportService => new ErpReportService(
            $c->make(AccountingRepositoryInterface::class),
            $c->make(InventoryRepositoryInterface::class),
            $c->make(ProcurementRepositoryInterface::class),
            $c->make(SalesRepositoryInterface::class),
            $c->make(ProductionRepositoryInterface::class),
            $c->make(HrRepositoryInterface::class),
            $c->make(TrialBalanceCalculator::class),
        ));
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
    }

    /**
     * ادغام نام رویدادهای مالی ERP در allowlist موتور audit (DOC-097 §7).
     *
     * موتور audit فقط رویدادهای `config('audit.events')` را ثبت می‌کند —
     * ماژول نام‌های خود را idempotent اضافه می‌کند (بدون تغییر رفتار سایر ماژول‌ها).
     */
    private function extendAuditEventAllowlist(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        $config = $this->app->make('config');
        $existing = (array) $config->get('audit.events', []);

        $erpEvents = [
            'voucher.created', 'voucher.approved', 'voucher.posted', 'voucher.voided',
            'purchase_order.created', 'purchase_order.submitted', 'purchase_order.finance_approved',
            'purchase_order.ceo_approved', 'purchase_order.rejected',
            'goods_receipt.registered', 'supplier_invoice.recorded',
            'payment.out', 'payment.in',
            'sales_order.delivered', 'sales_invoice.issued',
            'work_order.started', 'work_order.completed',
            'employee.created', 'employee.terminated',
            'payroll.processed', 'payroll.paid',
            'period.closed',
        ];

        $merged = \array_values(\array_unique(\array_merge($existing, $erpEvents)));
        $config->set('audit.events', $merged);
    }

    private function registerLang(string $moduleRoot): void
    {
        if (!$this->app->bound('lang')) {
            return;
        }
        $translator = $this->app->make('lang');
        if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
            $translator->addNamespace('erp', $moduleRoot . '/lang');
        }
    }

    private function registerRoutes(string $moduleRoot): void
    {
        foreach (['web', 'admin'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }
    }

    /**
     * listenerهای حسابداری — مصرف‌کننده‌های رویدادهای Outbox (DOC-097 §9).
     *
     * دفتر کل مرکزی: هر رویداد عملیاتی → سند دوطرفهٔ تراز (AC-ERP-09).
     */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        Event::listen('inventory.stock.received', PostStockReceivedVoucherListener::class);
        Event::listen('inventory.stock.issued', PostStockIssuedVoucherListener::class);
        Event::listen('sales.invoice.issued', PostSalesInvoiceVoucherListener::class);
        Event::listen('sales.payment.received', PostPaymentReceivedVoucherListener::class);
        Event::listen('production.order.completed', PostWorkOrderCompletedVoucherListener::class);
        Event::listen('hr.payroll.processed', PostPayrollProcessedVoucherListener::class);
    }
}
