<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductVariant — تنوع محصول (سایز/رنگ) با price_delta و موجودی مستقل.
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Shop\Domain\ValueObjects\Money;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class ProductVariant
{
    /** @param array<string, string> $options */
    private function __construct(
        private readonly string $id,
        private readonly string $productId,
        private ?string $sku,
        private string $title,
        private Money $priceDelta,
        private int $stock,
        private array $options,
    ) {
    }

    /** @param array<string, string> $options */
    public static function create(string $productId, string $title, Money $priceDelta, int $stock = 0, ?string $sku = null, array $options = []): self
    {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            productId: $productId,
            sku: $sku,
            title: $title,
            priceDelta: $priceDelta,
            stock: \max(0, $stock),
            options: $options,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $options = [];
        if (isset($row['options']) && \is_string($row['options']) && $row['options'] !== '') {
            $decoded = \json_decode($row['options'], true);
            $options = \is_array($decoded) ? $decoded : [];
        }

        return new self(
            id: (string) ($row['id'] ?? ''),
            productId: (string) ($row['product_id'] ?? ''),
            sku: isset($row['sku']) && $row['sku'] !== null ? (string) $row['sku'] : null,
            title: (string) ($row['title'] ?? ''),
            priceDelta: Money::of((int) ($row['price_delta'] ?? 0)),
            stock: (int) ($row['stock'] ?? 0),
            options: $options,
        );
    }

    /** قیمت نهایی variant = قیمت محصول + delta. */
    public function priceFor(Money $basePrice): Money
    {
        return $basePrice->add($this->priceDelta);
    }

    /** @internal توسط InventoryService پس از موفقیت DB */
    public function applyStockDelta(int $delta): void
    {
        $this->stock = \max(0, $this->stock + $delta);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function priceDelta(): Money
    {
        return $this->priceDelta;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return $this->options;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'sku' => $this->sku,
            'title' => $this->title,
            'price_delta' => $this->priceDelta->minor,
            'stock' => $this->stock,
            'options' => $this->options === [] ? null : (string) \json_encode($this->options, JSON_UNESCAPED_UNICODE),
        ];
    }
}
