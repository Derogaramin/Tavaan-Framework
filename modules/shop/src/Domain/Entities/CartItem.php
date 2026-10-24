<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CartItem — قلم سبد خرید (DOC-093 §3.1).
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Shop\Domain\ValueObjects\Money;

/**
 * @api 1.0
 */
final class CartItem
{
    public function __construct(
        private readonly string $id,
        private readonly string $productId,
        private readonly ?string $variantId,
        private int $quantity,
        private readonly Money $unitPrice,
        private readonly string $title = '',
        private readonly ?string $slug = null,
    ) {
    }

    public static function of(string $productId, ?string $variantId, int $quantity, Money $unitPrice, string $title = '', ?string $slug = null): self
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('quantity باید ≥ ۱ باشد.');
        }

        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            productId: $productId,
            variantId: $variantId,
            quantity: $quantity,
            unitPrice: $unitPrice,
            title: $title,
            slug: $slug,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, string $title = '', ?string $slug = null): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            productId: (string) ($row['product_id'] ?? ''),
            variantId: isset($row['variant_id']) && $row['variant_id'] !== null ? (string) $row['variant_id'] : null,
            quantity: (int) ($row['quantity'] ?? 1),
            unitPrice: Money::of((int) ($row['unit_price'] ?? 0)),
            title: $title,
            slug: $slug,
        );
    }

    public function setQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('quantity باید ≥ ۱ باشد.');
        }
        $this->quantity = $quantity;
    }

    /** نسخهٔ تازه با قیمت به‌روز (refresh هنگام checkout از کاتالوگ معتبر). */
    public function withUnitPrice(Money $price): self
    {
        return new self($this->id, $this->productId, $this->variantId, $this->quantity, $price, $this->title, $this->slug);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function variantId(): ?string
    {
        return $this->variantId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    /** کلید یکتای محصول+variant (برای ادغام در سبد). */
    public function mergeKey(): string
    {
        return $this->productId . '|' . ($this->variantId ?? '');
    }

    /** @return array<string, mixed> */
    public function toRow(string $cartId): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $cartId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->minor,
        ];
    }
}
