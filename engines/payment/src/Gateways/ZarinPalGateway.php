<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Gateways;

use Tavaan\Engine\Payment\PaymentResult;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PurchaseRequest;
use Tavaan\Engine\Payment\PurchaseResult;
use Tavaan\Engine\Payment\RefundRequest;
use Tavaan\Engine\Payment\RefundResult;
use Tavaan\Engine\Payment\VerifyRequest;

/**
 * تَوان | Tavaan — Payment
 * ZarinPalGateway — زرین‌پال (API v4 — DOC-073 §5).
 *
 * 🔒 مبلغ به تومان ارسال می‌شود (نه ریال).
 * 🔒 verify فقط از طریق API درگاه (هرگز اعتماد به Status بازگشتی).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class ZarinPalGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'zarinpal';
    }

    protected function requiredConfig(): array
    {
        return ['merchant_id'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        [$status, $data] = $this->postJson($this->api() . '/payment/request.json', \array_filter([
            'merchant_id' => (string) $this->configValue('merchant_id'),
            'amount' => self::toToman($request->amount),
            'callback_url' => $request->callbackUrl,
            'description' => $request->description ?? 'پرداخت سفارش ' . $request->orderId,
            'mobile' => $request->mobile !== '' ? $request->mobile : null,
            'email' => $request->email,
            'metadata' => $request->meta === [] ? null : ['order_id' => $request->orderId, ...$request->meta],
        ], static fn (mixed $v): bool => $v !== null));

        $code = (int) ($data['data']['code'] ?? ($data['data']['Code'] ?? -1));
        if ($status !== 200 || $code !== 100) {
            return PurchaseResult::failure((string) ($data['data']['code'] ?? ($data['errors']['code'] ?? 'unknown')), $data);
        }

        $authority = (string) ($data['data']['authority'] ?? '');

        return PurchaseResult::success($authority, $this->startPayUrl($authority), $data);
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        [$status, $data] = $this->postJson($this->api() . '/payment/verify.json', [
            'merchant_id' => (string) $this->configValue('merchant_id'),
            'amount' => self::toToman($request->amount),
            'authority' => $request->authority,
        ]);

        $payload = (array) ($data['data'] ?? []);
        $code = (int) ($payload['code'] ?? ($payload['Code'] ?? -1));

        return match (true) {
            $status === 200 && $code === 100 => new PaymentResult(
                PaymentStatus::Success,
                isset($payload['ref_id']) ? (string) $payload['ref_id'] : null,
                self::maskPan((string) ($payload['card_pan'] ?? '')),
                $request->amount,
                $request->authority,
                $data,
            ),
            $code === 101 => new PaymentResult(PaymentStatus::AlreadyVerified, null, null, $request->amount, $request->authority, $data),
            default => new PaymentResult(self::mapFailure($code), null, null, $request->amount, $request->authority, $data),
        };
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $data] = $this->postJson($this->api() . '/payment/refund.json', [
            'merchant_id' => (string) $this->configValue('merchant_id'),
            'authority' => $request->authority,
            // مبلغ منفی = بازگشت (طبق API v4)
            'amount' => -self::toToman($request->amount),
            'ref_id' => $request->referenceId,
        ]);

        $code = (int) ($data['data']['code'] ?? -1);
        if ($status === 200 && $code === 100) {
            return RefundResult::ok(isset($data['data']['ref_id']) ? (string) $data['data']['ref_id'] : $request->referenceId, $data);
        }

        return RefundResult::fail((string) ($data['data']['code'] ?? ($data['errors']['code'] ?? 'unknown')), $data);
    }

    private function api(): string
    {
        return $this->apiUrl('https://api.zarinpal.com/pg/v4');
    }

    private function startPayUrl(string $authority): string
    {
        $host = $this->sandbox() ? 'https://sandbox.zarinpal.com/pg/StartPay' : 'https://payment.zarinpal.com/pg/StartPay';

        return $host . '/' . \rawurlencode($authority);
    }

    /** نگاشت کدهای خطای زرین‌پال به وضعیت. */
    private static function mapFailure(int $code): PaymentStatus
    {
        // کدهای لغو کاربر/انقضا
        return \in_array($code, [-20, -21, -22, 51, 52, 53, 54, 61, 62, 63, 64, 65, 66, 67], true)
            ? PaymentStatus::Canceled
            : PaymentStatus::Failed;
    }
}
