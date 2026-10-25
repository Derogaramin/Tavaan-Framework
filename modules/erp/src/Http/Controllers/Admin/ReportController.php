<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ReportController — گزارش‌های مالی (DOC-097 §5).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\ErpReportService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class ReportController
{
    public function __construct(private readonly ErpReportService $reports)
    {
    }

    public function index(): mixed
    {
        $this->authorize();

        return View::render('erp::admin.reports-index', []);
    }

    public function trialBalance(RequestInterface $request): mixed
    {
        $this->authorize();

        [$from, $to] = $this->rangeOf($request);
        $report = $this->reports->trialBalance($from, $to);

        return View::render('erp::admin.report-trial-balance', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function profitLoss(RequestInterface $request): mixed
    {
        $this->authorize();

        [$from, $to] = $this->rangeOf($request);
        $report = $this->reports->profitLoss($from, $to);

        return View::render('erp::admin.report-profit-loss', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function balanceSheet(RequestInterface $request): mixed
    {
        $this->authorize();

        $to = (string) $request->input('to', '');
        $report = $this->reports->balanceSheet($to !== '' ? $to : null);

        return View::render('erp::admin.report-balance-sheet', [
            'report' => $report,
            'to' => $to,
        ]);
    }

    public function turnover(RequestInterface $request): mixed
    {
        $this->authorize();

        [$from, $to] = $this->rangeOf($request);
        $warehouseId = (string) $request->input('warehouse_id', '');

        return View::render('erp::admin.report-turnover', [
            'rows' => $this->reports->inventoryTurnover(
                $from,
                $to,
                $warehouseId !== '' ? $warehouseId : null,
            ),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function productionCost(): mixed
    {
        $this->authorize();

        return View::render('erp::admin.report-production-cost', [
            'rows' => $this->reports->productionCost(),
        ]);
    }

    public function partyStatement(RequestInterface $request): mixed
    {
        $this->authorize();

        $partyType = (string) $request->input('type', 'customer');
        $partyId = (string) $request->input('id', '');

        $statement = null;
        if ($partyId !== '') {
            $statement = $partyType === 'supplier'
                ? $this->reports->supplierStatement($partyId)
                : $this->reports->customerStatement($partyId);
        }

        return View::render('erp::admin.report-party', [
            'statement' => $statement,
            'partyType' => $partyType,
            'partyId' => $partyId,
        ]);
    }

    public function payroll(RequestInterface $request): mixed
    {
        $this->authorize();

        $month = (string) $request->input('month', '');
        if ($month === '' || !\preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = \Tavaan\Support\Time\Clock::utcNow()->format('Y-m');
        }

        return View::render('erp::admin.report-payroll', [
            'rows' => $this->reports->payrollReport($month),
            'month' => $month,
        ]);
    }

    /** دانلود CSV — مسیرهای لITERAL (الگوی crm/lms). */
    public function csvTrialBalance(RequestInterface $request): mixed
    {
        [$from, $to] = $this->rangeOf($request);

        return $this->csvResponse($this->csvTrialBalanceRows($from, $to), 'trial-balance');
    }

    public function csvProfitLossAction(RequestInterface $request): mixed
    {
        [$from, $to] = $this->rangeOf($request);

        return $this->csvResponse($this->csvProfitLossRows($from, $to), 'profit-loss');
    }

    public function csvBalanceSheetAction(RequestInterface $request): mixed
    {
        $to = (string) $request->input('to', '');

        return $this->csvResponse($this->csvBalanceSheetRows($to !== '' ? $to : null), 'balance-sheet');
    }

    public function csvTurnoverAction(RequestInterface $request): mixed
    {
        [$from, $to] = $this->rangeOf($request);

        return $this->csvResponse($this->csvTurnoverRows($from, $to), 'turnover');
    }

    public function csvPayrollAction(RequestInterface $request): mixed
    {
        $month = (string) $request->input('month', \Tavaan\Support\Time\Clock::utcNow()->format('Y-m'));

        return $this->csvResponse($this->csvPayrollRows($month), 'payroll');
    }

    private function csvResponse(string $csv, string $name): mixed
    {
        $this->authorize();

        /** @var object $responses */
        $responses = \app()->make('response.factory');

        return $responses->make(
            $csv,
            200,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="erp-' . $name . '.csv"'],
        );
    }

    // ── CSV سازها ──────────────────────────────────────────────────────────

    private function csvTrialBalanceRows(?string $from, ?string $to): string
    {
        $lines = ['code,title,type,debit_minor,credit_minor,balance_minor'];
        foreach ($this->reports->trialBalance($from, $to)['rows'] as $row) {
            $lines[] = \implode(',', [
                self::csvSafe((string) $row['code']),
                self::csvSafe((string) $row['title']),
                (string) $row['type'],
                (string) (int) $row['debit'],
                (string) (int) $row['credit'],
                (string) (int) $row['balance'],
            ]);
        }

        return \implode("\n", $lines) . "\n";
    }

    private function csvProfitLossRows(?string $from, ?string $to): string
    {
        $report = $this->reports->profitLoss($from, $to);
        $lines = ['section,code,title,amount_minor'];

        foreach ($report['revenue_rows'] as $row) {
            $lines[] = \implode(',', ['revenue', self::csvSafe((string) $row['code']), self::csvSafe((string) $row['title']), (string) (int) $row['balance']]);
        }
        foreach ($report['expense_rows'] as $row) {
            $lines[] = \implode(',', ['expense', self::csvSafe((string) $row['code']), self::csvSafe((string) $row['title']), (string) (int) $row['balance']]);
        }
        $lines[] = \implode(',', ['total', '', 'net_income', (string) (int) $report['net_income']]);

        return \implode("\n", $lines) . "\n";
    }

    private function csvBalanceSheetRows(?string $to): string
    {
        $report = $this->reports->balanceSheet($to);
        $lines = ['section,code,title,balance_minor'];

        foreach (['asset_rows' => 'asset', 'liability_rows' => 'liability', 'equity_rows' => 'equity'] as $key => $section) {
            foreach ($report[$key] as $row) {
                $lines[] = \implode(',', [$section, self::csvSafe((string) $row['code']), self::csvSafe((string) $row['title']), (string) (int) $row['balance']]);
            }
        }
        $lines[] = \implode(',', ['total', '', 'assets', (string) (int) $report['assets']]);
        $lines[] = \implode(',', ['total', '', 'liabilities_plus_equity_plus_ni', (string) ((int) $report['liabilities'] + (int) $report['equity'] + (int) $report['net_income'])]);
        $lines[] = \implode(',', ['check', '', 'balanced', $report['balanced'] ? '1' : '0']);

        return \implode("\n", $lines) . "\n";
    }

    private function csvTurnoverRows(?string $from, ?string $to): string
    {
        $lines = ['sku,name,unit,received,issued,on_hand,avg_cost_minor,value_minor'];
        foreach ($this->reports->inventoryTurnover($from, $to) as $row) {
            $lines[] = \implode(',', [
                self::csvSafe((string) $row['sku']),
                self::csvSafe((string) $row['name']),
                self::csvSafe((string) $row['unit']),
                (string) (float) $row['received'],
                (string) (float) $row['issued'],
                (string) (float) $row['on_hand'],
                (string) (int) $row['avg_cost'],
                (string) (int) $row['value'],
            ]);
        }

        return \implode("\n", $lines) . "\n";
    }

    private function csvPayrollRows(string $month): string
    {
        $lines = ['employee_code,employee,base_minor,overtime_minor,deductions_minor,tax_minor,net_minor'];
        foreach ($this->reports->payrollReport($month) as $row) {
            $lines[] = \implode(',', [
                self::csvSafe((string) $row['code']),
                self::csvSafe((string) $row['employee']),
                (string) (int) $row['base'],
                (string) (int) $row['overtime'],
                (string) (int) $row['deductions'],
                (string) (int) $row['tax'],
                (string) (int) $row['net'],
            ]);
        }

        return \implode("\n", $lines) . "\n";
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @return array{0: ?string, 1: ?string} */
    private function rangeOf(RequestInterface $request): array
    {
        $from = (string) $request->input('from', '');
        $to = (string) $request->input('to', '');

        return [$from !== '' ? $from : null, $to !== '' ? $to : null];
    }

    private static function csvSafe(string $value): string
    {
        $value = \str_replace('"', '""', $value);

        return '"' . $value . '"';
    }

    private function authorize(): void
    {
        if (!Gate::allows('erp.report.view')) {
            throw new ForbiddenException(messageId: 'erp::messages.forbidden', context: ['permission' => 'erp.report.view']);
        }
    }
}
