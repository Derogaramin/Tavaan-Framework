<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * Bom — ساختار محصول (Bill of Materials — DOC-097 §2.4).
 */

namespace Modules\Erp\Domain\Entities;

/**
 * Bom — components: [{item_id, qty}] برای یک محصول (نسخه‌دار).
 *
 * @api 1.0
 */
final class Bom
{
    /** @var list<array{item_id: string, qty: float}> */
    private array $components;

    private function __construct(
        private readonly string $id,
        private readonly string $productId,
        private readonly int $version,
        array $components,
        private bool $isActive,
    ) {
        $this->components = $components;
    }

    /** @param list<array{item_id: string, qty: float}> $components */
    public static function create(string $productId, array $components, int $version = 1, ?\DateTimeImmutable $at = null): self
    {
        if ($components === []) {
            throw new \DomainException('BOM بدون جزء ساخته نمی‌شود.');
        }

        $normalized = [];
        foreach ($components as $component) {
            $itemId = (string) ($component['item_id'] ?? '');
            $qty = (float) ($component['qty'] ?? 0);
            if ($itemId === '' || $qty <= 0) {
                throw new \DomainException('جزء BOM باید item_id و qty مثبت داشته باشد.');
            }
            if ($itemId === $productId) {
                throw new \DomainException('جزء BOM نمی‌تواند خود محصول باشد (چرخهٔ بدیهی).');
            }
            $normalized[] = ['item_id' => $itemId, 'qty' => \round($qty, 3)];
        }

        return new self(\Tavaan\Support\Ulid::generate($at)->toString(), $productId, \max(1, $version), $normalized, true);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $components = [];
        $raw = $row['components'] ?? null;
        if (\is_string($raw) && $raw !== '') {
            $decoded = \json_decode($raw, true);
            $components = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($raw)) {
            $components = $raw;
        }

        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['product_id'] ?? ''),
            (int) ($row['version'] ?? 1),
            $components,
            (bool) ($row['is_active'] ?? true),
        );
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /** مواد لازم برای qty مشخص محصول. @return list<array{item_id: string, qty: float}> */
    public function explodeFor(float $productQty): array
    {
        $out = [];
        foreach ($this->components as $component) {
            $out[] = [
                'item_id' => (string) $component['item_id'],
                'qty' => \round((float) $component['qty'] * $productQty, 3),
            ];
        }

        return $out;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return list<array{item_id: string, qty: float}> */
    public function components(): array
    {
        return $this->components;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'version' => $this->version,
            'components' => (string) \json_encode($this->components, JSON_UNESCAPED_UNICODE),
            'is_active' => $this->isActive,
            'created_at' => $now,
            'updated_at' => null,
        ];
    }
}
