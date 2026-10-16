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
 * PayPingGateway — پی‌پینگ (api.payping.ir/v2 — Bearer token).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PayPingGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'payping';
    }

    protected function requiredConfig(): array
    {
        return ['token'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        [$status, $data] = $this->postJson($this->api() . '/pay', \array_filter([
            'amount' => self::toToman($request->amount),
            'callbackUrl' => $request->callbackUrl,
            'clientRefId' => $request->orderId,
            'description' => $request->description,
            'mobileNumber' => $request->mobile !== '' ? $request->mobile : null,
            'email' => $request->email,
        ], static fn (mixed $v): bool => $v !== null), $this->headers());

        if ($status !== 200 || !isset($data['code'])) {
            return PurchaseResult::failure((string) ($data['Error'] ?? ($data['error'] ?? (string) $status)), $data);
        }

        $code = (string) $data['code'];

        return PurchaseResult::success($code, $this->api() . '/pay/startipg?code=' . \rawurlencode($code), $data);
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        // refId از callback می‌آید؛ در نبود آن، authority (همان code)
        $refId = (string) ($request->meta['refid'] ?? ($request->meta['refId'] ?? $request->authority));

        [$status, $data] = $this->postJson($this->api() . '/pay/verify', [
            'refId' => $refId,
            'amount' => self::toToman($request->amount),
        ], $this->headers());

        if ($status === 200) {
            return new PaymentResult(
                PaymentStatus::Success,
                isset($data['refId']) ? (string) $data['refId'] : $refId,
                self::maskPan((string) ($data['cardNumber'] ?? '')),
                $request->amount,
                $request->authority,
                $data,
            );
        }

        return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, $data);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $data] = $this->postJson($this->api() . '/pay/refund', [
            'refId' => $request->referenceId ?? $request->authority,
            'amount' => self::toToman($request->amount),
        ], $this->headers());

        return $status === 200
            ? RefundResult::ok($request->referenceId, $data)
            : RefundResult::fail((string) $status, $data);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . (string) $this->configValue('token')];
    }

    private function api(): string
    {
        return $this->apiUrl('https://api.payping.ir/v2');
    }
}
