<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * HrRepositoryInterface — پورت بافتهٔ منابع انسانی.
 */

namespace Modules\Erp\Domain\Repositories;

use Modules\Erp\Domain\Entities\Attendance;
use Modules\Erp\Domain\Entities\Employee;
use Modules\Erp\Domain\Entities\PayrollRun;
use Modules\Erp\Domain\Entities\Payslip;

/**
 * @api 1.0
 */
interface HrRepositoryInterface
{
    /** @return list<Employee> فقط فعال‌ها مگر خلافش. */
    public function employees(bool $onlyActive = true): array;

    public function findEmployee(string $id): ?Employee;

    public function saveEmployee(Employee $employee): void;

    /** ثبت/به‌روزرسانی کارکرد ماهانه (upsert). */
    public function saveAttendance(Attendance $attendance): void;

    public function findAttendance(string $employeeId, string $periodMonth): ?Attendance;

    /** @return list<Attendance> همهٔ کارکردهای ماه (برای پردازش حقوق). */
    public function attendanceFor(string $periodMonth): array;

    public function findPayrollRun(string $id): ?PayrollRun;

    /** @return list<PayrollRun> */
    public function payrollRuns(int $limit = 50): array;

    public function savePayrollRun(PayrollRun $run): void;

    public function savePayslip(Payslip $payslip): void;

    /** @return list<Payslip> */
    public function payslipsOf(string $payrollRunId): array;
}
