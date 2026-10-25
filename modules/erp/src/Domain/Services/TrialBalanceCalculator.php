<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * TrialBalanceCalculator — تراز آزمایشی/سود و زیان/ترازنامه (DOC-097 §4-5).
 */

namespace Modules\Erp\Domain\Services;

use Modules\Erp\Domain\Enums\AccountType;

/**
 * ورودی: ردیف‌های سند **posted** (account_id/type/debit/credit).
 * خروجی: خالص هر حساب با علامت ماهیت (ماندهٔ طبیعی مثبت).
 *
 * ```
 * balance(debit-nature)  = SUM(debit) − SUM(credit)
 * balance(credit-nature) = SUM(credit) − SUM(debit)
 * ترازنامه: assets === liabilities + equity + (revenue − expense)
 * ```
 *
 * دامنهٔ خالص — بدون DB.
 *
 * @api 1.0
 */
final class TrialBalanceCalculator
{
    /**
     * تراز آزمایشی — به تفکیک حساب.
     *
     * @param list<array{account_id: int, code?: string, title?: string, type: string, debit: int, credit: int}> $lines
     * @return list<array{account_id: int, code: string, title: string, type: string, debit: int, credit: int, balance: int}>
     */
    public function trialBalance(array $lines): array
    {
        $byAccount = [];

        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $type = AccountType::tryFromString((string) ($line['type'] ?? 'asset'));

            $byAccount[$accountId] ??= [
                'account_id' => $accountId,
                'code' => (string) ($line['code'] ?? ''),
                'title' => (string) ($line['title'] ?? ''),
                'type' => $type->value,
                'debit' => 0,
                'credit' => 0,
            ];
            $byAccount[$accountId]['debit'] += (int) ($line['debit'] ?? 0);
            $byAccount[$accountId]['credit'] += (int) ($line['credit'] ?? 0);
        }

        $out = [];
        foreach ($byAccount as $row) {
            $type = AccountType::tryFromString($row['type']);
            $normalDebit = $type->normalBalance() === 'debit';

            $row['balance'] = $normalDebit
                ? $row['debit'] - $row['credit']
                : $row['credit'] - $row['debit'];

            $out[] = $row;
        }

        \usort($out, static fn (array $a, array $b): int => \strcmp($a['code'], $b['code']));

        return $out;
    }

    /**
     * جمع‌های صورت‌های مالی.
     *
     * @param list<array{account_id: int, type: string, debit: int, credit: int}> $lines
     * @return array{assets: int, liabilities: int, equity: int, revenue: int, expense: int, net_income: int, balanced: bool}
     */
    public function statementTotals(array $lines): array
    {
        $totals = ['asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0];

        foreach ($lines as $line) {
            $type = AccountType::tryFromString((string) ($line['type'] ?? 'asset'));
            $normalDebit = $type->normalBalance() === 'debit';

            $balance = $normalDebit
                ? (int) ($line['debit'] ?? 0) - (int) ($line['credit'] ?? 0)
                : (int) ($line['credit'] ?? 0) - (int) ($line['debit'] ?? 0);

            $totals[$type->value] += $balance;
        }

        $netIncome = $totals['revenue'] - $totals['expense'];

        return [
            'assets' => $totals['asset'],
            'liabilities' => $totals['liability'],
            'equity' => $totals['equity'],
            'revenue' => $totals['revenue'],
            'expense' => $totals['expense'],
            'net_income' => $netIncome,
            // 🔒 معادلهٔ اساسی حسابداری: A = L + E + NI
            'balanced' => $totals['asset'] === $totals['liability'] + $totals['equity'] + $netIncome,
        ];
    }

    /** آیا جمع ردیف‌ها تراز است (کنترل دوباره برای گزارش)؟ */
    public function linesAreBalanced(array $lines): bool
    {
        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $debit += (int) ($line['debit'] ?? 0);
            $credit += (int) ($line['credit'] ?? 0);
        }

        return $debit === $credit;
    }
}
