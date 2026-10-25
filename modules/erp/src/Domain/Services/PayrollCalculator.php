<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PayrollCalculator — محاسبهٔ فیش حقوق (DOC-097 §2.5).
 */

namespace Modules\Erp\Domain\Services;

/**
 * PayrollCalculator — فرمول مستند:
 *
 * ```
 * hourly   = base / monthly_hours
 * overtime = round(hourly × overtime_rate × overtime_hours)
 * tax      = gross > threshold ? round((gross − threshold) × tax_rate) : 0
 * net      = base + overtime − deductions − tax   (همه int minor-unit)
 * ```
 *
 * 🔒 leave_days در v1 فقط ثبت می‌شود (کسر خودکار مرخصی = قوانین سازمانی —
 * از deductions دستی اعمال می‌شود). دامنهٔ خالص.
 *
 * @api 1.0
 */
final readonly class PayrollCalculator
{
    public function __construct(
        private float $overtimeRate = 1.4,
        private int $monthlyHours = 220,
        private float $taxRate = 0.0,
        private int $taxThreshold = 0,
    ) {
        if ($monthlyHours <= 0) {
            throw new \DomainException('ساعت کاری ماه باید مثبت باشد.');
        }
        if ($overtimeRate < 1.0) {
            throw new \DomainException('ضریب اضافه‌کاری باید ≥ ۱ باشد.');
        }
        if ($taxRate < 0 || $taxRate > 1) {
            throw new \DomainException('نرخ مالیات باید بین ۰ و ۱ باشد.');
        }
    }

    /** @return array{base: int, overtime: int, deductions: int, tax: int, net: int, gross: int} */
    public function calculate(int $baseSalary, float $overtimeHours, int $deductions): array
    {
        $baseSalary = \max(0, $baseSalary);
        $overtimeHours = \max(0.0, $overtimeHours);
        $deductions = \max(0, $deductions);

        $hourly = $baseSalary / $this->monthlyHours;
        $overtime = (int) \round($hourly * $this->overtimeRate * $overtimeHours);

        $gross = $baseSalary + $overtime;
        $tax = 0;
        if ($this->taxRate > 0 && $gross > $this->taxThreshold) {
            $tax = (int) \round(($gross - $this->taxThreshold) * $this->taxRate);
        }

        $net = $gross - $deductions - $tax;

        return [
            'base' => $baseSalary,
            'overtime' => $overtime,
            'deductions' => $deductions,
            'tax' => $tax,
            'net' => \max(0, $net),
            'gross' => $gross,
        ];
    }
}
