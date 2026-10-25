<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * AccountingRepositoryInterface — پورت بافتهٔ حسابداری.
 */

namespace Modules\Erp\Domain\Repositories;

use Modules\Erp\Domain\Entities\Account;
use Modules\Erp\Domain\Entities\FiscalPeriod;
use Modules\Erp\Domain\Entities\Voucher;

/**
 * @api 1.0
 */
interface AccountingRepositoryInterface
{
    // ── حساب‌ها ────────────────────────────────────────────────────────────

    /** @return list<Account> همهٔ حساب‌ها (مرتب بر اساس code). */
    public function accounts(): array;

    public function findAccount(int $id): ?Account;

    public function findAccountByCode(string $code): ?Account;

    public function saveAccount(Account $account): void;

    // ── دوره‌ها ────────────────────────────────────────────────────────────

    /** @return list<FiscalPeriod> */
    public function periods(): array;

    public function findPeriod(int $id): ?FiscalPeriod;

    /** دورهٔ باز شامل تاریخ (Y-m-d) — یا null. */
    public function findOpenPeriodForDate(string $date): ?FiscalPeriod;

    public function savePeriod(FiscalPeriod $period): void;

    // ── اسناد ──────────────────────────────────────────────────────────────

    public function findVoucher(string $id, bool $withLines = true): ?Voucher;

    /** سند صادرشده از یک منبع (source_module + source_id). */
    public function findVoucherBySource(string $sourceModule, string $sourceId): ?Voucher;

    /**
     * @return list<Voucher>
     * @param array{status?: string, source_module?: string, date_from?: string, date_to?: string, period_id?: int, q?: string, sort?: string} $filters
     */
    public function vouchers(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function countVouchers(array $filters = []): int;

    /** ذخیرهٔ سند + ردیف‌ها (insert یا update draft). */
    public function saveVoucher(Voucher $voucher): void;

    /** شمارهٔ بعدی سند برای دوره (SV-<period|GEN>-<seq>). */
    public function nextVoucherNumber(?string $date = null): string;

    /**
     * ردیف‌های posted برای گزارش‌ها (join accounts).
     *
     * @return list<array{account_id: int, code: string, title: string, type: string, debit: int, credit: int}>
     */
    public function postedLines(?string $dateFrom = null, ?string $dateTo = null): array;

    /**
     * گردش یک حساب (posted lines + متادیتای سند).
     *
     * @return list<array{voucher_id: string, voucher_number: string, date: string, debit: int, credit: int, description: ?string, source_module: string}>
     */
    public function accountLedger(int $accountId, ?string $dateFrom = null, ?string $dateTo = null): array;

}
