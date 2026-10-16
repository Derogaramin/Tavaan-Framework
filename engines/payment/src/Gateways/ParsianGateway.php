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
 * ParsianGateway — پارسیان (pec.shaparak.ir — SOAP/XML خودنوشته).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class ParsianGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'parsian';
    }

    protected function requiredConfig(): array
    {
        return ['login_account'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $orderId = \ctype_digit($request->orderId) ? (int) $request->orderId : \crc32($request->orderId);

        $envelope = $this->soapEnvelope('SalesPaymentRequest', \implode('', [
            $this->tag('LoginAccount', $this->configValue('login_account')),
            $this->tag('Amount', self::toRial($request->amount)),
            $this->tag('OrderId', $orderId),
            $this->tag('AdditionalData', $request->description ?? ''),
            $this->tag('CallBackUrl', $request->callbackUrl),
        ]));

        $response = $this->postRaw($this->api('SalesPaymentRequest'), $envelope, 'text/xml; charset=utf-8', ['SOAPAction' => '#SalesPaymentRequest']);
        $body = $response->body();

        $rs = (int) (self::xmlValue($body, 'Rs') ?? -1);
        $token = (string) (self::xmlValue($body, 'Token') ?? '');
        if ($response->status() !== 200 || $rs !== 0 || $token === '') {
            return PurchaseResult::failure((string) $rs, ['rs' => $rs]);
        }

        return PurchaseResult::success(
            $token,
            'https://pec.shaparak.ir/NewIPG/payment/confirm?Token=' . \rawurlencode($token),
            ['token' => $token, 'rs' => $rs],
        );
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        $envelope = $this->soapEnvelope('ConfirmPayment', \implode('', [
            $this->tag('LoginAccount', $this->configValue('login_account')),
            $this->tag('Token', $request->authority),
        ]));

        $response = $this->postRaw($this->api('ConfirmPayment'), $envelope, 'text/xml; charset=utf-8', ['SOAPAction' => '#ConfirmPayment']);
        $body = $response->body();

        $rs = (int) (self::xmlValue($body, 'Rs') ?? -1);
        if ($response->status() === 200 && $rs === 0) {
            return new PaymentResult(
                PaymentStatus::Success,
                self::xmlValue($body, 'TraceNo'),
                self::maskPan((string) (self::xmlValue($body, 'CardNumberMasked') ?? '')),
                $request->amount,
                $request->authority,
                ['rs' => $rs],
            );
        }

        return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, ['rs' => $rs]);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $envelope = $this->soapEnvelope('PaymentReversal', \implode('', [
            $this->tag('LoginAccount', $this->configValue('login_account')),
            $this->tag('Token', $request->authority),
        ]));

        $response = $this->postRaw($this->api('PaymentReversal'), $envelope, 'text/xml; charset=utf-8', ['SOAPAction' => '#PaymentReversal']);
        $rs = (int) (self::xmlValue($response->body(), 'Rs') ?? -1);

        return $rs === 0
            ? RefundResult::ok($request->referenceId, ['rs' => $rs])
            : RefundResult::fail((string) $rs, ['rs' => $rs]);
    }

    private function api(string $service): string
    {
        $base = $this->apiUrl('https://pec.shaparak.ir/NewIPGServices/Unique/Coupon');

        return $base . '/' . $service;
    }

    private function soapEnvelope(string $operation, string $params): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:ns="https://pec.shaparak.ir/NewIPGServices/Unique/Coupon">'
            . '<soapenv:Header/>'
            . '<soapenv:Body><ns:' . $operation . '>' . $params . '</ns:' . $operation . '></soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    private function tag(string $name, mixed $value): string
    {
        return '<' . $name . '>' . self::xmlEscape($value) . '</' . $name . '>';
    }
}
