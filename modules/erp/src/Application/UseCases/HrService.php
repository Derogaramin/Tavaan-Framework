<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * HrService — پرسنل/کارکرد/حقوق و دستمزد (DOC-097 §2.5).
 */

namespace Modules\Erp\Application\UseCases;

use Modules\Erp\Domain\Entities\Attendance;
use Modules\Erp\Domain\Entities\Employee;
use Modules\Erp\Domain\Entities\PayrollRun;
use Modules\Erp\Domain\Entities\Payslip;
use Modules\Erp\Domain\Repositories\HrRepositoryInterface;
use Modules\Erp\Domain\Services\PayrollCalculator;
use Modules\Erp\Infrastructure\Integration\OutboxPublisher;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Support\Time\Clock;

/**
 * HrService — چرخهٔ حقوق:
 *
 * ```
 * کارکرد ماهانه → PayrollRun(draft) → process (فیش‌ها + سند هزینه با Outbox)
 * → pay (سند پرداختنی/بانک)
 * ```
 *
 * 🔒 national_id پرسنل در DB رمزنگاری‌شده ذخیره می‌شود (لایهٔ زیرساخت — الگوی CRM).
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class HrService
{
    public function __construct(
        private readonly HrRepositoryInterface $repository,
        private readonly PayrollCalculator $payrollCalculator,
        private readonly AccountingService $accountingService,
        private readonly OutboxPublisher $outbox,
        private readonly ErpAuditRecorder $audit,
    ) {
    }

    // ── پرسنل ──────────────────────────────────────────────────────────────

    public function createEmployee(
        string $employeeCode,
        string $name,
        int $baseSalary,
        ?string $position = null,
        ?string $nationalId = null,
        ?string $hireDate = null,
        ?int $userId = null,
    ): Employee {
        $employee = Employee::create($employeeCode, $name, $baseSalary, $position, $nationalId, $hireDate, $userId);
        $this->repository->saveEmployee($employee);

        $this->audit->record('employee.created', 'erp.employee', $employee->id(), [
            'employee_code' => $employee->employeeCode(),
            // 🔒 national_id در audit ثبت نمی‌شود (دادهٔ حساس)
        ], $userId);

        return $employee;
    }

    public function updateEmployee(string $employeeId, ?string $name = null, ?string $position = null, ?int $baseSalary = null, ?int $userId = null): Employee
    {
        $employee = $this->mustFindEmployee($employeeId);
        $employee->revise($name, $position, $baseSalary);
        $this->repository->saveEmployee($employee);

        return $employee;
    }

    public function terminateEmployee(string $employeeId, ?int $userId = null): void
    {
        $employee = $this->mustFindEmployee($employeeId);
        $employee->terminate();
        $this->repository->saveEmployee($employee);

        $this->audit->record('employee.terminated', 'erp.employee', $employeeId, [], $userId);
    }

    /** @return list<Employee> */
    public function employees(bool $onlyActive = true): array
    {
        return $this->repository->employees($onlyActive);
    }

    public function findEmployee(string $id): ?Employee
    {
        return $this->repository->findEmployee($id);
    }

    // ── کارکرد ─────────────────────────────────────────────────────────────

    public function recordAttendance(string $employeeId, string $periodMonth, float $workHours, float $overtimeHours = 0.0, float $leaveDays = 0.0, int $deductions = 0): Attendance
    {
        $this->mustFindEmployee($employeeId);

        $attendance = Attendance::record($employeeId, $periodMonth, $workHours, $overtimeHours, $leaveDays, $deductions);
        $this->repository->saveAttendance($attendance);

        return $attendance;
    }

    public function findAttendance(string $employeeId, string $periodMonth): ?Attendance
    {
        return $this->repository->findAttendance($employeeId, $periodMonth);
    }

    /** @return list<Attendance> */
    public function attendanceFor(string $periodMonth): array
    {
        return $this->repository->attendanceFor($periodMonth);
    }

    // ── حقوق و دستمزد ──────────────────────────────────────────────────────

    /**
     * پردازش حقوق ماه — فیش همهٔ پرسنل فعال + سند هزینهٔ حقوق با Outbox.
     *
     * سند (listener مصرف‌کنندهٔ hr.payroll.processed):
     * بدهکار هزینهٔ حقوق (gross) / بستانکار حقوق پرداختنی.
     */
    public function runPayroll(string $periodMonth, ?int $userId = null): PayrollRun
    {
        $run = PayrollRun::create($periodMonth);
        $this->repository->savePayrollRun($run);

        $employees = $this->repository->employees(true);
        if ($employees === []) {
            throw new \DomainException('پرسنل فعالی برای پردازش حقوق وجود ندارد.');
        }

        $attendanceByEmployee = [];
        foreach ($this->repository->attendanceFor($periodMonth) as $attendance) {
            $attendanceByEmployee[$attendance->employeeId()] = $attendance;
        }

        $totalGross = 0;
        $totalNet = 0;
        $slips = [];
        foreach ($employees as $employee) {
            $attendance = $attendanceByEmployee[$employee->id()] ?? null;

            $calc = $this->payrollCalculator->calculate(
                $employee->baseSalary(),
                $attendance !== null ? $attendance->overtimeHours() : 0.0,
                $attendance !== null ? $attendance->deductions() : 0,
            );

            $slip = Payslip::create(
                $run->id(),
                $employee->id(),
                $periodMonth,
                $calc['base'],
                $calc['overtime'],
                $calc['deductions'],
                $calc['tax'],
            );
            $this->repository->savePayslip($slip);
            $slips[] = $slip;

            $totalGross += $calc['gross'];
            $totalNet += $slip->net();
        }

        $run->process($totalGross, $totalNet, \count($slips), null, $userId ?? 0);

        // رویداد → سند هزینهٔ حقوق (listener) — شناسهٔ سند بعداً روی run می‌نشیند
        foreach ($run->pullEvents() as $event) {
            $this->outbox->store($event);
        }
        $this->outbox->flushNow();

        // پیدا کردن سند صادرشده (source: hr + run id) برای ارجاع
        $voucher = $this->accountingService->findVoucherForSource('hr', $run->id());
        if ($voucher !== null) {
            $run = PayrollRun::fromRow(\array_merge($this->rowOfRun($run), ['voucher_id' => $voucher->id()]));
        }

        $this->repository->savePayrollRun($run);

        $this->audit->record('payroll.processed', 'erp.payroll_run', $run->id(), [
            'period_month' => $periodMonth,
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'slips' => \count($slips),
            'voucher_id' => $run->voucherId(),
        ], $userId);

        return $run;
    }

    /**
     * پرداخت حقوق — سند: بدهکار حقوق پرداختنی / بستانکار بانک.
     */
    public function payPayroll(string $runId, string $method = 'bank', ?int $userId = null): PayrollRun
    {
        $run = $this->repository->findPayrollRun($runId);
        if ($run === null) {
            throw new \DomainException('دورهٔ حقوق یافت نشد: ' . $runId);
        }

        $accounts = Config::get('erp.accounting.accounts', []);
        $voucher = $this->accountingService->issue(
            sourceModule: 'hr',
            sourceId: $run->id() . ':pay',
            date: Clock::utcNow()->format('Y-m-d'),
            description: 'پرداخت حقوق ' . $run->periodMonth(),
            lineSpecs: [
                ['account_code' => (string) ($accounts['salary_payable'] ?? '2201'), 'debit' => $run->totalNet(), 'description' => 'پرداخت حقوق'],
                ['account_code' => (string) ($accounts[$method === 'cash' ? 'cash' : 'bank'] ?? '1102'), 'credit' => $run->totalNet(), 'description' => 'پرداخت حقوق ' . $run->periodMonth()],
            ],
            userId: $userId,
            autoPost: (bool) Config::get('erp.accounting.auto_post_system_vouchers', true),
        );

        $run->markPaid();
        $this->repository->savePayrollRun($run);

        $this->audit->record('payroll.paid', 'erp.payroll_run', $runId, [
            'period_month' => $run->periodMonth(),
            'total_net' => $run->totalNet(),
            'voucher_id' => $voucher->id(),
        ], $userId);

        return $run;
    }

    // ── پرسش ───────────────────────────────────────────────────────────────

    /** @return list<PayrollRun> */
    public function payrollRuns(int $limit = 50): array
    {
        return $this->repository->payrollRuns($limit);
    }

    public function findPayrollRun(string $id): ?PayrollRun
    {
        return $this->repository->findPayrollRun($id);
    }

    /** @return list<Payslip> */
    public function payslipsOf(string $runId): array
    {
        return $this->repository->payslipsOf($runId);
    }

    // ── خصوصی ──────────────────────────────────────────────────────────────

    private function mustFindEmployee(string $id): Employee
    {
        $employee = $this->repository->findEmployee($id);
        if ($employee === null) {
            throw new \DomainException('پرسنل یافت نشد: ' . $id);
        }

        return $employee;
    }

    /** @return array<string, mixed> */
    private function rowOfRun(PayrollRun $run): array
    {
        return $run->toRow();
    }
}
