<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PeriodCloseService — بستن دورهٔ مالی (DOC-097 §4).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\FiscalPeriod;
use Modules\Erp\Domain\Entities\Voucher;
use Modules\Erp\Domain\Enums\AccountType;
use Modules\Erp\Domain\Repositories\AccountingRepositoryInterface;
use Modules\Erp\Domain\Services\TrialBalanceCalculator;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Tavaan\Core\Facade\Facades\Config;

/**
 * PeriodCloseService — گام ۴-۵ §4:
 *
 * ```
 * بستن حساب‌های موقت (درآمد/هزینه) → خلاصهٔ سود و زیان → سود انباشته
 * → دوره closed (قفل سند جدید در بازه)
 * ```
 *
 * سند بستن (closing voucher) در یک سند تراز صادر و posted می‌شود:
 * - درآمدها: بدهکار درآمد / بستانکار خلاصهٔ سود و زیان
 * - هزینه‌ها: بدهکار خلاصهٔ سود و زیان / بستانکار هزینه
 * - انتقال نتیجه: خلاصهٔ سود و زیان ↔ سود انباشته
 *
 * 🔒 ماندهٔ حساب‌های موقت از اسناد posted **کل تاریخ تا پایان دوره**
 * محاسبه می‌شود (بستن تجمعی — ساده و قطعی برای v1).
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class PeriodCloseService
{
    public function __construct(
        private readonly AccountingRepositoryInterface $repository,
        private readonly AccountingService $accountingService,
        private readonly TrialBalanceCalculator $calculator,
        private readonly OutboxPublisher $outbox,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    /**
     * بستن دوره — با سند بستن تراز و posted.
     */
    public function closePeriod(int $periodId, int $userId): FiscalPeriod
    {
        $period = $this->repository->findPeriod($periodId);
        if ($period === null) {
            throw new \DomainException('دورهٔ مالی یافت نشد: ' . $periodId);
        }
        if (!$period->isOpen()) {
            throw new \DomainException('دوره پیش‌تر بسته شده است: ' . $period->title());
        }

        // ماندهٔ حساب‌های موقت تا پایان دوره (posted)
        $lines = $this->repository->postedLines(null, $period->endDate());
        $balances = $this->temporaryBalances($lines);

        $accounts = Config::get('erp.accounting.accounts', []);
        $incomeSummaryCode = (string) ($accounts['income_summary'] ?? '3201');
        $retainedCode = (string) ($accounts['retained_earnings'] ?? '3301');

        $incomeSummary = $this->repository->findAccountByCode($incomeSummaryCode);
        $retained = $this->repository->findAccountByCode($retainedCode);
        if ($incomeSummary === null || $retained === null) {
            throw new \DomainException('حساب خلاصهٔ سود و زیان/سود انباشته در chart وجود ندارد.');
        }

        $closingLines = [];
        foreach ($balances as $accountId => $info) {
            $code = (string) $info['code'];
            $balance = (int) $info['balance'];   // مانده با علامت ماهیت (همیشه ≥ ۰ برای حساب‌های فعال)
            if ($balance === 0) {
                continue;
            }

            if ($info['type'] === AccountType::Revenue->value) {
                // بستن درآمد: بدهکار درآمد / بستانکار خلاصهٔ سود و زیان
                $closingLines[] = ['account_code' => $code, 'debit' => $balance, 'description' => 'بستن درآمد — ' . $period->title()];
                $closingLines[] = ['account_code' => $incomeSummaryCode, 'credit' => $balance, 'description' => 'بستن درآمد'];
            } else {
                // بستن هزینه: بدهکار خلاصهٔ سود و زیان / بستانکار هزینه
                $closingLines[] = ['account_code' => $incomeSummaryCode, 'debit' => $balance, 'description' => 'بستن هزینه — ' . $period->title()];
                $closingLines[] = ['account_code' => $code, 'credit' => $balance, 'description' => 'بستن هزینه'];
            }
        }

        // انتقال نتیجهٔ خلاصهٔ سود و زیان به سود انباشته
        $totals = $this->calculator->statementTotals($lines);
        $netIncome = (int) $totals['net_income'];
        if ($netIncome > 0) {
            // سود: بدهکار خلاصهٔ سود و زیان / بستانکار سود انباشته
            $closingLines[] = ['account_code' => $incomeSummaryCode, 'debit' => $netIncome, 'description' => 'انتقال سود دوره'];
            $closingLines[] = ['account_code' => $retainedCode, 'credit' => $netIncome, 'description' => 'سود دوره — ' . $period->title()];
        } elseif ($netIncome < 0) {
            // زیان: بدهکار سود انباشته / بستانکار خلاصهٔ سود و زیان
            $closingLines[] = ['account_code' => $retainedCode, 'debit' => -$netIncome, 'description' => 'زیان دوره — ' . $period->title()];
            $closingLines[] = ['account_code' => $incomeSummaryCode, 'credit' => -$netIncome, 'description' => 'انتقال زیان دوره'];
        }

        if ($closingLines === []) {
            throw new \DomainException('دوره هیچ فعالیت درآمد/هزینه‌ای ندارد — سند بستن بی‌معنا است.');
        }

        $voucher = $this->accountingService->issue(
            sourceModule: 'closing',
            sourceId: 'period:' . $periodId,
            date: $period->endDate(),
            description: 'سند بستن حساب‌های موقت — ' . $period->title(),
            lineSpecs: $closingLines,
            userId: $userId,
        );

        // post مستقیم (بدون assertPeriodOpen — خودِ سند بستن است)
        $voucher->post($userId);
        $this->repository->saveVoucher($voucher);

        $period->close($voucher->id(), $userId);
        $this->repository->savePeriod($period);

        foreach ($period->pullEvents() as $event) {
            $this->outbox->store($event);
        }
        $this->outbox->flushNow();

        $this->audit->record('period.closed', 'erp.fiscal_period', (string) $periodId, [
            'title' => $period->title(),
            'close_voucher' => $voucher->voucherNumber(),
            'net_income' => $netIncome,
            'by' => $userId,
        ], $userId);

        return $period;
    }

    /**
     * ماندهٔ حساب‌های موقت (درآمد/هزینه) — به تفکیک حساب.
     *
     * @param list<array{account_id: int, code: string, title: string, type: string, debit: int, credit: int}> $lines
     * @return array<int, array{code: string, title: string, type: string, balance: int}>
     */
    private function temporaryBalances(array $lines): array
    {
        $trial = $this->calculator->trialBalance($lines);
        $out = [];
        foreach ($trial as $row) {
            $type = AccountType::tryFromString((string) $row['type']);
            if ($type->isTemporary()) {
                $out[(int) $row['account_id']] = [
                    'code' => (string) $row['code'],
                    'title' => (string) $row['title'],
                    'type' => $type->value,
                    'balance' => (int) $row['balance'],
                ];
            }
        }

        return $out;
    }

    /** پیش‌نمایش بستن (بدون اجرا) — برای UI/تست. */
    public function previewClose(int $periodId): array
    {
        $period = $this->repository->findPeriod($periodId);
        if ($period === null) {
            throw new \DomainException('دورهٔ مالی یافت نشد: ' . $periodId);
        }

        $lines = $this->repository->postedLines(null, $period->endDate());

        return [
            'period' => $period,
            'temporary' => $this->temporaryBalances($lines),
            'totals' => $this->calculator->statementTotals($lines),
        ];
    }
}
