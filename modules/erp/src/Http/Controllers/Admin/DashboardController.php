<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DashboardController — داشبورد سازمانی (/admin/erp).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\AccountingService;
use Modules\Erp\Application\UseCases\ErpAuditRecorder;
use Modules\Erp\Application\UseCases\ErpReportService;
use Modules\Erp\Application\UseCases\InventoryService;
use Modules\Erp\Application\UseCases\ProcurementService;
use Modules\Erp\Application\UseCases\SalesService;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class DashboardController
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly ProcurementService $procurement,
        private readonly SalesService $sales,
        private readonly ErpReportService $reports,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    public function index(): mixed
    {
        if (!Gate::allows('erp.voucher.view') && !Gate::allows('erp.report.view')) {
            return redirect('/admin');
        }

        $balanceSheet = $this->reports->balanceSheet();
        $lowStock = $this->inventory->lowStock();

        return View::render('erp::admin.dashboard', [
            'draftVouchers' => $this->accounting->countVouchers(['status' => 'draft']),
            'postedVouchers' => $this->accounting->countVouchers(['status' => 'posted']),
            'pendingPos' => $this->procurement->countPurchaseOrders(['status' => 'pending_finance'])
                + $this->procurement->countPurchaseOrders(['status' => 'pending_ceo']),
            'openSalesOrders' => $this->sales->countOrders(['status' => 'confirmed']),
            'lowStockCount' => \count($lowStock),
            'assets' => $balanceSheet['assets'],
            'liabilities' => $balanceSheet['liabilities'],
            'equity' => $balanceSheet['equity'] + $balanceSheet['net_income'],
            'balanced' => $balanceSheet['balanced'],
            'auditHealthy' => $this->audit->chainHealthy(),
        ]);
    }
}
