<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * SandboxPaymentGateway — درگاه قطعی برای dev/test/demo (بدون شبکه).
 */

namespace Modules\Shop\Infrastructure\Gateways;

use Modules\Shop\Contracts\PaymentGatewayPort;

/**
 * SandboxPaymentGateway — رفتار قطعی و قابل تست:
 *
 * - purchase → authority = `sbx-<orderNumber>`، redirect به صفحهٔ sandbox خود ماژول
 * - verify → موفق، مگر `meta.simulate_failure` در purchase یا authority با
 *   پیشوند `sbx-fail-` (برای E2E-03: پرداخت ناموفق)
 * - refund → همیشه موفق (amount > 0)
 *
 * 🔒 هیچ I/O خارجی — مناسب CI و دمو.
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class SandboxPaymentGateway implements PaymentGatewayPort
{
    /** @var array<string, array<string, mixed>> authority → meta (در حافظهٔ فرایند) */
    private static array $started = [];

    public function purchase(int $amountMinor, string $currency, string $orderNumber, string $callbackUrl, ?string $mobile = null, ?string $email = null, array $meta = []): array
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('مبلغ پرداخت باید مثبت باشد.');
        }

        $fail = (bool) ($meta['simulate_failure'] ?? false);
        $authority = ($fail ? 'sbx-fail-' : 'sbx-') . $orderNumber . '-' . \substr(\bin2hex(\random_bytes(3)), 0, 6);

        self::$started[$authority] = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'order_number' => $orderNumber,
            'fail' => $fail,
        ];

        return [
            'authority' => $authority,
            'redirect_url' => '/shop/pay/sandbox/' . \rawurlencode($authority),
        ];
    }

    public function verify(string $authority, int $expectedAmountMinor, string $currency, array $callbackMeta = []): bool
    {
        if (\str_starts_with($authority, 'sbx-fail-')) {
            return false;
        }
        if (!\str_starts_with($authority, 'sbx-')) {
            return false;   // authority ناشناخته
        }

        $started = self::$started[$authority] ?? null;
        if ($started !== null) {
            // 🔒 تطبیق مبلغ با سفارش محلی (نه مبلغ callback — DOC-120 §5)
            if ((int) $started['amount'] !== $expectedAmountMinor || (string) $started['currency'] !== $currency) {
                return false;
            }

            return !(bool) $started['fail'];
        }

        // در فرایندهای جدا (redirect واقعی) حافظه در دسترس نیست — پیشوند authority معتبر است
        return true;
    }

    public function refund(string $authority, int $amountMinor, string $currency): bool
    {
        return $amountMinor > 0 && \str_starts_with($authority, 'sbx-') && !\str_starts_with($authority, 'sbx-fail-');
    }

    public function name(): string
    {
        return 'sandbox';
    }

    /** @internal فقط برای تست — پاک‌سازی حافظهٔ authorityها */
    public static function resetMemory(): void
    {
        self::$started = [];
    }
}
