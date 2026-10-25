<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DatabaseAccountingRepository — پایداری بافتهٔ حسابداری.
 */

namespace Modules\Erp\Infrastructure\Persistence;

use Modules\Erp\Domain\Entities\Account;
use Modules\Erp\Domain\Entities\FiscalPeriod;
use Modules\Erp\Domain\Entities\Voucher;
use Modules\Erp\Domain\Entities\VoucherLine;
use Modules\Erp\Domain\Repositories\AccountingRepositoryInterface;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;

/**
 * DatabaseAccountingRepository — accounts/periods/vouchers/lines.
 *
 * @internal
 */
final class DatabaseAccountingRepository implements AccountingRepositoryInterface
{
    // ── حساب‌ها ────────────────────────────────────────────────────────────

    public function accounts(): array
    {
        $rows = DB::table('erp_accounts')->orderBy('code')->get();

        return \array_map(static fn (array $row): Account => Account::fromRow($row), $rows);
    }

    public function findAccount(int $id): ?Account
    {
        $row = DB::table('erp_accounts')->where('id', '=', $id)->first();

        return $row !== null ? Account::fromRow($row) : null;
    }

    public function findAccountByCode(string $code): ?Account
    {
        $row = DB::table('erp_accounts')->where('code', '=', $code)->first();

        return $row !== null ? Account::fromRow($row) : null;
    }

    public function saveAccount(Account $account): void
    {
        $row = $account->toRow();

        if ($account->id() > 0) {
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            unset($row['created_at']);
            DB::table('erp_accounts')->where('id', '=', $account->id())->update($row);

            return;
        }

        unset($row['updated_at']);
        DB::table('erp_accounts')->insert($row);
    }

    // ── دوره‌ها ────────────────────────────────────────────────────────────

    public function periods(): array
    {
        $rows = DB::table('erp_fiscal_periods')->orderBy('start_date')->get();

        return \array_map(static fn (array $row): FiscalPeriod => FiscalPeriod::fromRow($row), $rows);
    }

    public function findPeriod(int $id): ?FiscalPeriod
    {
        $row = DB::table('erp_fiscal_periods')->where('id', '=', $id)->first();

        return $row !== null ? FiscalPeriod::fromRow($row) : null;
    }

    public function findOpenPeriodForDate(string $date): ?FiscalPeriod
    {
        $row = DB::table('erp_fiscal_periods')
            ->where('status', '=', 'open')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        return $row !== null ? FiscalPeriod::fromRow($row) : null;
    }

    public function savePeriod(FiscalPeriod $period): void
    {
        $row = $period->toRow();

        if ($period->id() > 0) {
            DB::table('erp_fiscal_periods')->where('id', '=', $period->id())->update($row);

            return;
        }

        $row['created_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
        $row['updated_at'] = null;
        $newId = DB::table('erp_fiscal_periods')->insertGetId($row);
        $period->assignId((int) $newId);
    }

    // ── اسناد ──────────────────────────────────────────────────────────────

    public function findVoucher(string $id, bool $withLines = true): ?Voucher
    {
        $row = DB::table('erp_vouchers')->where('id', '=', $id)->first();
        if ($row === null) {
            return null;
        }

        $lines = [];
        if ($withLines) {
            $lineRows = DB::table('erp_voucher_lines')->where('voucher_id', '=', $id)->get();
            foreach ($lineRows as $lineRow) {
                $lines[] = VoucherLine::fromRow($lineRow);
            }
        }

        return Voucher::fromRow($row, $lines);
    }

    public function findVoucherBySource(string $sourceModule, string $sourceId): ?Voucher
    {
        $row = DB::table('erp_vouchers')
            ->where('source_module', '=', $sourceModule)
            ->where('source_id', '=', $sourceId)
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->findVoucher((string) $row['id']);
    }

    public function vouchers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('erp_vouchers');
        $this->applyVoucherFilters($query, $filters);

        $sort = (string) ($filters['sort'] ?? 'latest');
        if ($sort === 'oldest') {
            $query->orderBy('date')->orderBy('voucher_number');
        } else {
            $query->orderByDesc('date')->orderByDesc('created_at');
        }

        $rows = $query->limit($limit)->offset($offset)->get();

        $out = [];
        foreach ($rows as $row) {
            // خطوط برای totalDebit/totalCredit و نمایش — بارگذاری می‌شوند
            $out[] = $this->findVoucher((string) $row['id']) ?? Voucher::fromRow($row);
        }

        return $out;
    }

    public function countVouchers(array $filters = []): int
    {
        $query = DB::table('erp_vouchers');
        $this->applyVoucherFilters($query, $filters);

        return $query->count();
    }

