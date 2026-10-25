<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * DatabaseHrRepository — پایداری بافتهٔ منابع انسانی.
 */

namespace Modules\Erp\Infrastructure\Persistence;

use Modules\Erp\Domain\Entities\Attendance;
use Modules\Erp\Domain\Entities\Employee;
use Modules\Erp\Domain\Entities\PayrollRun;
use Modules\Erp\Domain\Entities\Payslip;
use Modules\Erp\Domain\Repositories\HrRepositoryInterface;
use Tavaan\Core\Facade\Facades\Crypt;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;

/**
 * DatabaseHrRepository — 🔒 national_id رمزنگاری‌شده در DB (fail-safe:
 * اگر رمزنگاری در دسترس نبود، مقدار ذخیره نمی‌شود — الگوی CRM).
 *
 * @internal
 */
final class DatabaseHrRepository implements HrRepositoryInterface
{
    private const CIPHER_CONTEXT = 'erp.employee.national_id';

    public function employees(bool $onlyActive = true): array
    {
        $query = DB::table('erp_employees');
        if ($onlyActive) {
            $query->where('status', '=', 'active');
        }

        $rows = $query->orderBy('employee_code')->get();

        return \array_map(fn (array $row): Employee => Employee::fromRow($row, $this->decryptNationalId($row['national_id'] ?? null)), $rows);
    }

    public function findEmployee(string $id): ?Employee
    {
        $row = DB::table('erp_employees')->where('id', '=', $id)->first();

        return $row !== null ? Employee::fromRow($row, $this->decryptNationalId($row['national_id'] ?? null)) : null;
    }

    public function saveEmployee(Employee $employee): void
    {
        $exists = DB::table('erp_employees')->where('id', '=', $employee->id())->count() > 0;
        $row = $employee->toRow();
        $row['national_id'] = $this->encryptNationalId($employee->nationalId());

        if ($exists) {
            unset($row['id'], $row['created_at']);
            $row['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.v');
            DB::table('erp_employees')->where('id', '=', $employee->id())->update($row);

            return;
        }

        DB::table('erp_employees')->insert($row);
    }

    public function saveAttendance(Attendance $attendance): void
    {
        $row = $attendance->toRow();

        // upsert با کلید یکتا (employee_id, period_month)
        DB::table('erp_attendance')->upsert([$row], ['employee_id', 'period_month'], ['work_hours', 'overtime_hours', 'leave_days', 'deductions']);
    }

    public function findAttendance(string $employeeId, string $periodMonth): ?Attendance
    {
        $row = DB::table('erp_attendance')
            ->where('employee_id', '=', $employeeId)
            ->where('period_month', '=', $periodMonth)
            ->first();

        return $row !== null ? Attendance::fromRow($row) : null;
    }

    public function attendanceFor(string $periodMonth): array
    {
        $rows = DB::table('erp_attendance')->where('period_month', '=', $periodMonth)->get();

        return \array_map(static fn (array $row): Attendance => Attendance::fromRow($row), $rows);
    }

    public function findPayrollRun(string $id): ?PayrollRun
    {
        $row = DB::table('erp_payroll_runs')->where('id', '=', $id)->first();

        return $row !== null ? PayrollRun::fromRow($row) : null;
    }

    public function payrollRuns(int $limit = 50): array
    {
        $rows = DB::table('erp_payroll_runs')->orderByDesc('created_at')->limit($limit)->get();

        return \array_map(static fn (array $row): PayrollRun => PayrollRun::fromRow($row), $rows);
    }

    public function savePayrollRun(PayrollRun $run): void
    {
        $exists = DB::table('erp_payroll_runs')->where('id', '=', $run->id())->count() > 0;
        $row = $run->toRow();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('erp_payroll_runs')->where('id', '=', $run->id())->update($row);

            return;
        }

        DB::table('erp_payroll_runs')->insert($row);
    }

    public function savePayslip(Payslip $payslip): void
    {
        DB::table('erp_payslips')->insert($payslip->toRow());
    }

    public function payslipsOf(string $payrollRunId): array
    {
        $rows = DB::table('erp_payslips')->where('payroll_run_id', '=', $payrollRunId)->get();

        return \array_map(static fn (array $row): Payslip => Payslip::fromRow($row), $rows);
    }

    // ── رمزنگاری national_id (fail-safe — الگوی CRM) ───────────────────────

    private function encryptNationalId(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        try {
            return Crypt::encrypt($plain, self::CIPHER_CONTEXT);
        } catch (\Throwable) {
            // اگر رمزنگاری در دسترس نیست — دادهٔ حساس خام ذخیره نمی‌شود
            return null;
        }
    }

    private function decryptNationalId(mixed $encrypted): ?string
    {
        if (!\is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $plain = Crypt::decrypt($encrypted, self::CIPHER_CONTEXT);

            return \is_string($plain) && $plain !== '' ? $plain : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
