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
 * MelliGateway — درگاه بانک ملی (BAM/پرداخت‌یار — REST JSON).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class MelliGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'melli';
    }

    protected function requiredConfig(): array
    {
        return ['merchant_id', 'sign_key'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        [$status, $data] = $this->postJson($this->api() . '/payment/request', [
            'merchant_id' => (string) $this->configValue('merchant_id'),
            'amount' => self::toRial($request->amount),
            'order_id' => $request->orderId,
            'callback_url' => $request->callbackUrl,
            'mobile' => $request->mobile,
            'description' => $request->description,
            'signature' => $this->hmac($request->orderId . '|' . self::toRial($request->amount)),
        ], $this->headers());

        $code = (int) ($data['code'] ?? -1);
        if ($status !== 200 || $code !== 0 || empty($data['token'])) {
            return PurchaseResult::failure((string) ($data['code'] ?? (string) $status), $data);
        }

        $token = (string) $data['token'];

        return PurchaseResult::success(
            $token,
            (string) ($data['redirect_url'] ?? ($this->portal() . '?token=' . \rawurlencode($token))),
            $data,
        );
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        [$status, $data] = $this->postJson($this->api() . '/payment/verify', [
            'merchant_id' => (string) $this->configValue('merchant_id'),
            'token' => $request->authority,
            'amount' => self::toRial($request->amount),
            'signature' => $this->hmac($request->authority . '|' . self::toRial($request->amount)),
        ], $this->headers());

        $code = (int) ($data['code'] ?? -1);
        if ($status === 200 && $code === 0) {
            return new PaymentResult(
                PaymentStatus::Success,
                isset($data['ref_id']) ? (string) $data['ref_id'] : null,
                self::maskPan((string) ($data['card_pan'] ?? '')),
                $request->amount,
                $request->authority,
                $data,
            );
        }

        return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, $data);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $data] = $this->postJson($this->api() . '/payment/refund', [
            'merchant_id' => (string) $this->configValue('merchant_id'),
            'token' => $request->authority,
            'ref_id' => $request->referenceId,
            'signature' => $this->hmac((string) ($request->referenceId ?? $request->authority)),
        ], $this->headers());

        return $status === 200 && (int) ($data['code'] ?? -1) === 0
            ? RefundResult::ok($request->referenceId, $data)
            : RefundResult::fail((string) ($data['code'] ?? (string) $status), $data);
    }

    private function hmac(string $payload): string
    {
        return \hash_hmac('sha256', $payload, (string) $this->configValue('sign_key'));
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $apiKey = $this->configValue('api_key');

        return $apiKey === null ? [] : ['X-API-Key' => (string) $apiKey];
    }

    private function api(): string
    {
        return $this->apiUrl('https://bam-api.bmi.ir/pg/v1');
    }

    private function portal(): string
    {
        return (string) $this->configValue('portal_url', 'https://bam.bmi.ir/pg/redirect');
    }
}
