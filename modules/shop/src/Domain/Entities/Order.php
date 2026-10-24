<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * Order — ریشهٔ Aggregate سفارش (DOC-093 §1.2/§4).
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Shop\Domain\Enums\OrderStatus;
use Modules\Shop\Domain\Events\OrderWasCanceled;
use Modules\Shop\Domain\Events\OrderWasPaid;
use Modules\Shop\Domain\Events\OrderWasPlaced;
use Modules\Shop\Domain\Events\OrderWasRefunded;
use Modules\Shop\Domain\Events\OrderWasShipped;
use Modules\Shop\Domain\ValueObjects\Address;
use Modules\Shop\Domain\ValueObjects\Money;

/**
 * Order — سفارش با breakdown مالی (subtotal/discount/tax/shipping/total).
 *
 * invariantها:
 * - total = subtotal - discount + tax + shipping (همه Money هم‌ارز)
 * - انتقال وضعیت فقط طبق ماتریس OrderStatus
 * - order_number یکتا (تولید در repository/use case)
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class Order
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var list<OrderItem> */
    private array $items;

    private function __construct(
        private readonly string $id,
        private string $orderNumber,
        private readonly int $customerId,
        private OrderStatus $status,
        private Money $subtotal,
        private Money $discount,
        private Money $tax,
        private Money $shippingCost,
        private readonly string $currency,
        private ?Address $shippingAddress,
        private ?Address $billingAddress,
        private ?string $paymentMethod,
        private ?string $paymentAuthority,
        private ?string $trackingCode,
        private ?string $notes,
        private ?\DateTimeImmutable $placedAt,
        private ?\DateTimeImmutable $paidAt,
        private ?\DateTimeImmutable $shippedAt,
        private ?\DateTimeImmutable $deliveredAt,
        private ?\DateTimeImmutable $canceledAt,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        array $items = [],
    ) {
        $this->items = $items;
    }

    /**
     * ساخت سفارش از سبد (DOC-093 §4.2 گام ۱).
     *
     * @param list<OrderItem> $items
     */
    public static function place(
        string $orderNumber,
        int $customerId,
        array $items,
        Address $shippingAddress,
        ?string $paymentMethod = null,
        ?string $notes = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if ($items === []) {
            throw new \DomainException('سفارش بدون آیتم ساخته نمی‌شود.');
        }
        if (!$shippingAddress->isComplete()) {
            throw new \DomainException('آدرس ارسال ناقص است.');
        }

        $currency = $items[0]->unitPrice()->currency;
        $subtotal = Money::zero($currency);
        foreach ($items as $item) {
            $subtotal = $subtotal->add($item->lineTotal());
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $order = new self(
            id: \Tavaan\Support\Ulid::generate($now)->toString(),
            orderNumber: $orderNumber,
            customerId: $customerId,
            status: OrderStatus::Pending,
            subtotal: $subtotal,
            discount: Money::zero($currency),
            tax: Money::zero($currency),
            shippingCost: Money::zero($currency),
            currency: $currency,
            shippingAddress: $shippingAddress,
            billingAddress: null,
            paymentMethod: $paymentMethod,
            paymentAuthority: null,
            trackingCode: null,
            notes: $notes,
            placedAt: $now,
            paidAt: null,
            shippedAt: null,
            deliveredAt: null,
            canceledAt: null,
            createdAt: $now,
            updatedAt: null,
            items: $items,
        );

        $order->recordedEvents[] = new OrderWasPlaced(
            orderId: $order->id,
            orderNumber: $orderNumber,
            customerId: $customerId,
            totalMinor: $order->total()->minor,
            currency: $currency,
        );

        return $order;
    }

    /**
     * بازسازی از ردیف دیتابیس.
     *
     * @param array<string, mixed> $row
     * @param list<OrderItem>      $items
     */
    public static function fromRow(array $row, array $items = []): self
    {
        $currency = (string) ($row['currency'] ?? 'IRR');

        return new self(
            id: (string) ($row['id'] ?? ''),
            orderNumber: (string) ($row['order_number'] ?? ''),
            customerId: (int) ($row['customer_id'] ?? 0),
            status: OrderStatus::tryFrom((string) ($row['status'] ?? 'pending')) ?? OrderStatus::Pending,
            subtotal: Money::of((int) ($row['subtotal'] ?? 0), $currency),
            discount: Money::of((int) ($row['discount'] ?? 0), $currency),
            tax: Money::of((int) ($row['tax'] ?? 0), $currency),
            shippingCost: Money::of((int) ($row['shipping_cost'] ?? 0), $currency),
            currency: $currency,
            shippingAddress: self::address($row['shipping_address'] ?? null),
            billingAddress: self::address($row['billing_address'] ?? null),
            paymentMethod: isset($row['payment_method']) ? (string) $row['payment_method'] : null,
            paymentAuthority: isset($row['payment_authority']) && $row['payment_authority'] !== null ? (string) $row['payment_authority'] : null,
            trackingCode: isset($row['tracking_code']) ? (string) $row['tracking_code'] : null,
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            placedAt: self::date($row['placed_at'] ?? null),
            paidAt: self::date($row['paid_at'] ?? null),
            shippedAt: self::date($row['shipped_at'] ?? null),
            deliveredAt: self::date($row['delivered_at'] ?? null),
            canceledAt: self::date($row['canceled_at'] ?? null),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            items: $items,
        );
    }

    // ─── محاسبات مالی (DOC-093 §4.2 گام ۲) ────────────────────────────────

    public function applyDiscount(Money $discount): void
    {
        $this->assertCurrency($discount);
        // تخفیف هرگز از subtotal بیشتر نمی‌شود
        $this->discount = $discount->min($this->subtotal);
    }

    public function applyTax(Money $tax): void
    {
        $this->assertCurrency($tax);
        $this->tax = $tax;
    }

    public function applyShipping(Money $cost): void
    {
        $this->assertCurrency($cost);
        $this->shippingCost = $cost;
    }

    /** total = subtotal - discount + tax + shipping (invariant). */
    public function total(): Money
    {
        return $this->subtotal
            ->subtract($this->discount)
            ->add($this->tax)
            ->add($this->shippingCost);
    }

    // ─── workflow ─────────────────────────────────────────────────────────

    /** پرداخت تأیید شد (DOC-093 §4.3). */
    public function markPaid(string $authority = '', ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(OrderStatus::Paid, $at);
        $this->paidAt = $at ?? $this->now();
        if ($authority !== '') {
            $this->paymentAuthority = $authority;
        }

        $this->recordedEvents[] = new OrderWasPaid(
            orderId: $this->id,
            orderNumber: $this->orderNumber,
            customerId: $this->customerId,
            totalMinor: $this->total()->minor,
            currency: $this->currency,
            authority: $this->paymentAuthority ?? '',
        );
    }

    public function ship(string $trackingCode = '', ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(OrderStatus::Shipped, $at);
        $this->shippedAt = $at ?? $this->now();
        if ($trackingCode !== '') {
            $this->trackingCode = $trackingCode;
        }

        $this->recordedEvents[] = new OrderWasShipped(
            orderId: $this->id,
            orderNumber: $this->orderNumber,
            customerId: $this->customerId,
            trackingCode: $this->trackingCode ?? '',
        );
    }

    public function deliver(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(OrderStatus::Delivered, $at);
        $this->deliveredAt = $at ?? $this->now();
    }

    /** لغو → آزادسازی موجودی توسط شنوندهٔ رویداد (DOC-093 §6). */
    public function cancel(string $reason = '', ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(OrderStatus::Canceled, $at);
        $this->canceledAt = $at ?? $this->now();

        $this->recordedEvents[] = new OrderWasCanceled(
            orderId: $this->id,
            orderNumber: $this->orderNumber,
            customerId: $this->customerId,
            wasPaid: $this->paidAt !== null,
            totalMinor: $this->total()->minor,
            currency: $this->currency,
            reason: $reason,
        );
    }

    public function refund(string $reason = '', ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(OrderStatus::Refunded, $at);

        $this->recordedEvents[] = new OrderWasRefunded(
            orderId: $this->id,
            orderNumber: $this->orderNumber,
            customerId: $this->customerId,
            totalMinor: $this->total()->minor,
            currency: $this->currency,
            reason: $reason,
        );
    }

    public function setPaymentAuthority(string $authority): void
    {
        $this->paymentAuthority = $authority;
        $this->touch();
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
    }

    // ─── Getters ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function orderNumber(): string
    {
        return $this->orderNumber;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function discount(): Money
    {
        return $this->discount;
    }

    public function tax(): Money
    {
        return $this->tax;
    }

    public function shippingCost(): Money
    {
        return $this->shippingCost;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function shippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function billingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function paymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function paymentAuthority(): ?string
    {
        return $this->paymentAuthority;
    }

    public function trackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function placedAt(): ?\DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function shippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function canceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPaid(): bool
    {
        return $this->paidAt !== null;
    }

    public function isCancelableByCustomer(): bool
    {
        return $this->status === OrderStatus::Pending || $this->status === OrderStatus::Paid;
    }

    /** @return list<OrderItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @param list<OrderItem> $items */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function itemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->quantity();
        }

        return $count;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber,
            'customer_id' => $this->customerId,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal->minor,
            'discount' => $this->discount->minor,
            'tax' => $this->tax->minor,
            'shipping_cost' => $this->shippingCost->minor,
            'total' => $this->total()->minor,
            'currency' => $this->currency,
            'shipping_address' => $this->shippingAddress === null ? null : (string) \json_encode($this->shippingAddress->toArray(), JSON_UNESCAPED_UNICODE),
            'billing_address' => $this->billingAddress === null ? null : (string) \json_encode($this->billingAddress->toArray(), JSON_UNESCAPED_UNICODE),
            'payment_method' => $this->paymentMethod,
            'payment_authority' => $this->paymentAuthority,
            'tracking_code' => $this->trackingCode,
            'notes' => $this->notes,
            'placed_at' => self::fmt($this->placedAt),
            'paid_at' => self::fmt($this->paidAt),
            'shipped_at' => self::fmt($this->shippedAt),
            'delivered_at' => self::fmt($this->deliveredAt),
            'canceled_at' => self::fmt($this->canceledAt),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(OrderStatus $target, ?\DateTimeImmutable $at = null): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت سفارش نامجاز: %s → %s (سفارش %s)',
                $this->status->value,
                $target->value,
                $this->orderNumber,
            ));
        }

        $this->status = $target;
        $this->touch($at);
    }

    private function assertCurrency(Money $money): void
    {
        if ($money->currency !== $this->currency) {
            throw new \InvalidArgumentException('ارز مبلغ با ارز سفارش یکسان نیست.');
        }
    }

    private function touch(?\DateTimeImmutable $at = null): void
    {
        $this->updatedAt = $at ?? $this->now();
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function address(mixed $value): ?Address
    {
        if (\is_string($value) && $value !== '') {
            $decoded = \json_decode($value, true);
            if (\is_array($decoded)) {
                return Address::fromArray($decoded);
            }
        }
        if (\is_array($value)) {
            return Address::fromArray($value);
        }

        return null;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (!\is_string($value) || \trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    private static function fmt(?\DateTimeImmutable $dt): ?string
    {
        return $dt?->format('Y-m-d H:i:s.v');
    }
}
