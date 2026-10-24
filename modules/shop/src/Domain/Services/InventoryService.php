<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * InventoryService — reserve/release/adjust با گارد همزمانی (DOC-093 §9).
 */

namespace Modules\Shop\Domain\Services;

use Modules\Shop\Domain\Entities\OrderItem;
use Modules\Shop\Domain\Events\StockWasLow;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Event;

/**
 * InventoryService — موجودی race-safe.
 *
 * 🔒 همزمانی (AC-SHOP-03): کاهش موجودی با **UPDATE اتمیک شرطی** انجام
 * می‌شود (`SET stock = stock - q WHERE stock >= q`) و از affected-rows
 * موفقیت سنجیده می‌شود — بدون read-modify-write و بدون نیاز به FOR UPDATE
 * (روی SQLite/MySQL/PG یکسان کار می‌کند). E2E-08 این گارد را می‌سنجد.
 *
 * هر حرکت در `shop_stock_movements` ثبت می‌شود (audit trail).
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class InventoryService
{
    /**
     * رزرو موجودی برای اقلام سفارش — همه‌یا‑هیچ.
     *
     * @param list<OrderItem> $items
     *
     * @throws InventoryException اگر موجودی قلمی نرسد (اقلام قبلی rollback می‌شوند)
     */
    public function reserve(array $items, ?string $orderId = null): void
    {
        $reserved = [];

        try {
            foreach ($items as $item) {
                $this->decrement($item->productId, $item->variantId, $item->quantity, 'reserve', $orderId);
                $reserved[] = $item;
            }
        } catch (InventoryException $e) {
            // rollback اقلام رزروشدهٔ پیشین (همه‌یا‌هیچ)
            foreach ($reserved as $done) {
                $this->increment($done->productId, $done->variantId, $done->quantity, 'release', $orderId);
            }

            throw $e;
        }
    }

    /**
     * آزادسازی موجودی (لغو سفارش — DOC-093 §6).
     *
     * @param list<OrderItem> $items
     */
    public function release(array $items, ?string $orderId = null): void
    {
        foreach ($items as $item) {
            $this->increment($item->productId, $item->variantId, $item->quantity, 'release', $orderId);
        }
    }

    /** تنظیم دستی موجودی (inventory-keeper) — با دلیل/یادداشت. */
    public function adjust(string $productId, ?string $variantId, int $delta, string $note = ''): int
    {
        if ($delta < 0) {
            $this->decrement($productId, $variantId, -$delta, 'adjust', null, $note);
        } elseif ($delta > 0) {
            $this->increment($productId, $variantId, $delta, 'adjust', null, $note);
        }

        return $this->stockOf($productId, $variantId);
    }

    /** موجودی فعلی (محصول یا variant). */
    public function stockOf(string $productId, ?string $variantId = null): int
    {
        $row = $variantId !== null
            ? DB::table('shop_product_variants')->where('id', $variantId)->where('product_id', $productId)->first(['stock'])
            : DB::table('shop_products')->where('id', $productId)->first(['stock']);

        return $row === null ? 0 : (int) $row['stock'];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function decrement(string $productId, ?string $variantId, int $quantity, string $reason, ?string $orderId, string $note = ''): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('quantity باید ≥ ۱ باشد.');
        }

        // 🔒 UPDATE اتمیک شرطی — گارد همزمانی (AC-SHOP-03)
        $affected = $variantId !== null
            ? DB::table('shop_product_variants')
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->where('stock', '>=', $quantity)
                ->update(['stock' => DB::raw('stock - ' . (int) $quantity)])
            : DB::table('shop_products')
                ->where('id', $productId)
                ->whereNull('deleted_at')
                ->where('stock', '>=', $quantity)
                ->update(['stock' => DB::raw('stock - ' . (int) $quantity)]);

        if ($affected < 1) {
            $available = $this->stockOf($productId, $variantId);
            $title = $this->titleOf($productId);

            throw InventoryException::outOfStock($productId, $title, $quantity, $available);
        }

        $after = $this->stockOf($productId, $variantId);
        $this->logMovement($productId, $variantId, -$quantity, $after, $reason, $orderId, $note);

        if ($variantId === null) {
            $this->emitLowStockIfAny($productId, $after);
        }
    }

    private function increment(string $productId, ?string $variantId, int $quantity, string $reason, ?string $orderId, string $note = ''): void
    {
        if ($quantity < 1) {
            return;
        }

        $affected = $variantId !== null
            ? DB::table('shop_product_variants')
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->update(['stock' => DB::raw('stock + ' . (int) $quantity)])
            : DB::table('shop_products')
                ->where('id', $productId)
                ->update(['stock' => DB::raw('stock + ' . (int) $quantity)]);

        if ($affected < 1) {
            return;   // محصول حذف‌شده — حرکت بی‌اثر
        }

        $after = $this->stockOf($productId, $variantId);
        $this->logMovement($productId, $variantId, $quantity, $after, $reason, $orderId, $note);
    }

    private function logMovement(string $productId, ?string $variantId, int $delta, int $stockAfter, string $reason, ?string $orderId, string $note): void
    {
        DB::table('shop_stock_movements')->insert([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'delta' => $delta,
            'stock_after' => $stockAfter,
            'reason' => $reason,
            'order_id' => $orderId,
            'note' => $note !== '' ? $note : null,
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }

    /** رویداد shop.stock.low (DOC-093 §6) — یک‌بار در عبور از آستانه. */
    private function emitLowStockIfAny(string $productId, int $stockAfter): void
    {
        $row = DB::table('shop_products')->where('id', $productId)->first(['title', 'low_stock_threshold']);
        if ($row === null) {
            return;
        }

        $threshold = (int) ($row['low_stock_threshold'] ?? 5);
        if ($stockAfter > 0 && $stockAfter <= $threshold) {
            $event = new StockWasLow(
                productId: $productId,
                title: (string) ($row['title'] ?? ''),
                stock: $stockAfter,
                threshold: $threshold,
            );
            Event::dispatch($event->eventName(), $event->toPayload());
        }
    }

    private function titleOf(string $productId): string
    {
        $row = DB::table('shop_products')->where('id', $productId)->first(['title']);

        return (string) ($row['title'] ?? $productId);
    }
}
