<?php

declare(strict_types=1);

/*
 * ماژول ERP — مسیرهای پنل مدیریت (DOC-097 §6/§7).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate) در کنترلرها
 * بررسی می‌شوند (الگوی crm/lms).
 */

use Modules\Erp\Http\Controllers\Admin\AccountingController;
use Modules\Erp\Http\Controllers\Admin\AuditController;
use Modules\Erp\Http\Controllers\Admin\DashboardController;
use Modules\Erp\Http\Controllers\Admin\HrController;
use Modules\Erp\Http\Controllers\Admin\InventoryController;
use Modules\Erp\Http\Controllers\Admin\ProcurementController;
use Modules\Erp\Http\Controllers\Admin\ProductionController;
use Modules\Erp\Http\Controllers\Admin\ReportController;
use Modules\Erp\Http\Controllers\Admin\SalesController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/erp', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // داشبورد
    Route::get('', [DashboardController::class, 'index'])->name('admin.erp.dashboard');
    Route::get('/', [DashboardController::class, 'index'])->name('admin.erp.dashboard.slash');

    // حسابداری — سرفصل حساب‌ها
    Route::get('/accounts', [AccountingController::class, 'accounts'])->name('admin.erp.accounts');
    Route::post('/accounts', [AccountingController::class, 'storeAccount'])->name('admin.erp.accounts.store');

    // حسابداری — اسناد
    Route::get('/vouchers', [AccountingController::class, 'vouchers'])->name('admin.erp.vouchers');
    Route::get('/vouchers/create', [AccountingController::class, 'createVoucher'])->name('admin.erp.vouchers.create');
    Route::post('/vouchers', [AccountingController::class, 'storeVoucher'])->name('admin.erp.vouchers.store');
    Route::get('/vouchers/{id}', [AccountingController::class, 'showVoucher'])->name('admin.erp.vouchers.show');
    Route::post('/vouchers/{id}/approve', [AccountingController::class, 'approveVoucher'])->name('admin.erp.vouchers.approve');
    Route::post('/vouchers/{id}/post', [AccountingController::class, 'postVoucher'])->name('admin.erp.vouchers.post');
    Route::post('/vouchers/{id}/void', [AccountingController::class, 'voidVoucher'])->name('admin.erp.vouchers.void');

    // حسابداری — دوره‌های مالی
    Route::get('/periods', [AccountingController::class, 'periods'])->name('admin.erp.periods');
    Route::post('/periods', [AccountingController::class, 'storePeriod'])->name('admin.erp.periods.store');
    Route::post('/periods/{id}/close', [AccountingController::class, 'closePeriod'])->name('admin.erp.periods.close');

    // انبار
    Route::get('/inventory', [InventoryController::class, 'index'])->name('admin.erp.inventory');
    Route::post('/inventory/warehouses', [InventoryController::class, 'storeWarehouse'])->name('admin.erp.inventory.warehouses.store');
    Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('admin.erp.inventory.items.store');
    Route::post('/inventory/receive', [InventoryController::class, 'storeReceive'])->name('admin.erp.inventory.receive');
    Route::post('/inventory/issue', [InventoryController::class, 'storeIssue'])->name('admin.erp.inventory.issue');
    Route::post('/inventory/transfer', [InventoryController::class, 'storeTransfer'])->name('admin.erp.inventory.transfer');
    Route::post('/inventory/adjust', [InventoryController::class, 'storeAdjust'])->name('admin.erp.inventory.adjust');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('admin.erp.inventory.movements');

    // تدارکات
    Route::get('/purchases', [ProcurementController::class, 'index'])->name('admin.erp.purchases');
    Route::get('/purchases/create', [ProcurementController::class, 'create'])->name('admin.erp.purchases.create');
    Route::post('/purchases', [ProcurementController::class, 'store'])->name('admin.erp.purchases.store');
    Route::get('/purchases/{id}', [ProcurementController::class, 'show'])->name('admin.erp.purchases.show');
    Route::post('/purchases/{id}/submit', [ProcurementController::class, 'submit'])->name('admin.erp.purchases.submit');
    Route::post('/purchases/{id}/approve-finance', [ProcurementController::class, 'approveFinance'])->name('admin.erp.purchases.approve-finance');
    Route::post('/purchases/{id}/approve-ceo', [ProcurementController::class, 'approveCeo'])->name('admin.erp.purchases.approve-ceo');
    Route::post('/purchases/{id}/reject', [ProcurementController::class, 'reject'])->name('admin.erp.purchases.reject');
    Route::post('/purchases/{id}/receive', [ProcurementController::class, 'receive'])->name('admin.erp.purchases.receive');
    Route::post('/suppliers', [ProcurementController::class, 'storeSupplier'])->name('admin.erp.suppliers.store');
    Route::post('/supplier-invoices', [ProcurementController::class, 'storeSupplierInvoice'])->name('admin.erp.supplier-invoices.store');
    Route::post('/supplier-invoices/{id}/pay', [ProcurementController::class, 'paySupplierInvoice'])->name('admin.erp.supplier-invoices.pay');

    // فروش
    Route::get('/sales', [SalesController::class, 'index'])->name('admin.erp.sales');
    Route::get('/sales/create', [SalesController::class, 'create'])->name('admin.erp.sales.create');
    Route::post('/sales', [SalesController::class, 'store'])->name('admin.erp.sales.store');
    Route::get('/sales/{id}', [SalesController::class, 'show'])->name('admin.erp.sales.show');
    Route::post('/sales/{id}/confirm', [SalesController::class, 'confirm'])->name('admin.erp.sales.confirm');
    Route::post('/sales/{id}/deliver', [SalesController::class, 'deliver'])->name('admin.erp.sales.deliver');
    Route::post('/sales/{id}/invoice', [SalesController::class, 'invoice'])->name('admin.erp.sales.invoice');
    Route::post('/sales-invoices/{id}/receive-payment', [SalesController::class, 'receivePayment'])->name('admin.erp.sales-invoices.receive-payment');
    Route::post('/customers', [SalesController::class, 'storeCustomer'])->name('admin.erp.customers.store');

    // تولید
    Route::get('/production', [ProductionController::class, 'index'])->name('admin.erp.production');
    Route::post('/production/bom', [ProductionController::class, 'storeBom'])->name('admin.erp.production.bom.store');
    Route::post('/production/work-orders', [ProductionController::class, 'storeWorkOrder'])->name('admin.erp.production.work-orders.store');
    Route::get('/production/work-orders/{id}', [ProductionController::class, 'showWorkOrder'])->name('admin.erp.production.work-orders.show');
    Route::post('/production/work-orders/{id}/start', [ProductionController::class, 'startWorkOrder'])->name('admin.erp.production.work-orders.start');
    Route::post('/production/work-orders/{id}/complete', [ProductionController::class, 'completeWorkOrder'])->name('admin.erp.production.work-orders.complete');

    // منابع انسانی
    Route::get('/hr', [HrController::class, 'index'])->name('admin.erp.hr');
    Route::post('/hr/employees', [HrController::class, 'storeEmployee'])->name('admin.erp.hr.employees.store');
    Route::post('/hr/attendance', [HrController::class, 'storeAttendance'])->name('admin.erp.hr.attendance.store');
    Route::post('/hr/payroll', [HrController::class, 'runPayroll'])->name('admin.erp.hr.payroll.run');
    Route::post('/hr/payroll/{id}/pay', [HrController::class, 'payPayroll'])->name('admin.erp.hr.payroll.pay');

    // گزارش‌ها
    Route::get('/reports', [ReportController::class, 'index'])->name('admin.erp.reports');
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('admin.erp.reports.trial-balance');
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('admin.erp.reports.profit-loss');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('admin.erp.reports.balance-sheet');
    Route::get('/reports/turnover', [ReportController::class, 'turnover'])->name('admin.erp.reports.turnover');
    Route::get('/reports/production-cost', [ReportController::class, 'productionCost'])->name('admin.erp.reports.production-cost');
    Route::get('/reports/party', [ReportController::class, 'partyStatement'])->name('admin.erp.reports.party');
    Route::get('/reports/payroll', [ReportController::class, 'payroll'])->name('admin.erp.reports.payroll');
    Route::get('/reports/trial-balance.csv', [ReportController::class, 'csvTrialBalance'])->name('admin.erp.reports.trial-balance.csv');
    Route::get('/reports/profit-loss.csv', [ReportController::class, 'csvProfitLossAction'])->name('admin.erp.reports.profit-loss.csv');
    Route::get('/reports/balance-sheet.csv', [ReportController::class, 'csvBalanceSheetAction'])->name('admin.erp.reports.balance-sheet.csv');
    Route::get('/reports/turnover.csv', [ReportController::class, 'csvTurnoverAction'])->name('admin.erp.reports.turnover.csv');
    Route::get('/reports/payroll.csv', [ReportController::class, 'csvPayrollAction'])->name('admin.erp.reports.payroll.csv');

    // audit
    Route::get('/audit', [AuditController::class, 'index'])->name('admin.erp.audit');
});
