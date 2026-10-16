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
 * SadadGateway — سداد (ملی/mbanking) با payload امضاشده (HMAC-SHA256).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class SadadGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'sadad';
    }

    protected function requiredConfig(): array
    {
        return ['merchant_id', 'sign_key'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $orderId = \ctype_digit($request->orderId) ? (int) $request->orderId : \crc32($request->orderId);

        $data = [
            'MerchantId' => (string) $this->configValue('merchant_id'),
            'ReturnUrl' => $request->callbackUrl,
            'Amount' => self::toRial($request->amount),
            'OrderId' => $orderId,
            'UserId' => (string) ($request->meta['user_id'] ?? ''),
            'Description' => $request->description ?? '',
        ];

        [$status, $response] = $this->postJson(
            $this->api() . '/PaymentRequest',
            ['Data' => $this->sign($data)],
        );

        if ($status !== 200 || (int) ($response['ResCode'] ?? -1) !== 0 || empty($response['Token'])) {
            return PurchaseResult::failure((string) ($response['ResCode'] ?? (string) $status), $response);
        }

        $token = (string) $response['Token'];

        return PurchaseResult::success(
            $token,
            'https://sadad.shaparak.ir/VPG/Purchase?token=' . \rawurlencode($token),
            $response,
        );
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        [$status, $response] = $this->postJson($this->api() . '/PaymentAdvice', [
            'Data' => $this->sign([
                'MerchantId' => (string) $this->configValue('merchant_id'),
                'Token' => $request->authority,
                'Amount' => self::toRial($request->amount),
            ]),
        ]);

        $resCode = (int) ($response['ResCode'] ?? -1);
        if ($status === 200 && $resCode === 0) {
            return new PaymentResult(
                PaymentStatus::Success,
                isset($response['RetrivalRefNo']) ? (string) $response['RetrivalRefNo'] : null,
                self::maskPan((string) ($response['PrimaryAcc'] ?? ($response['CardHolderPan'] ?? ''))),
                $request->amount,
                $request->authority,
                $response,
            );
        }

        return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, $response);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $response] = $this->postJson($this->api() . '/PaymentReversal', [
            'Data' => $this->sign([
                'MerchantId' => (string) $this->configValue('merchant_id'),
                'Token' => $request->authority,
                'Amount' => self::toRial($request->amount),
            ]),
        ]);

        return $status === 200 && (int) ($response['ResCode'] ?? -1) === 0
            ? RefundResult::ok(isset($response['RetrivalRefNo']) ? (string) $response['RetrivalRefNo'] : null, $response)
            : RefundResult::fail((string) ($response['ResCode'] ?? (string) $status), $response);
    }

    /**
     * امضای payload: base64(json) + HMAC-SHA256 با sign_key.
     *
     * @param array<string, mixed> $data
     */
    private function sign(array $data): string
    {
        $encoded = \base64_encode(\json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $signature = \hash_hmac('sha256', $encoded, (string) $this->configValue('sign_key'));

        return $encoded . '.' . $signature;
    }

    private function api(): string
    {
        return $this->apiUrl('https://api.mbanking.ir/sadad/merchant/api/v1');
    }
}
