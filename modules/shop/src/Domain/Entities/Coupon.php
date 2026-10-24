<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * Coupon — کوپن تخفیف (DOC-093 §8: CouponService).
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Shop\Domain\ValueObjects\Money;

/**
 * Coupon — percent (۰..۱۰۰ با سقف max_discount) یا fixed (minor-unit).
 *
 * @api 1.0
 */
final class Coupon
{
    private function __construct(
        private readonly ?int $id,
        private string $code,
        private string $type,               // percent|fixed
        private int $value,
        private ?int $maxDiscount,
        private int $minCart,
        private ?int $usageLimit,
        private int $usedCount,
        private ?\DateTimeImmutable $expiresAt,
        private bool $isActive,
    ) {
    }

    public static function create(
        string $code,
        string $type,
        int $value,
        ?int $maxDiscount = null,
        int $minCart = 0,
        ?int $usageLimit = null,
        ?\DateTimeImmutable $expiresAt = null,
    ): self {
        if (!\in_array($type, ['percent', 'fixed'], true)) {
            throw new \InvalidArgumentException('نوع کوپن باید percent یا fixed باشد.');
        }
        if ($type === 'percent' && ($value < 1 || $value > 100)) {
            throw new \InvalidArgumentException('درصد کوپن باید بین ۱ تا ۱۰۰ باشد.');
        }
        if ($type === 'fixed' && $value < 1) {
            throw new \InvalidArgumentException('مبلغ کوپن باید مثبت باشد.');
        }

        return new self(
            id: null,
            code: \mb_strtoupper(\trim($code)),
            type: $type,
            value: $value,
            maxDiscount: $maxDiscount,
            minCart: \max(0, $minCart),
            usageLimit: $usageLimit,
            usedCount: 0,
            expiresAt: $expiresAt,
            isActive: true,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $expiresAt = null;
        if (isset($row['expires_at']) && \is_string($row['expires_at']) && $row['expires_at'] !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($row['expires_at'], new \DateTimeZone('UTC'));
            } catch (\Exception) {
                $expiresAt = null;
            }
        }

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            code: (string) ($row['code'] ?? ''),
            type: (string) ($row['type'] ?? 'percent'),
            value: (int) ($row['value'] ?? 0),
            maxDiscount: isset($row['max_discount']) && $row['max_discount'] !== null ? (int) $row['max_discount'] : null,
            minCart: (int) ($row['min_cart'] ?? 0),
            usageLimit: isset($row['usage_limit']) && $row['usage_limit'] !== null ? (int) $row['usage_limit'] : null,
            usedCount: (int) ($row['used_count'] ?? 0),
            expiresAt: $expiresAt,
            isActive: (bool) ($row['is_active'] ?? true),
        );
    }

    /** آیا کوپن روی این سبد معتبر است؟ (بدون مصرف‌شماری) */
    public function isValidFor(Money $subtotal, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->isActive) {
            return false;
        }
        if ($this->expiresAt !== null && ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) > $this->expiresAt) {
            return false;
        }
        if ($this->usageLimit !== null && $this->usedCount >= $this->usageLimit) {
            return false;
        }

        return $subtotal->minor >= $this->minCart;
    }

    /** محاسبهٔ تخفیف برای subtotal (گرد به پایین، با سقف). */
    public function discountFor(Money $subtotal): Money
    {
        $discount = $this->type === 'percent'
            ? $subtotal->percent((float) $this->value)
            : Money::of($this->value, $subtotal->currency);

        $discount = $discount->min($subtotal);   // هرگز بیش از جمع سبد

        if ($this->type === 'percent' && $this->maxDiscount !== null && $this->maxDiscount > 0) {
            $discount = $discount->min(Money::of($this->maxDiscount, $subtotal->currency));
        }

        return $discount;
    }

    public function markUsed(): void
    {
        $this->usedCount++;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function maxDiscount(): ?int
    {
        return $this->maxDiscount;
    }

    public function minCart(): int
    {
        return $this->minCart;
    }

    public function usageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function usedCount(): int
    {
        return $this->usedCount;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'max_discount' => $this->maxDiscount,
            'min_cart' => $this->minCart,
            'usage_limit' => $this->usageLimit,
            'used_count' => $this->usedCount,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s.v'),
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