    public function saveVoucher(Voucher $voucher): void
    {
        $row = $voucher->toRow();
        $exists = DB::table('erp_vouchers')->where('id', '=', $voucher->id())->count() > 0;

        if ($exists) {
            $update = $row;
            unset($update['id'], $update['created_at']);
            DB::table('erp_vouchers')->where('id', '=', $voucher->id())->update($update);
        } else {
            DB::table('erp_vouchers')->insert($row);
        }

        // ردیف‌ها — فقط برای سند جدید (posted immutable است و ردیف تازه نمی‌گیرد)
        $existingLines = DB::table('erp_voucher_lines')->where('voucher_id', '=', $voucher->id())->count();
        if ($existingLines === 0) {
            foreach ($voucher->lines() as $line) {
                DB::table('erp_voucher_lines')->insert($line->toRow($voucher->id()));
            }
        }
    }

    public function nextVoucherNumber(?string $date = null): string
    {
        $prefix = (string) Config::get('erp.accounting.voucher_prefix', 'SV');
        $periodTag = 'GEN';

        $period = $this->findOpenPeriodForDate($date ?? Clock::utcNow()->format('Y-m-d'));
        if ($period !== null) {
            $periodTag = (string) $period->id();
        }

        // seq = تعداد اسناد + ۱ (با لایهٔ unique روی voucher_number — retry در تصادف)
        $seq = DB::table('erp_vouchers')->count() + 1;

        return \sprintf('%s-%s-%05d', $prefix, $periodTag, $seq);
    }

    public function postedLines(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = DB::table('erp_voucher_lines')
            ->join('erp_vouchers', 'erp_vouchers.id', '=', 'erp_voucher_lines.voucher_id')
            ->join('erp_accounts', 'erp_accounts.id', '=', 'erp_voucher_lines.account_id')
            ->where('erp_vouchers.status', '=', 'posted');

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('erp_vouchers.date', '>=', $dateFrom);
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('erp_vouchers.date', '<=', $dateTo);
        }

        $rows = $query->select(
            'erp_voucher_lines.account_id',
            'erp_accounts.code',
            'erp_accounts.title',
            'erp_accounts.type',
            'erp_voucher_lines.debit',
            'erp_voucher_lines.credit',
        )->get();

        return \array_map(static fn (array $row): array => [
            'account_id' => (int) $row['account_id'],
            'code' => (string) $row['code'],
            'title' => (string) $row['title'],
            'type' => (string) $row['type'],
            'debit' => (int) $row['debit'],
            'credit' => (int) $row['credit'],
        ], $rows);
    }

    public function accountLedger(int $accountId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = DB::table('erp_voucher_lines')
            ->join('erp_vouchers', 'erp_vouchers.id', '=', 'erp_voucher_lines.voucher_id')
            ->where('erp_voucher_lines.account_id', '=', $accountId)
            ->where('erp_vouchers.status', '=', 'posted');

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('erp_vouchers.date', '>=', $dateFrom);
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('erp_vouchers.date', '<=', $dateTo);
        }

        $rows = $query->orderBy('erp_vouchers.date')
            ->select(
                'erp_voucher_lines.voucher_id',
                'erp_vouchers.voucher_number',
                'erp_vouchers.date',
                'erp_voucher_lines.debit',
                'erp_voucher_lines.credit',
                'erp_voucher_lines.description',
                'erp_vouchers.source_module',
            )
            ->get();

        return \array_map(static fn (array $row): array => [
            'voucher_id' => (string) $row['voucher_id'],
            'voucher_number' => (string) $row['voucher_number'],
            'date' => (string) $row['date'],
            'debit' => (int) $row['debit'],
            'credit' => (int) $row['credit'],
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'source_module' => (string) $row['source_module'],
        ], $rows);
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $filters @param object $query */
    private function applyVoucherFilters(mixed $query, array $filters): void
    {
        foreach (['status', 'source_module'] as $key) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where('erp_vouchers.' . $key, '=', $value);
            }
        }

        $periodId = $filters['period_id'] ?? null;
        if ($periodId !== null && $periodId !== '') {
            $query->where('erp_vouchers.period_id', '=', (int) $periodId);
        }

        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $value = $filters[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $query->where('erp_vouchers.date', $op, $value);
            }
        }

        $q = $filters['q'] ?? null;
        if (\is_string($q) && \trim($q) !== '') {
            $needle = '%' . \trim($q) . '%';
            $query->where(static function (mixed $inner) use ($needle): void {
                $inner->where('erp_vouchers.voucher_number', 'like', $needle)
                    ->orWhere('erp_vouchers.description', 'like', $needle);
            });
        }
    }
}
