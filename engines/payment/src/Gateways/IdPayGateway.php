<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Gateways;

use Tavaan\Engine\Payment\Exceptions\SignatureMismatchException;
use Tavaan\Engine\Payment\PaymentResult;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PurchaseRequest;
use Tavaan\Engine\Payment\PurchaseResult;
use Tavaan\Engine\Payment\RefundRequest;
use Tavaan\Engine\Payment\RefundResult;
use Tavaan\Engine\Payment\VerifyRequest;

/**
 * تَوان | Tavaan — Payment
 * IdPayGateway — آیدی‌پی (api.idpay.ir/v1.1).
 *
 * 🔒 IPN با امضای HMAC-SHA256 (هدر X-Idpay-Signature) تأیید می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class IdPayGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'idpay';
    }

    protected function requiredConfig(): array
    {
        return ['api_key'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        [$status, $data] = $this->postJson($this->api() . '/webapp/request', \array_filter([
            'order_id' => $request->orderId,
            'amount' => self::toRial($request->amount),   // آیدی‌پی ریال
            'callback' => $request->callbackUrl,
            'phone' => $request->mobile !== '' ? $request->mobile : null,
            'mail' => $request->email,
            'desc' => $request->description,
            'name' => $request->meta['name'] ?? null,
        ], static fn (mixed $v): bool => $v !== null), $this->headers());

        if ($status !== 201 || !isset($data['id'])) {
            return PurchaseResult::failure((string) ($data['error_code'] ?? ($data['code'] ?? 'unknown')), $data);
        }

        $authority = (string) $data['id'];

        return PurchaseResult::success($authority, (string) ($data['link'] ?? ''), $data);
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        [$status, $data] = $this->postJson($this->api() . '/webapp/verify', [
            'id' => $request->authority,
            'order_id' => (string) ($request->meta['order_id'] ?? ''),
        ], $this->headers());

        $code = (int) ($data['status'] ?? -1);

        return match (true) {
            $status === 200 && $code === 200 => new PaymentResult(
                PaymentStatus::Success,
                isset($data['ref_id']) ? (string) $data['ref_id'] : null,
                self::maskPan((string) ($data['card_number'] ?? '')),
                $request->amount,
                $request->authority,
                $data,
            ),
            $code === 201 => new PaymentResult(PaymentStatus::AlreadyVerified, null, null, $request->amount, $request->authority, $data),
            \in_array($code, [100, 101, 102, 103, 104, 105, 106], true) => new PaymentResult(PaymentStatus::Pending, null, null, $request->amount, $request->authority, $data),
            default => new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, $data),
        };
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $data] = $this->postJson($this->api() . '/webapp/refund', [
            'id' => $request->authority,
        ], $this->headers());

        if ($status === 200 && \in_array((int) ($data['status'] ?? 0), [200, 201], true)) {
            return RefundResult::ok(isset($data['ref_id']) ? (string) $data['ref_id'] : null, $data);
        }

        return RefundResult::fail((string) ($data['status'] ?? 'unknown'), $data);
    }

    public function inquire(string $authority): PaymentStatus
    {
        [$status, $data] = $this->postJson($this->api() . '/webapp/inquiry', ['id' => $authority], $this->headers());
        if ($status !== 200) {
            return PaymentStatus::Pending;
        }

        return match ((int) ($data['status'] ?? -1)) {
            200 => PaymentStatus::Success,
            201 => PaymentStatus::AlreadyVerified,
            100, 101, 102, 103, 104, 105, 106 => PaymentStatus::Pending,
            default => PaymentStatus::Failed,
        };
    }

    /**
     * 🔒 تأیید امضای IPN: هدر `X-Idpay-Signature` = HMAC-SHA256(body, api_key).
     *
     * @throws SignatureMismatchException
     */
    public function assertNotifySignature(string $body, ?string $signature): void
    {
        $apiKey = (string) $this->configValue('api_key', '');
        $expected = \hash_hmac('sha256', $body, $apiKey);

        if ($signature === null || $signature === '' || !\hash_equals($expected, \strtolower($signature))) {
            throw SignatureMismatchException::for($this->name());
        }
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'X-API-KEY' => (string) $this->configValue('api_key'),
            'X-SANDBOX' => $this->sandbox() ? '1' : '0',
        ];
    }

    private function api(): string
    {
        return $this->apiUrl('https://api.idpay.ir/v1.1');
    }
}
