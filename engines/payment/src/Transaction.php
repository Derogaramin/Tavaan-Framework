<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Support\Money;

/**
 * تَوان | Tavaan — Payment
 * Transaction — رکورد تراکنش پرداخت (جدول tvn_payment_transactions — DOC-073 §6).
 *
 * 🔒 amount/currency محلی نگه داشته می‌شود؛ verify همیشه با همین مبلغ است
 * (محافظ Amount tampering).
 * 🔒 card_pan فقط mask‌شده ذخیره می‌شود (۶ اول + ۴ آخر).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class Transaction extends Model
{
    protected string $table = 'tvn_payment_transactions';

    protected string $keyType = 'ulid';

    public bool $incrementing = false;

    public bool $timestamps = false;   // فقط created_at/paid_at (بدون updated_at)

    /** @var list<string> */
    protected array $fillable = [
        'gateway', 'order_type', 'order_id', 'amount', 'currency',
        'authority', 'reference_id', 'status', 'card_pan',
        'callback_payload', 'idempotency_key', 'paid_at', 'created_at',
    ];

    /** @var array<string, string> */
    protected array $casts = [
        'amount' => 'int',
        'callback_payload' => 'array',
    ];

    /** تراکنش بر اساس authority درگاه. */
    public static function byAuthority(string $gateway, string $authority): ?self
    {
        /** @var self|null */
        return static::query()
            ->where('gateway', $gateway)
            ->where('authority', $authority)
            ->orderByDesc('created_at')
            ->first();
    }

    /** تراکنش بر اساس کلید idempotency. */
    public static function byIdempotencyKey(string $key): ?self
    {
        /** @var self|null */
        return static::query()->where('idempotency_key', $key)->first();
    }

    public function gateway(): string
    {
        return (string) $this->getAttribute('gateway');
    }

    public function authority(): ?string
    {
        $authority = $this->getAttribute('authority');

        return $authority === null || $authority === '' ? null : (string) $authority;
    }

    public function status(): string
    {
        return (string) $this->getAttribute('status');
    }

    public function statusEnum(): PaymentStatus
    {
        return PaymentStatus::tryFrom($this->status()) ?? PaymentStatus::Pending;
    }

    /** 💰 مبلغ تراکنش به‌صورت Money (ADR-0013). */
    public function money(): Money
    {
        return Money::of((int) $this->getAttribute('amount'), (string) ($this->getAttribute('currency') ?: 'IRT'));
    }

    public function orderId(): ?string
    {
        $orderId = $this->getAttribute('order_id');

        return $orderId === null || $orderId === '' ? null : (string) $orderId;
    }

    public function orderType(): ?string
    {
        $orderType = $this->getAttribute('order_type');

        return $orderType === null || $orderType === '' ? null : (string) $orderType;
    }

    public function referenceId(): ?string
    {
        $ref = $this->getAttribute('reference_id');

        return $ref === null || $ref === '' ? null : (string) $ref;
    }

    /** 🔒 PAN mask‌شده (هرگز کامل ذخیره نمی‌شود). */
    public function cardPan(): ?string
    {
        $pan = $this->getAttribute('card_pan');

        return $pan === null || $pan === '' ? null : (string) $pan;
    }

    /** @return array<string, mixed> */
    public function callbackPayload(): array
    {
        $payload = $this->getAttribute('callback_payload');

        return \is_array($payload) ? $payload : [];
    }

    public function isPending(): bool
    {
        return $this->status() === PaymentStatus::Pending->value;
    }

    public function isSuccess(): bool
    {
        return $this->status() === PaymentStatus::Success->value;
    }

    public function isFailed(): bool
    {
        return \in_array($this->status(), [PaymentStatus::Failed->value, PaymentStatus::Canceled->value], true);
    }

    public function isRefunded(): bool
    {
        return $this->status() === PaymentStatus::Refunded->value;
    }

    /**
     * اعمال نتیجهٔ verify روی تراکنش (بدون save — caller تصمیم می‌گیرد).
     */
    public function updateFromResult(PaymentResult $result): void
    {
        $status = $result->status;
        if ($status === PaymentStatus::AlreadyVerified) {
            // 🔒 double-payment: قبلاً موفق شده — وضعیت را برنمی‌گردانیم
            if (!$this->isSuccess()) {
                $this->setAttribute('status', PaymentStatus::Success->value);
                $this->setAttribute('paid_at', $this->getAttribute('paid_at')
                    ?? \Tavaan\Support\Time\Clock::now()->format('Y-m-d H:i:s.v'));
            }
        } else {
            $this->setAttribute('status', $status->value);
            if ($status === PaymentStatus::Success && $this->getAttribute('paid_at') === null) {
                $this->setAttribute('paid_at', \Tavaan\Support\Time\Clock::now()->format('Y-m-d H:i:s.v'));
            }
        }

        if ($result->referenceId !== null) {
            $this->setAttribute('reference_id', $result->referenceId);
        }
        if ($result->cardPan !== null && $result->cardPan !== '') {
            $this->setAttribute('card_pan', $result->cardPan);   // از قبل mask شده
        }
        if ($result->raw !== []) {
            // 🔒 ادغام — کلیدهای خرید (meta/redirect_url) هرگز بازنویسی نمی‌شوند
            $payload = $this->callbackPayload();
            $payload['verify'] = $result->raw;
            $this->setAttribute('callback_payload', $payload);
        }
    }
}
