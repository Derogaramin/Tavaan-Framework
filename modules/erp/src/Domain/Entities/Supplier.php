<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Supplier — تأمین‌کننده.
 */

namespace Modules\Erp\Domain\Entities;

/**
 * @api 1.0
 */
final class Supplier
{
    private function __construct(
        private readonly string $id,
        private readonly string $code,
        private string $name,
        private ?string $phone,
        private ?string $email,
        private ?string $address,
    ) {
    }

    public static function create(string $code, string $name, ?string $phone = null, ?string $email = null, ?string $address = null, ?\DateTimeImmutable $at = null): self
    {
        if (\trim($code) === '' || \trim($name) === '') {
            throw new \DomainException('کد و نام تأمین‌کننده الزامی است.');
        }

        return new self(\Tavaan\Support\Ulid::generate($at)->toString(), \trim($code), \trim($name), $phone, $email, $address);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['code'] ?? ''),
            (string) ($row['name'] ?? ''),
            isset($row['phone']) ? (string) $row['phone'] : null,
            isset($row['email']) ? (string) $row['email'] : null,
            isset($row['address']) ? (string) $row['address'] : null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'phone' => $this->phone, 'email' => $this->email, 'address' => $this->address,
            'created_at' => $now, 'updated_at' => null,
        ];
    }
}
