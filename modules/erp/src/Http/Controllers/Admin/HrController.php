<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * HrController — پرسنل/کارکرد/حقوق (/admin/erp/hr).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\HrService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class HrController
{
    public function __construct(private readonly HrService $service)
    {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('erp.hr.manage');

        $month = (string) $request->input('month', '');
        if ($month === '' || !\preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = \Tavaan\Support\Time\Clock::utcNow()->format('Y-m');
        }

        $attendance = [];
        foreach ($this->service->attendanceFor($month) as $row) {
            $attendance[$row->employeeId()] = $row;
        }

        return View::render('erp::admin.hr-index', [
            'employees' => $this->service->employees(false),
            'month' => $month,
            'attendance' => $attendance,
            'payrollRuns' => $this->service->payrollRuns(20),
        ]);
    }

    public function storeEmployee(RequestInterface $request): mixed
    {
        $this->authorize('erp.hr.manage');

        $validated = Validator::validate($request->all(), [
            'employee_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'position' => ['nullable', 'string', 'max:100'],
            'base_salary' => ['required', 'integer', 'min:0'],
            'national_id' => ['nullable', 'string', 'max:10'],
            'hire_date' => ['nullable', 'date'],
        ]);

        try {
            $this->service->createEmployee(
                (string) $validated['employee_code'],
                (string) $validated['name'],
                (int) $validated['base_salary'],
                isset($validated['position']) ? (string) $validated['position'] : null,
                isset($validated['national_id']) && $validated['national_id'] !== '' ? (string) $validated['national_id'] : null,
                isset($validated['hire_date']) ? (string) $validated['hire_date'] : null,
                (int) Auth::id(),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'employee_created');

        return redirect('/admin/erp/hr');
    }

    public function storeAttendance(RequestInterface $request): mixed
    {
        $this->authorize('erp.hr.manage');

        $validated = Validator::validate($request->all(), [
            'employee_id' => ['required', 'string'],
            'period_month' => ['required', 'string'],
            'work_hours' => ['required', 'numeric', 'min:0'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'leave_days' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->service->recordAttendance(
                (string) $validated['employee_id'],
                (string) $validated['period_month'],
                (float) $validated['work_hours'],
                (float) ($validated['overtime_hours'] ?? 0),
                (float) ($validated['leave_days'] ?? 0),
                (int) ($validated['deductions'] ?? 0),
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'attendance_saved');

        return back();
    }

    public function runPayroll(RequestInterface $request): mixed
    {
        $this->authorize('erp.payroll.process');

        $validated = Validator::validate($request->all(), [
            'period_month' => ['required', 'string'],
        ]);

        try {
            $this->service->runPayroll((string) $validated['period_month'], (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'payroll_processed');

        return redirect('/admin/erp/hr');
    }

    public function payPayroll(RequestInterface $request, string $id): mixed
    {
        $this->authorize('erp.payroll.process');

        $method = (string) $request->input('method', 'bank');

        try {
            $this->service->payPayroll($id, \in_array($method, ['cash', 'bank'], true) ? $method : 'bank', (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'payroll_paid');

        return redirect('/admin/erp/hr');
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'erp::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
