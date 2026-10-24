<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderItem — قلم سفارش با snapshot عنوان/قیمت (DOC-093 §3.1).
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Shop\Domain\ValueObjects\Money;

/**
 * OrderItem — تغییرناپذیر؛ snapshot محصول در لحظهٔ سفارش (عنوان/sku/قیمت)
 * تا تغییرات بعدی کاتالوگ روی سفارش ثبت‌شده اثر نگذارد.
 *
 * @api 1.0
 */
final readonly class OrderItem
{
    public function __construct(
        private string $id,
        public string $productId,
        public ?string $variantId,
        public string $title,
        public ?string $sku,
        public int $quantity,
        private Money $unitPrice,
        private Money $discount,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('quantity باید ≥ ۱ باشد.');
        }
    }

    public static function of(
        string $productId,
        ?string $variantId,
        string $title,
        ?string $sku,
        int $quantity,
        Money $unitPrice,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            productId: $productId,
            variantId: $variantId,
            title: $title,
            sku: $sku,
            quantity: $quantity,
            unitPrice: $unitPrice,
            discount: Money::zero($unitPrice->currency),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $currency = 'IRR';

        return new self(
            id: (string) ($row['id'] ?? ''),
            productId: (string) ($row['product_id'] ?? ''),
            variantId: isset($row['variant_id']) && $row['variant_id'] !== null ? (string) $row['variant_id'] : null,
            title: (string) ($row['title'] ?? ''),
            sku: isset($row['sku']) ? (string) $row['sku'] : null,
            quantity: (int) ($row['quantity'] ?? 1),
            unitPrice: Money::of((int) ($row['unit_price'] ?? 0), $currency),
            discount: Money::of((int) ($row['discount'] ?? 0), $currency),
        );
    }

    public function withDiscount(Money $discount): self
    {
        return new self(
            id: $this->id,
            productId: $this->productId,
            variantId: $this->variantId,
            title: $this->title,
            sku: $this->sku,
            quantity: $this->quantity,
            unitPrice: $this->unitPrice,
            discount: $discount,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function discount(): Money
    {
        return $this->discount;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    /** جمع قلم = unit × qty - discount. */
    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity)->subtract($this->discount);
    }

    /** @return array<string, mixed> */
    public function toRow(string $orderId): array
    {
        return [
            'id' => $this->id,
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'title' => $this->title,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->minor,
            'discount' => $this->discount->minor,
            'total' => $this->lineTotal()->minor,
        ];
    }
}
