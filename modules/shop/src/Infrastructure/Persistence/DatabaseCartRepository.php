<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * DatabaseCartRepository — آداپتر سبد (shop_carts + shop_cart_items).
 */

namespace Modules\Shop\Infrastructure\Persistence;

use Modules\Shop\Domain\Entities\Cart;
use Modules\Shop\Domain\Entities\CartItem;
use Modules\Shop\Domain\Repositories\CartRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class DatabaseCartRepository implements CartRepositoryInterface
{
    public function findActive(?int $userId, ?string $sessionId): ?Cart
    {
        $query = DB::table('shop_carts');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif ($sessionId !== null && $sessionId !== '') {
            $query->where('user_id', null)->where('session_id', $sessionId);
        } else {
            return null;
        }

        $row = $query->orderByDesc('created_at')->first();
        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findOrCreate(?int $userId, ?string $sessionId): Cart
    {
        $cart = $this->findActive($userId, $sessionId);
        if ($cart !== null) {
            return $cart;
        }

        $cart = Cart::create($userId, $sessionId, $this->defaultExpiry());
        $this->persistShell($cart);

        return $cart;
    }

    public function save(Cart $cart): void
    {
        $exists = DB::table('shop_carts')->where('id', $cart->id())->exists();
        if (!$exists) {
            $this->persistShell($cart);
        } else {
            DB::table('shop_carts')->where('id', $cart->id())->update([
                'coupon_code' => $cart->couponCode(),
                'total' => $cart->subtotal()->minor,
                'user_id' => $cart->userId(),
                'session_id' => $cart->sessionId(),
                'expires_at' => $cart->expiresAt()?->format('Y-m-d H:i:s.v'),
                'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
            ]);
        }

        // اقلام — جایگزینی کامل (سبد کوچک است؛ ساده و قطعی)
        DB::table('shop_cart_items')->where('cart_id', $cart->id())->delete();
        foreach ($cart->items() as $item) {
            DB::table('shop_cart_items')->insert($item->toRow($cart->id()));
        }
    }

    public function assignToUser(string $sessionId, int $userId): ?Cart
    {
        $guestCart = $this->findActive(null, $sessionId);
        if ($guestCart === null) {
            return null;
        }

        DB::table('shop_carts')->where('id', $guestCart->id())->update([
            'user_id' => $userId,
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);

        // ادغام با سبد موجود کاربر (در صورت وجود) — quantity جمع می‌شود
        $userCart = DB::table('shop_carts')
            ->where('user_id', $userId)
            ->where('id', '!=', $guestCart->id())
            ->orderByDesc('created_at')
            ->first();

        if ($userCart !== null) {
            $guestItems = DB::table('shop_cart_items')->where('cart_id', $guestCart->id())->get();
            $userItems = DB::table('shop_cart_items')->where('cart_id', $userCart['id'])->get();

            $byKey = [];
            foreach ($userItems as $item) {
                $byKey[(string) $item['product_id'] . '|' . (string) ($item['variant_id'] ?? '')] = $item;
            }
            foreach ($guestItems as $item) {
                $key = (string) $item['product_id'] . '|' . (string) ($item['variant_id'] ?? '');
                if (isset($byKey[$key])) {
                    DB::table('shop_cart_items')->where('id', $byKey[$key]['id'])->update([
                        'quantity' => (int) $byKey[$key]['quantity'] + (int) $item['quantity'],
                    ]);
                } else {
                    DB::table('shop_cart_items')->insert([
                        'id' => (string) $item['id'],
                        'cart_id' => (string) $userCart['id'],
                        'product_id' => (string) $item['product_id'],
                        'variant_id' => isset($item['variant_id']) ? $item['variant_id'] : null,
                        'quantity' => (int) $item['quantity'],
                        'unit_price' => (int) $item['unit_price'],
                    ]);
                }
            }

            // پاک‌سازی سبد مهمان
            DB::table('shop_cart_items')->where('cart_id', $guestCart->id())->delete();
            DB::table('shop_carts')->where('id', $guestCart->id())->delete();

            return $this->hydrate($userCart);
        }

        return $this->hydrate(DB::table('shop_carts')->where('id', $guestCart->id())->first() ?? []);
    }

    public function clear(Cart $cart): void
    {
        DB::table('shop_cart_items')->where('cart_id', $cart->id())->delete();
        DB::table('shop_carts')->where('id', $cart->id())->update([
            'coupon_code' => null,
            'total' => 0,
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Cart
    {
        $itemRows = DB::table('shop_cart_items')->where('cart_id', (string) $row['id'])->get();

        // عنوان/slug اقلام برای نمایش — یک کوئری دسته‌ای (بدون N+1)
        $productIds = \array_values(\array_unique(\array_map(static fn (array $i): string => (string) $i['product_id'], $itemRows)));
        $titles = [];
        if ($productIds !== []) {
            $products = DB::table('shop_products')->whereIn('id', $productIds)->get(['id', 'title', 'slug']);
            foreach ($products as $p) {
                $titles[(string) $p['id']] = ['title' => (string) $p['title'], 'slug' => (string) $p['slug']];
            }
        }

        $items = [];
        foreach ($itemRows as $itemRow) {
            $meta = $titles[(string) $itemRow['product_id']] ?? ['title' => '', 'slug' => null];
            $items[] = CartItem::fromRow($itemRow, $meta['title'], $meta['slug']);
        }

        $expiresAt = null;
        if (isset($row['expires_at']) && \is_string($row['expires_at']) && $row['expires_at'] !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($row['expires_at'], new \DateTimeZone('UTC'));
            } catch (\Exception) {
                $expiresAt = null;
            }
        }

        return Cart::rehydrate(
            id: (string) $row['id'],
            userId: isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            sessionId: isset($row['session_id']) ? (string) $row['session_id'] : null,
            couponCode: isset($row['coupon_code']) ? (string) $row['coupon_code'] : null,
            items: $items,
            expiresAt: $expiresAt,
        );
    }

    private function persistShell(Cart $cart): void
    {
        DB::table('shop_carts')->insert([
            'id' => $cart->id(),
            'user_id' => $cart->userId(),
            'session_id' => $cart->sessionId(),
            'coupon_code' => $cart->couponCode(),
            'total' => 0,
            'expires_at' => $cart->expiresAt()?->format('Y-m-d H:i:s.v'),
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function defaultExpiry(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('+14 days', new \DateTimeZone('UTC'));
    }
}
