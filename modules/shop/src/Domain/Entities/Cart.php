<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * Cart — Aggregate سبد خرید (DOC-093 §1.2/§4.1).
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Shop\Domain\Events\CartWasUpdated;
use Modules\Shop\Domain\ValueObjects\Money;

/**
 * Cart — سبد مهمان (session_id) یا کاربر (user_id).
 *
 * افزودن قلم تکراری → ادغام quantity. قیمت‌ها snapshot لحظهٔ افزودن‌اند؛
 * در checkout با کاتالوگ معتبر refresh می‌شوند (CartService).
 *
 * @api 1.0
 */
final class Cart
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var array<string, CartItem> کلید = mergeKey */
    private array $items = [];

    private function __construct(
        private readonly string $id,
        private readonly ?int $userId,
        private ?string $sessionId,
        private ?string $couponCode,
        private ?\DateTimeImmutable $expiresAt,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(?int $userId = null, ?string $sessionId = null, ?\DateTimeImmutable $expiresAt = null): self
    {
        if ($userId === null && ($sessionId === null || $sessionId === '')) {
            throw new \InvalidArgumentException('سبد به user_id یا session_id نیاز دارد.');
        }

        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            userId: $userId,
            sessionId: $sessionId,
            couponCode: null,
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
        );
    }

    /** @param list<CartItem> $items */
    public static function rehydrate(string $id, ?int $userId, ?string $sessionId, ?string $couponCode, array $items, ?\DateTimeImmutable $expiresAt = null, ?\DateTimeImmutable $createdAt = null): self
    {
        $cart = new self(
            id: $id,
            userId: $userId,
            sessionId: $sessionId,
            couponCode: $couponCode,
            expiresAt: $expiresAt,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
        );
        foreach ($items as $item) {
            $cart->items[$item->mergeKey()] = $item;
        }

        return $cart;
    }

    /** افزودن قلم (ادغام در صورت تکرار) — DOC-093 §4.1. */
    public function addItem(CartItem $item): void
    {
        $key = $item->mergeKey();
        if (isset($this->items[$key])) {
            // ادغام quantity + تازه‌سازی قیمت با آخرین افزودن
            $existing = $this->items[$key];
            $merged = $existing->withUnitPrice($item->unitPrice());
            $merged->setQuantity($existing->quantity() + $item->quantity());
            $this->items[$key] = $merged;
        } else {
            $this->items[$key] = $item;
        }

        $this->touch();
        $this->recordCartEvent('add');
    }

    public function updateQuantity(string $itemId, int $quantity): bool
    {
        foreach ($this->items as $key => $item) {
            if ($item->id() === $itemId) {
                if ($quantity < 1) {
                    unset($this->items[$key]);
                } else {
                    $item->setQuantity($quantity);
                }
                $this->touch();
                $this->recordCartEvent('update');

                return true;
            }
        }

        return false;
    }

    public function removeItem(string $itemId): bool
    {
        foreach ($this->items as $key => $item) {
            if ($item->id() === $itemId) {
                unset($this->items[$key]);
                $this->touch();
                $this->recordCartEvent('remove');

                return true;
            }
        }

        return false;
    }

    public function applyCoupon(?string $code): void
    {
        $this->couponCode = $code !== null && $code !== '' ? $code : null;
        $this->touch();
        $this->recordCartEvent('coupon');
    }

    public function clear(): void
    {
        $this->items = [];
        $this->couponCode = null;
        $this->touch();
        $this->recordCartEvent('clear');
    }

    /** جایگزینی قیمت‌ها با نرخ معتبر کاتالوگ (checkout). */
    public function refreshPrice(string $productId, ?string $variantId, Money $price): void
    {
        $key = $productId . '|' . ($variantId ?? '');
        if (isset($this->items[$key])) {
            $this->items[$key] = $this->items[$key]->withUnitPrice($price);
        }
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function totalQuantity(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += $item->quantity();
        }

        return $sum;
    }

    /** جمع اقلام (بدون تخفیف/مالیات/ارسال). */
    public function subtotal(string $currency = 'IRR'): Money
    {
        $total = Money::zero($currency);
        foreach ($this->items as $item) {
            $total = $total->add($item->lineTotal());
        }

        return $total;
    }

    /** @return list<CartItem> */
    public function items(): array
    {
        return \array_values($this->items);
    }

    public function findItem(string $itemId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->id() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function couponCode(): ?string
    {
        return $this->couponCode;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** مالکیت سبد برای این کاربر؟ */
    public function belongsTo(?int $userId, ?string $sessionId): bool
    {
        if ($this->userId !== null) {
            return $userId !== null && $this->userId === $userId;
        }

        return $sessionId !== null && $this->sessionId === $sessionId;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function recordCartEvent(string $action): void
    {
        $this->recordedEvents[] = new CartWasUpdated(
            cartId: $this->id,
            userId: $this->userId,
            action: $action,
            itemCount: $this->count(),
            subtotalMinor: $this->subtotal()->minor,
        );
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
