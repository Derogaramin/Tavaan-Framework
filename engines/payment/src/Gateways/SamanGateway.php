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
 * SamanGateway — سامان/SEP (api.sep.ir/v1 — توکن سپس Redirect).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class SamanGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'saman';
    }

    protected function requiredConfig(): array
    {
        return ['merchant_id'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        [$status, $data] = $this->postJson($this->api() . '/Payments/GetToken', \array_filter([
            'MerchantId' => (string) $this->configValue('merchant_id'),
            'TerminalId' => $this->configValue('terminal_id') !== null ? (string) $this->configValue('terminal_id') : null,
            'Amount' => self::toRial($request->amount),
            'CallbackUrl' => $request->callbackUrl,
            'CellNumber' => $request->mobile !== '' ? $request->mobile : null,
            'OrderId' => \ctype_digit($request->orderId) ? (int) $request->orderId : \crc32($request->orderId),
            'Description' => $request->description,
        ], static fn (mixed $v): bool => $v !== null));

        $errorCode = (int) ($data['ErrorCode'] ?? -1);
        if ($status !== 200 || $errorCode !== 0 || empty($data['Token'])) {
            return PurchaseResult::failure((string) ($data['ErrorCode'] ?? (string) $status), $data);
        }

        $token = (string) $data['Token'];

        return PurchaseResult::success(
            $token,
            'https://sep.ir/Payments/Redirect?Token=' . \rawurlencode($token),
            $data,
        );
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        // RefNum از callback می‌آید (در نبود آن authority = Token)
        $refNum = (string) ($request->meta['RefNum'] ?? ($request->meta['refnum'] ?? $request->authority));

        [$status, $data] = $this->postJson($this->api() . '/Payments/Verify', [
            'MerchantId' => (string) $this->configValue('merchant_id'),
            'RefNum' => $refNum,
            'Amount' => self::toRial($request->amount),
        ]);

        $resultCode = (int) ($data['ResultCode'] ?? -1);
        if ($status === 200 && $resultCode > 0) {
            return new PaymentResult(
                PaymentStatus::Success,
                isset($data['TraceNo']) ? (string) $data['TraceNo'] : null,
                self::maskPan((string) ($data['SecurePan'] ?? '')),
                $request->amount,
                $request->authority,
                $data,
            );
        }

        // 🔒 ResultCode منفی = لغو/ناموفق؛ مبلغ ناسازگار هم رد می‌شود
        return new PaymentResult(
            \in_array($resultCode, [-1], true) ? PaymentStatus::Canceled : PaymentStatus::Failed,
            null,
            null,
            $request->amount,
            $request->authority,
            $data,
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $data] = $this->postJson($this->api() . '/Payments/Refund', [
            'MerchantId' => (string) $this->configValue('merchant_id'),
            'RefNum' => $request->referenceId ?? $request->authority,
            'Amount' => self::toRial($request->amount),
        ]);

        return $status === 200 && (int) ($data['ResultCode'] ?? -1) >= 0
            ? RefundResult::ok($request->referenceId, $data)
            : RefundResult::fail((string) ($data['ResultCode'] ?? (string) $status), $data);
    }

    private function api(): string
    {
        return $this->apiUrl('https://api.sep.ir/v1');
    }
}
