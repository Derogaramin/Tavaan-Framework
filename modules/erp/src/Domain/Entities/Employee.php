<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Employee — پرسنل (DOC-097 §2.5).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * Employee — national_id در DB **رمزنگاری‌شده** (الگوی CRM).
 *
 * @api 1.0
 */
final class Employee
{
    private function __construct(
        private readonly string $id,
        private readonly ?int $userId,
        private readonly string $employeeCode,
        private string $name,
        private ?string $position,
        private int $baseSalary,
        private readonly ?string $nationalIdPlain,   // در حافظه plain — در DB رمز
        private ?string $hireDate,
        private string $status,                      // active|terminated
    ) {
    }

    public static function create(
        string $employeeCode,
        string $name,
        int $baseSalary,
        ?string $position = null,
        ?string $nationalId = null,
        ?string $hireDate = null,
        ?int $userId = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($employeeCode) === '' || \trim($name) === '') {
            throw new \DomainException('کد پرسنلی و نام الزامی است.');
        }
        if ($baseSalary < 0) {
            throw new \DomainException('حقوق پایه نمی‌تواند منفی باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $userId,
            \trim($employeeCode),
            \trim($name),
            $position !== null && \trim($position) !== '' ? \trim($position) : null,
            $baseSalary,
            $nationalId !== null && \trim($nationalId) !== '' ? \trim($nationalId) : null,
            $hireDate,
            'active',
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, ?string $decryptedNationalId = null): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            (string) ($row['employee_code'] ?? ''),
            (string) ($row['name'] ?? ''),
            isset($row['position']) && $row['position'] !== null ? (string) $row['position'] : null,
            (int) ($row['base_salary'] ?? 0),
            $decryptedNationalId,
            isset($row['hire_date']) && $row['hire_date'] !== null ? (string) $row['hire_date'] : null,
            (string) ($row['status'] ?? 'active'),
        );
    }

    public function revise(?string $name = null, ?string $position = null, ?int $baseSalary = null): void
    {
        $this->name = $name !== null && \trim($name) !== '' ? \trim($name) : $this->name;
        $this->position = $position !== null ? (\trim($position) !== '' ? \trim($position) : null) : $this->position;
        $this->baseSalary = $baseSalary === null ? $this->baseSalary : \max(0, $baseSalary);
    }

    public function terminate(): void
    {
        $this->status = 'terminated';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function employeeCode(): string
    {
        return $this->employeeCode;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): ?string
    {
        return $this->position;
    }

    public function baseSalary(): int
    {
        return $this->baseSalary;
    }

    public function nationalId(): ?string
    {
        return $this->nationalIdPlain;
    }

    public function hireDate(): ?string
    {
        return $this->hireDate;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'employee_code' => $this->employeeCode,
            'name' => $this->name,
            'position' => $this->position,
            'base_salary' => $this->baseSalary,
            // 🔒 national_id توسط لایهٔ زیرساخت رمز می‌شود (persist)
            'national_id' => $this->nationalIdPlain,
            'hire_date' => $this->hireDate,
            'status' => $this->status,
            'created_at' => $now,
            'updated_at' => null,
        ];
    }
}
