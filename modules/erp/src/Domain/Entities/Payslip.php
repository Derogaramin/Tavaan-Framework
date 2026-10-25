<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Payslip — فیش حقوق.
 */

namespace Modules\Erp\Domain\Entities;

/**
 * net = base + overtime − deductions − tax (int minor-unit).
 *
 * @api 1.0
 */
final class Payslip
{
    private function __construct(
        private readonly string $id,
        private readonly string $payrollRunId,
        private readonly string $employeeId,
        private readonly string $periodMonth,
        private readonly int $base,
        private readonly int $overtime,
        private readonly int $deductions,
        private readonly int $tax,
        private readonly int $net,
    ) {
    }

    public static function create(string $payrollRunId, string $employeeId, string $periodMonth, int $base, int $overtime, int $deductions, int $tax, ?\DateTimeImmutable $at = null): self
    {
        if ($base < 0 || $overtime < 0 || $deductions < 0 || $tax < 0) {
            throw new \DomainException('اجزای فیش حقوق نمی‌توانند منفی باشند.');
        }

        $net = $base + $overtime - $deductions - $tax;
        if ($net < 0) {
            throw new \DomainException('خالص فیش منفی شد — کسورات/مالیات بیش از درآمد است.');
        }

        return new self(\Tavaan\Support\Ulid::generate($at)->toString(), $payrollRunId, $employeeId, $periodMonth, $base, $overtime, $deductions, $tax, $net);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['payroll_run_id'] ?? ''),
            (string) ($row['employee_id'] ?? ''),
            (string) ($row['period_month'] ?? ''),
            (int) ($row['base'] ?? 0),
            (int) ($row['overtime'] ?? 0),
            (int) ($row['deductions'] ?? 0),
            (int) ($row['tax'] ?? 0),
            (int) ($row['net'] ?? 0),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function payrollRunId(): string
    {
        return $this->payrollRunId;
    }

    public function employeeId(): string
    {
        return $this->employeeId;
    }

    public function periodMonth(): string
    {
        return $this->periodMonth;
    }

    public function base(): int
    {
        return $this->base;
    }

    public function overtime(): int
    {
        return $this->overtime;
    }

    public function deductions(): int
    {
        return $this->deductions;
    }

    public function tax(): int
    {
        return $this->tax;
    }

    public function net(): int
    {
        return $this->net;
    }

    public function gross(): int
    {
        return $this->base + $this->overtime;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payrollRunId,
            'employee_id' => $this->employeeId,
            'period_month' => $this->periodMonth,
            'base' => $this->base,
            'overtime' => $this->overtime,
            'deductions' => $this->deductions,
            'tax' => $this->tax,
            'net' => $this->net,
        ];
    }
}
