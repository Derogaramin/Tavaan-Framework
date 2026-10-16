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
 * StripeGateway — استرایپ (Checkout Sessions — form-encoded + Bearer).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class StripeGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    protected function requiredConfig(): array
    {
        return ['secret_key'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $currency = \strtolower($request->amount->currency);

        [$status, $data] = $this->postForm($this->api() . '/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $request->callbackUrl . (\str_contains($request->callbackUrl, '?') ? '&' : '?') . 'status=OK&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $request->callbackUrl . (\str_contains($request->callbackUrl, '?') ? '&' : '?') . 'status=CANCELED',
            'client_reference_id' => $request->orderId,
            'customer_email' => $request->email ?? '',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => $request->amount->amount,
            'line_items[0][price_data][product_data][name]' => $request->description ?? ('Order ' . $request->orderId),
        ], $this->headers());

        if ($status !== 200 || empty($data['id'])) {
            return PurchaseResult::failure((string) ($data['error']['code'] ?? ($data['error']['type'] ?? (string) $status)), $data);
        }

        return PurchaseResult::success((string) $data['id'], (string) ($data['url'] ?? ''), $data);
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        $response = $this->get($this->api() . '/checkout/sessions/' . \rawurlencode($request->authority), $this->headers());
        $data = $response->json() ?? [];
        $status = $response->status();

        $paymentStatus = (string) ($data['payment_status'] ?? '');
        if ($status === 200 && $paymentStatus === 'paid') {
            return new PaymentResult(
                PaymentStatus::Success,
                isset($data['payment_intent']) ? (string) $data['payment_intent'] : null,
                null,
                $request->amount,
                $request->authority,
                $data,
            );
        }
        if ($status === 200 && $paymentStatus === 'unpaid') {
            return new PaymentResult(PaymentStatus::Pending, null, null, $request->amount, $request->authority, $data);
        }

        return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, $data);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        [$status, $data] = $this->postForm($this->api() . '/refunds', \array_filter([
            'payment_intent' => $request->referenceId,
            'amount' => $request->amount->amount > 0 ? $request->amount->amount : null,
        ], static fn (mixed $v): bool => $v !== null), $this->headers());

        return $status === 200 && !empty($data['id'])
            ? RefundResult::ok((string) $data['id'], $data)
            : RefundResult::fail((string) ($data['error']['code'] ?? (string) $status), $data);
    }

    public function inquire(string $authority): PaymentStatus
    {
        $response = $this->get($this->api() . '/checkout/sessions/' . \rawurlencode($authority), $this->headers());
        $data = $response->json() ?? [];

        return match ((string) ($data['payment_status'] ?? '')) {
            'paid' => PaymentStatus::Success,
            'unpaid' => PaymentStatus::Pending,
            default => $response->status() === 200 ? PaymentStatus::Failed : PaymentStatus::Pending,
        };
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . (string) $this->configValue('secret_key')];
    }

    private function api(): string
    {
        return $this->apiUrl('https://api.stripe.com/v1');
    }
}
