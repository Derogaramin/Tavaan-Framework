<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * Address — VO آدرس (JSON در shop_orders).
 */

namespace Modules\Shop\Domain\ValueObjects;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final readonly class Address
{
    public function __construct(
        public string $receiver,
        public string $phone,
        public string $province,
        public string $city,
        public string $address,
        public string $postalCode = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            receiver: \trim((string) ($data['receiver'] ?? '')),
            phone: \trim((string) ($data['phone'] ?? '')),
            province: \trim((string) ($data['province'] ?? '')),
            city: \trim((string) ($data['city'] ?? '')),
            address: \trim((string) ($data['address'] ?? '')),
            postalCode: \trim((string) ($data['postal_code'] ?? '')),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'receiver' => $this->receiver,
            'phone' => $this->phone,
            'province' => $this->province,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
        ];
    }

    /** حداقل فیلدهای لازم برای ارسال. */
    public function isComplete(): bool
    {
        return $this->receiver !== '' && $this->phone !== '' && $this->city !== '' && $this->address !== '';
    }
}
