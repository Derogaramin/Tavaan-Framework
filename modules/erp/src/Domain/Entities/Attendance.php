<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Attendance — کارکرد ماهانهٔ پرسنل (DOC-097 §2.5).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * @api 1.0
 */
final class Attendance
{
    private function __construct(
        private readonly int $id,
        private readonly string $employeeId,
        private readonly string $periodMonth,     // YYYY-MM
        private float $workHours,
        private float $overtimeHours,
        private float $leaveDays,
        private int $deductions,
    ) {
    }

    public static function record(string $employeeId, string $periodMonth, float $workHours = 0.0, float $overtimeHours = 0.0, float $leaveDays = 0.0, int $deductions = 0): self
    {
        if (!\preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodMonth)) {
            throw new \DomainException('دورهٔ کارکرد باید YYYY-MM باشد.');
        }
        if ($workHours < 0 || $overtimeHours < 0 || $leaveDays < 0 || $deductions < 0) {
            throw new \DomainException('مقادیر کارکرد نمی‌توانند منفی باشند.');
        }

        return new self(0, $employeeId, $periodMonth, $workHours, $overtimeHours, $leaveDays, $deductions);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['employee_id'] ?? ''),
            (string) ($row['period_month'] ?? ''),
            (float) ($row['work_hours'] ?? 0),
            (float) ($row['overtime_hours'] ?? 0),
            (float) ($row['leave_days'] ?? 0),
            (int) ($row['deductions'] ?? 0),
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function employeeId(): string
    {
        return $this->employeeId;
    }

    public function periodMonth(): string
    {
        return $this->periodMonth;
    }

    public function workHours(): float
    {
        return $this->workHours;
    }

    public function overtimeHours(): float
    {
        return $this->overtimeHours;
    }

    public function leaveDays(): float
    {
        return $this->leaveDays;
    }

    public function deductions(): int
    {
        return $this->deductions;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'period_month' => $this->periodMonth,
            'work_hours' => $this->workHours,
            'overtime_hours' => $this->overtimeHours,
            'leave_days' => $this->leaveDays,
            'deductions' => $this->deductions,
        ];
    }
}
