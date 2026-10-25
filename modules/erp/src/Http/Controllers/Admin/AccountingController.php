<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * AccountingController — حساب‌ها/اسناد/دوره‌ها (/admin/erp/*).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\AccountingService;
use Modules\Erp\Application\UseCases\PeriodCloseService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class AccountingController
{
    public function __construct(
        private readonly AccountingService $service,
        private readonly PeriodCloseService $periodClose,
    ) {
    }

    // ── سرفصل حساب‌ها ──────────────────────────────────────────────────────

    public function accounts(): mixed
    {
        $this->authorize('erp.account.view');

        return View::render('erp::admin.accounts-index', [
            'accounts' => $this->service->chartOfAccounts(),
        ]);
    }

    public function storeAccount(RequestInterface $request): mixed
    {
        $this->authorize('erp.account.manage');

        $validated = Validator::validate($request->all(), [
            'code' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:190'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_code' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $this->service->createAccount(
                (string) $validated['code'],
                (string) $validated['title'],
                (string) $validated['type'],
                isset($validated['parent_code']) && $validated['parent_code'] !== '' ? (string) $validated['parent_code'] : null,
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'account_created');

        return redirect('/admin/erp/accounts');
    }

    // ── اسناد ──────────────────────────────────────────────────────────────

    public function vouchers(RequestInterface $request): mixed
    {
        $this->authorize('erp.voucher.view');

        $page = \max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $filters = [];
        foreach (['status', 'source_module'] as $key) {
            if (($value = (string) $request->input($key, '')) !== '') {
                $filters[$key] = $value;
            }
        }

        return View::render('erp::admin.vouchers-index', [
            'vouchers' => $this->service->vouchers($filters, $perPage, ($page - 1) * $perPage),
            'total' => $this->service->countVouchers($filters),
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters,
        ]);
    }

    public function createVoucher(): mixed
    {
        $this->authorize('erp.voucher.create');

        return View::render('erp::admin.voucher-form', [
            'accounts' => $this->service->chartOfAccounts(),
        ]);
    }

    public function storeVoucher(RequestInterface $request): mixed
    {
        $this->authorize('erp.voucher.create');

        $validated = Validator::validate($request->all(), [
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // ردیف‌ها از فرم پویا: account_code[] + debit[] + credit[]
        $codes = (array) $request->input('account_code', []);
        $debits = (array) $request->input('debit', []);
        $credits = (array) $request->input('credit', []);

        $lineSpecs = [];
        foreach ($codes as $index => $code) {
            $code = \trim((string) $code);
            $debit = (int) ($debits[$index] ?? 0);
            $credit = (int) ($credits[$index] ?? 0);
            if ($code === '' || ($debit === 0 && $credit === 0)) {
                continue;
            }
            $lineSpecs[] = ['account_code' => $code, 'debit' => $debit, 'credit' => $credit];
        }

        try {
            $voucher = $this->service->issue(
                sourceModule: 'manual',
                sourceId: null,
                date: (string) $validated['date'],
                description: isset($validated['description']) ? (string) $validated['description'] : null,
                lineSpecs: $lineSpecs,
                userId: (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'voucher_created');

        return redirect('/admin/erp/vouchers/' . $voucher->id());
    }

    public function showVoucher(string $id): mixed
    {
        $this->authorize('erp.voucher.view');

        $voucher = $this->service->findVoucher($id);
        if ($voucher === null) {
            throw new RouteNotFoundException(messageId: 'erp::messages.not_found');
        }

        $accountTitles = [];
        foreach ($this->service->chartOfAccounts() as $account) {
            $accountTitles[$account->id()] = $account->code() . ' — ' . $account->title();
        }

        return View::render('erp::admin.voucher-show', [
            'voucher' => $voucher,
            'accountTitles' => $accountTitles,
        ]);
    }

    public function approveVoucher(string $id): mixed
    {
        $this->authorize('erp.voucher.approve');

        try {
            $this->service->approve($id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'voucher_approved');

        return redirect('/admin/erp/vouchers/' . $id);
    }

    public function postVoucher(string $id): mixed
    {
        $this->authorize('erp.voucher.post');

        try {
            $this->service->post($id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'voucher_posted');

        return redirect('/admin/erp/vouchers/' . $id);
    }

    public function voidVoucher(string $id): mixed
    {
        $this->authorize('erp.voucher.void');

        try {
            $this->service->voidWithReversal($id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'voucher_voided');

        return redirect('/admin/erp/vouchers/' . $id);
    }

    // ── دوره‌ها ────────────────────────────────────────────────────────────

    public function periods(): mixed
    {
        $this->authorize('erp.period.manage', own: 'erp.voucher.view');

        $periods = $this->service->periods();
        $previews = [];
        foreach ($periods as $period) {
            if ($period->isOpen()) {
                try {
                    $preview = $this->periodClose->previewClose($period->id());
                    $previews[$period->id()] = $preview['totals'];
                } catch (\Throwable) {
                    $previews[$period->id()] = null;
                }
            }
        }

        return View::render('erp::admin.periods-index', [
            'periods' => $periods,
            'previews' => $previews,
        ]);
    }

    public function storePeriod(RequestInterface $request): mixed
    {
        $this->authorize('erp.period.manage');

        $validated = Validator::validate($request->all(), [
            'title' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        try {
            $this->service->createPeriod((string) $validated['title'], (string) $validated['start_date'], (string) $validated['end_date']);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'period_created');

        return redirect('/admin/erp/periods');
    }

    public function closePeriod(string $id): mixed
    {
        $this->authorize('erp.period.manage');

        try {
            $this->periodClose->closePeriod((int) $id, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'period_closed');

        return redirect('/admin/erp/periods');
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function authorize(string $permission, ?string $own = null): void
    {
        if (Gate::allows($permission)) {
            return;
        }
        if ($own !== null && Gate::allows($own)) {
            return;
        }

        throw new ForbiddenException(messageId: 'erp::messages.forbidden', context: ['permission' => $permission]);
    }
}
