<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ErpReportService — گزارش‌های مالی (DOC-097 §5).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\Account;
use Modules\Erp\Domain\Repositories\AccountingRepositoryInterface;
use Modules\Erp\Domain\Repositories\HrRepositoryInterface;
use Modules\Erp\Domain\Repositories\InventoryRepositoryInterface;
use Modules\Erp\Domain\Repositories\ProcurementRepositoryInterface;
use Modules\Erp\Domain\Repositories\ProductionRepositoryInterface;
use Modules\Erp\Domain\Repositories\SalesRepositoryInterface;
use Modules\Erp\Domain\Services\TrialBalanceCalculator;

/**
 * ErpReportService — گزارش‌های §5:
 *
 * | گزارش | پیاده‌سازی |
 * |---|---|
 * | تراز آزمایشی | trialBalance() — مانده per account (posted) |
 * | صورت سود و زیان | profitLoss() — درآمد/هزینه/سود خالص بازه |
 * | ترازنامه | balanceSheet() — A = L + E + NI (balanced flag) |
 * | گردش کالا | inventoryTurnover() |
 * | بهای تمام‌شده | productionCost() |
 * | حساب مشتری/تامین‌کننده | customerStatement()/supplierStatement() |
 * | حقوق و دستمزد | payrollReport() |
 *
 * همه از اسناد **posted** — مبالغ int minor-unit.
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class ErpReportService
{
    public function __construct(
        private readonly AccountingRepositoryInterface $accounting,
        private readonly InventoryRepositoryInterface $inventory,
        private readonly ProcurementRepositoryInterface $procurement,
        private readonly SalesRepositoryInterface $sales,
        private readonly ProductionRepositoryInterface $production,
        private readonly HrRepositoryInterface $hr,
        private readonly TrialBalanceCalculator $calculator,
    ) {
    }

    /**
     * تراز آزمایشی — جمع بدهکار/بستانکار + مانده per account.
     *
     * @return array{rows: list<array<string, mixed>>, total_debit: int, total_credit: int, balanced: bool}
     */
    public function trialBalance(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $lines = $this->accounting->postedLines($dateFrom, $dateTo);
        $rows = $this->calculator->trialBalance($lines);

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($rows as $row) {
            $totalDebit += (int) $row['debit'];
            $totalCredit += (int) $row['credit'];
        }

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => $totalDebit === $totalCredit,
        ];
    }

    /**
     * صورت سود و زیان.
     *
     * @return array{revenue_rows: list<array<string, mixed>>, expense_rows: list<array<string, mixed>>, revenue: int, expense: int, net_income: int}
     */
    public function profitLoss(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $lines = $this->accounting->postedLines($dateFrom, $dateTo);
        $rows = $this->calculator->trialBalance($lines);

        $revenueRows = [];
        $expenseRows = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'revenue') {
                $revenueRows[] = $row;
            } elseif ($row['type'] === 'expense') {
                $expenseRows[] = $row;
            }
        }

        $totals = $this->calculator->statementTotals($lines);

        return [
            'revenue_rows' => $revenueRows,
            'expense_rows' => $expenseRows,
            'revenue' => (int) $totals['revenue'],
            'expense' => (int) $totals['expense'],
            'net_income' => (int) $totals['net_income'],
        ];
    }

    /**
     * ترازنامه — 🔒 balanced یعنی A = L + E + NI.
     *
     * @return array{assets: int, liabilities: int, equity: int, net_income: int, balanced: bool, asset_rows: list<array<string, mixed>>, liability_rows: list<array<string, mixed>>, equity_rows: list<array<string, mixed>>}
     */
    public function balanceSheet(?string $dateTo = null): array
    {
        $lines = $this->accounting->postedLines(null, $dateTo);
        $rows = $this->calculator->trialBalance($lines);
        $totals = $this->calculator->statementTotals($lines);

        $assets = [];
        $liabilities = [];
        $equity = [];
        foreach ($rows as $row) {
            if ((int) $row['balance'] === 0) {
                continue;
            }
            match ($row['type']) {
                'asset' => $assets[] = $row,
                'liability' => $liabilities[] = $row,
                'equity' => $equity[] = $row,
                default => null,
            };
        }

        return [
            'assets' => (int) $totals['assets'],
            'liabilities' => (int) $totals['liabilities'],
            'equity' => (int) $totals['equity'],
            'net_income' => (int) $totals['net_income'],
            'balanced' => (bool) $totals['balanced'],
            'asset_rows' => $assets,
            'liability_rows' => $liabilities,
            'equity_rows' => $equity,
        ];
    }

    /** گردش یک حساب (ledger) — ردیف‌های posted با ماندهٔ روان. */
    public function accountLedger(int $accountId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $account = $this->accounting->findAccount($accountId);
        $entries = $this->accounting->accountLedger($accountId, $dateFrom, $dateTo);

        $running = 0;
        $normalDebit = $account !== null && $account->normalBalance() === 'debit';
        foreach ($entries as &$entry) {
            $running += $normalDebit
                ? (int) $entry['debit'] - (int) $entry['credit']
                : (int) $entry['credit'] - (int) $entry['debit'];
            $entry['running'] = $running;
        }
        unset($entry);

        return ['account' => $account, 'entries' => $entries, 'closing' => $running];
    }

    /**
     * گردش کالا — ورود/خروج per item در بازه.
     *
     * @return list<array{item_id: string, sku: string, name: string, unit: string, received: float, issued: float, on_hand: float, avg_cost: int, value: int}>
     */
    public function inventoryTurnover(?string $dateFrom = null, ?string $dateTo = null, ?string $warehouseId = null): array
    {
        $turnover = $this->inventory->turnover($dateFrom, $dateTo, $warehouseId);
        $byItem = [];
        foreach ($turnover as $row) {
            $byItem[(string) $row['item_id']] = $row;
        }

        $out = [];
        foreach ($this->inventory->items() as $item) {
            $row = $byItem[$item->id()] ?? null;
            $onHand = $this->inventory->totalQuantityOf($item->id());

            $out[] = [
                'item_id' => $item->id(),
                'sku' => $item->sku(),
                'name' => $item->name(),
                'unit' => $item->unit(),
                'received' => $row !== null ? (float) $row['received'] : 0.0,
                'issued' => $row !== null ? (float) $row['issued'] : 0.0,
                'on_hand' => $onHand,
                'avg_cost' => $item->avgCost(),
                'value' => (int) \round($onHand * $item->avgCost()),
            ];
        }

        return $out;
    }

    /**
     * بهای تمام‌شدهٔ تولید — per work order.
     *
     * @return list<array{wo_number: string, product: string, planned: float, produced: float, material_cost: int, unit_cost: int, status: string}>
     */
    public function productionCost(): array
    {
        $out = [];
        foreach ($this->production->workOrders([], 200) as $order) {
            $product = $this->inventory->findItem($order->productId());

            $out[] = [
                'wo_number' => $order->woNumber(),
                'product' => $product !== null ? $product->name() : $order->productId(),
                'planned' => $order->plannedQty(),
                'produced' => $order->producedQty(),
                'material_cost' => $order->materialCost(),
                'unit_cost' => $order->unitCost(),
                'status' => $order->status()->value,
            ];
        }

        return $out;
    }

    /**
     * حساب مشتری — فاکتورها + دریافت‌ها + مانده.
     *
     * @return array{customer: mixed, entries: list<array<string, mixed>>, balance: int}
     */
    public function customerStatement(string $customerId): array
    {
        $customer = $this->sales->findCustomer($customerId);
        if ($customer === null) {
            throw new \DomainException('مشتری یافت نشد: ' . $customerId);
        }

        $entries = [];
        foreach ($this->sales->salesInvoices($customerId, 200) as $invoice) {
            $entries[] = [
                'date' => $invoice->issuedAt()->format('Y-m-d'),
                'ref' => $invoice->invoiceNumber(),
                'type' => 'invoice',
                'debit' => $invoice->amount(),
                'credit' => 0,
            ];
        }
        foreach ($this->sales->paymentsIn($customerId, 200) as $payment) {
            $entries[] = [
                'date' => $payment->paidAt()->format('Y-m-d'),
                'ref' => $payment->id(),
                'type' => 'payment',
                'debit' => 0,
                'credit' => $payment->amount(),
            ];
        }

        \usort($entries, static fn (array $a, array $b): int => \strcmp((string) $a['date'], (string) $b['date']));

        return [
            'customer' => $customer,
            'entries' => $entries,
            'balance' => $this->sales->customerBalance($customerId),
        ];
    }

    /**
     * حساب تأمین‌کننده — فاکتورها + پرداخت‌ها + مانده.
     *
     * @return array{supplier: mixed, entries: list<array<string, mixed>>, balance: int}
     */
    public function supplierStatement(string $supplierId): array
    {
        $supplier = $this->procurement->findSupplier($supplierId);
        if ($supplier === null) {
            throw new \DomainException('تأمین‌کننده یافت نشد: ' . $supplierId);
        }

        $entries = [];
        // بدهی در رسید کالا شناسایی می‌شود (بستانکار = افزایش بدهی به تأمین‌کننده)
        foreach ($this->procurement->goodsReceipts(500) as $receipt) {
            if ($receipt->supplierId() !== $supplierId) {
                continue;
            }
            $entries[] = [
                'date' => $receipt->createdAt()->format('Y-m-d'),
                'ref' => $receipt->receiptNumber(),
                'type' => 'receipt',
                'debit' => 0,
                'credit' => $receipt->totalCost(),
            ];
        }
        foreach ($this->procurement->paymentsOut($supplierId, 200) as $payment) {
            $entries[] = [
                'date' => $payment->paidAt()->format('Y-m-d'),
                'ref' => $payment->id(),
                'type' => 'payment',
                'debit' => $payment->amount(),
                'credit' => 0,
            ];
        }

        \usort($entries, static fn (array $a, array $b): int => \strcmp((string) $a['date'], (string) $b['date']));

        return [
            'supplier' => $supplier,
            'entries' => $entries,
            'balance' => $this->procurement->supplierBalance($supplierId),
        ];
    }

    /**
     * گزارش حقوق و دستمزد — فیش‌های یک دوره.
     *
     * @return list<array{employee: string, code: string, base: int, overtime: int, deductions: int, tax: int, net: int}>
     */
    public function payrollReport(string $periodMonth): array
    {
        $employees = [];
        foreach ($this->hr->employees(false) as $employee) {
            $employees[$employee->id()] = $employee;
        }

        $out = [];
        foreach ($this->hr->payrollRuns(50) as $run) {
            if ($run->periodMonth() !== $periodMonth) {
                continue;
            }
            foreach ($this->hr->payslipsOf($run->id()) as $slip) {
                $employee = $employees[$slip->employeeId()] ?? null;

                $out[] = [
                    'employee' => $employee !== null ? $employee->name() : $slip->employeeId(),
                    'code' => $employee !== null ? $employee->employeeCode() : '',
                    'base' => $slip->base(),
                    'overtime' => $slip->overtime(),
                    'deductions' => $slip->deductions(),
                    'tax' => $slip->tax(),
                    'net' => $slip->net(),
                ];
            }
        }

        return $out;
    }

    /** حساب‌های کل (chart) — برای CSV/نمایش. @return list<Account> */
    public function chartOfAccounts(): array
    {
        return $this->accounting->accounts();
    }
}
