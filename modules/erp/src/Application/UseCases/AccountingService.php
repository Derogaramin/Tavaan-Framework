<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * AccountingService — دفتر کل مرکزی (DOC-097 §1.2/§2.1).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\Account;
use Modules\Erp\Domain\Entities\FiscalPeriod;
use Modules\Erp\Domain\Entities\Voucher;
use Modules\Erp\Domain\Entities\VoucherLine;
use Modules\Erp\Domain\Repositories\AccountingRepositoryInterface;

/**
 * AccountingService — تنها درگاه صدور سند حسابداری در ERP.
 *
 * قانون طلایی (AC-ERP-09): هر سند پیش از ذخیره `SUM(debit) === SUM(credit)`.
 * سند posted غیرقابل تغییر است؛ ابطال = سند معکوس + void.
 * دورهٔ بسته → رد سند جدید در آن بازه (قفل دوره).
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class AccountingService
{
    public function __construct(
        private readonly AccountingRepositoryInterface $repository,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    // ── حساب‌ها ────────────────────────────────────────────────────────────

    /** @return list<Account> */
    public function chartOfAccounts(): array
    {
        return $this->repository->accounts();
    }

    public function findAccount(int $id): ?Account
    {
        return $this->repository->findAccount($id);
    }

    /**
     * ایجاد سرفصل حساب (کد یکتا).
     */
    public function createAccount(string $code, string $title, string $type, ?string $parentCode = null): Account
    {
        $existing = $this->repository->findAccountByCode($code);
        if ($existing !== null) {
            throw new \DomainException('حساب با این کد پیش‌تر ساخته شده: ' . $code);
        }

        $level = 1;
        $parentId = null;
        if ($parentCode !== null && \trim($parentCode) !== '') {
            $parent = $this->repository->findAccountByCode(\trim($parentCode));
            if ($parent === null) {
                throw new \DomainException('حساب والد یافت نشد: ' . $parentCode);
            }
            if (!$parent->isLeaf()) {
                // والد از قبل branch است
            }
            $parentId = $parent->id();
            $level = $parent->level() + 1;
        }

        $account = Account::create($code, $title, \Modules\Erp\Domain\Enums\AccountType::tryFromString($type), $parentId, $level, true);
        $this->repository->saveAccount($account);

        if ($parentId !== null) {
            $parent = $this->repository->findAccount($parentId);
            if ($parent !== null && $parent->isLeaf()) {
                $parent->markBranch();
                $this->repository->saveAccount($parent);
            }
        }

        return $account;
    }

    // ── دوره‌ها ────────────────────────────────────────────────────────────

    /** @return list<FiscalPeriod> */
    public function periods(): array
    {
        return $this->repository->periods();
    }

    public function findPeriod(int $id): ?FiscalPeriod
    {
        return $this->repository->findPeriod($id);
    }

    public function createPeriod(string $title, string $startDate, string $endDate): FiscalPeriod
    {
        $period = FiscalPeriod::create($title, $startDate, $endDate);
        $this->repository->savePeriod($period);

        return $period;
    }

    // ── صدور سند ───────────────────────────────────────────────────────────

    /**
     * صدور سند — تنها درگاه ورودی دفتر کل.
     *
     * @param list<array{account_code: string, debit?: int, credit?: int, description?: string}> $lineSpecs
     */
    public function issue(
        string $sourceModule,
        ?string $sourceId,
        string $date,
        ?string $description,
        array $lineSpecs,
        ?int $userId = null,
        bool $autoPost = false,
    ): Voucher {
        $this->assertPeriodOpenFor($date);

        $lines = [];
        foreach ($lineSpecs as $spec) {
            $account = $this->repository->findAccountByCode((string) ($spec['account_code'] ?? ''));
            if ($account === null) {
                throw new \DomainException('سرفصل حساب یافت نشد: ' . (string) ($spec['account_code'] ?? ''));
            }
            if (!$account->isPostable()) {
                throw new \DomainException('فقط حساب تفصیلی (leaf) ردیف سند می‌گیرد: ' . $account->code());
            }

            $lines[] = VoucherLine::create(
                accountId: $account->id(),
                debit: (int) ($spec['debit'] ?? 0),
                credit: (int) ($spec['credit'] ?? 0),
                description: isset($spec['description']) ? (string) $spec['description'] : null,
            );
        }

        $voucher = Voucher::draft(
            voucherNumber: $this->repository->nextVoucherNumber($date),
            date: $date,
            lines: $lines,
            description: $description,
            sourceModule: $sourceModule,
            sourceId: $sourceId,
            periodId: $this->repository->findOpenPeriodForDate($date)?->id(),
            createdBy: $userId,
        );

        if (!$voucher->isBalanced()) {
            throw new \DomainException(\sprintf(
                'سند تراز نیست: debit=%d credit=%d (قانون طلایی — AC-ERP-09)',
                $voucher->totalDebit(),
                $voucher->totalCredit(),
            ));
        }

        $this->repository->saveVoucher($voucher);

        $this->audit->record('voucher.created', 'erp.voucher', $voucher->id(), [
            'voucher_number' => $voucher->voucherNumber(),
            'date' => $date,
            'source_module' => $sourceModule,
            'total_debit' => $voucher->totalDebit(),
        ], $userId);

        if ($autoPost) {
            $this->post($voucher->id(), $userId);

            return $this->repository->findVoucher($voucher->id()) ?? $voucher;
        }

        return $voucher;
    }

    // ── چرخهٔ سند ──────────────────────────────────────────────────────────

    public function approve(string $voucherId, int $userId): void
    {
        $voucher = $this->mustFindVoucher($voucherId);
        $voucher->approve($userId);

        if (!$voucher->isBalanced()) {
            throw new \DomainException('سند ناتراز تأیید نمی‌شود.');
        }

        $this->repository->saveVoucher($voucher);
        $this->audit->record('voucher.approved', 'erp.voucher', $voucherId, ['by' => $userId], $userId);
    }

    public function post(string $voucherId, ?int $userId = null): void
    {
        $voucher = $this->mustFindVoucher($voucherId);
        $this->assertPeriodOpenFor($voucher->date());

        $voucher->post($userId);
        $this->repository->saveVoucher($voucher);

        $this->audit->record('voucher.posted', 'erp.voucher', $voucherId, [
            'voucher_number' => $voucher->voucherNumber(),
            'total_debit' => $voucher->totalDebit(),
        ], $userId);
    }

    /**
     * ابطال — با سند معکوس (posted) + void سند اصلی (DOC-097 §2.1 🔒).
     */
    public function voidWithReversal(string $voucherId, int $userId, ?string $date = null): Voucher
    {
        $original = $this->mustFindVoucher($voucherId);

        $reversal = $original->reverseVoucher(
            $this->repository->nextVoucherNumber($date ?? $original->date()),
            $userId,
            $date,
        );

        if (!$reversal->isBalanced()) {
            throw new \DomainException('سند معکوس ناتراز شد — خطای سیستمی.');
        }

        $this->assertPeriodOpenFor($reversal->date());
        $this->repository->saveVoucher($reversal);
        $reversal->post($userId);
        $this->repository->saveVoucher($reversal);

        $original->markVoidedBy($reversal->id());
        $this->repository->saveVoucher($original);

        $this->audit->record('voucher.voided', 'erp.voucher', $voucherId, [
            'reversal_voucher' => $reversal->voucherNumber(),
            'by' => $userId,
        ], $userId);

        return $reversal;
    }

    /** ذخیرهٔ مستقیم سند ساخته‌شده (بستن دوره/سند داخلی). */
    public function persistPrepared(Voucher $voucher, bool $autoPost = false, ?int $userId = null): Voucher
    {
        if (!$voucher->isBalanced()) {
            throw new \DomainException('سند ناتراز ذخیره نمی‌شود (AC-ERP-09).');
        }

        $this->repository->saveVoucher($voucher);

        if ($autoPost) {
            $voucher->post($userId);
            $this->repository->saveVoucher($voucher);
        }

        return $voucher;
    }

    // ── پرسش ───────────────────────────────────────────────────────────────

    /**
     * @return list<Voucher>
     * @param array<string, mixed> $filters
     */
    public function vouchers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->repository->vouchers($filters, $limit, $offset);
    }

    public function countVouchers(array $filters = []): int
    {
        return $this->repository->countVouchers($filters);
    }

    public function findVoucher(string $id): ?Voucher
    {
        return $this->repository->findVoucher($id);
    }

    public function findVoucherForSource(string $sourceModule, string $sourceId): ?Voucher
    {
        return $this->repository->findVoucherBySource($sourceModule, $sourceId);
    }

    /** @return array<string, int> نقشهٔ کد حساب‌های سیستمی → account_id */
    public function systemAccountIds(): array
    {
        $map = \Tavaan\Core\Facade\Facades\Config::get('erp.accounting.accounts', []);
        $ids = [];
        foreach ($map as $key => $code) {
            $account = $this->repository->findAccountByCode((string) $code);
            if ($account !== null) {
                $ids[(string) $key] = $account->id();
            }
        }

        return $ids;
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function mustFindVoucher(string $id): Voucher
    {
        $voucher = $this->repository->findVoucher($id);
        if ($voucher === null) {
            throw new \DomainException('سند حسابداری یافت نشد: ' . $id);
        }

        return $voucher;
    }

    /** 🔒 قفل دوره — سند جدید در دورهٔ بسته رد می‌شود (DOC-097 §4). */
    private function assertPeriodOpenFor(string $date): void
    {
        foreach ($this->repository->periods() as $period) {
            if ($period->containsDate($date) && !$period->isOpen()) {
                throw new \DomainException('دورهٔ مالی «' . $period->title() . '» بسته است — سند در آن ثبت نمی‌شود.');
            }
        }
    }
}
