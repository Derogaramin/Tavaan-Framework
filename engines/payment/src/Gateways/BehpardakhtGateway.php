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
 * BehpardakhtGateway — به‌پرداخت ملت (SOAP/XML — بدون ext-soap، خودنوشته).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class BehpardakhtGateway extends AbstractHttpGateway
{
    public function name(): string
    {
        return 'behpardakht';
    }

    protected function requiredConfig(): array
    {
        return ['terminal_id', 'username', 'password'];
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $orderId = \ctype_digit($request->orderId) ? (int) $request->orderId : \crc32($request->orderId);

        $envelope = $this->soapEnvelope('ns1:PayRequest', \implode('', [
            $this->tag('terminalId', $this->configValue('terminal_id')),
            $this->tag('userName', $this->configValue('username')),
            $this->tag('userPassword', $this->configValue('password')),
            $this->tag('orderId', $orderId),
            $this->tag('amount', self::toRial($request->amount)),
            $this->tag('localDate', \Tavaan\Support\Time\Clock::now()->format('Ymd')),
            $this->tag('localTime', \Tavaan\Support\Time\Clock::now()->format('Gis')),
            $this->tag('additionalData', $request->description ?? ''),
            $this->tag('callBackUrl', $request->callbackUrl),
            $this->tag('payerId', 0),
        ]));

        $response = $this->postRaw($this->api(), $envelope, 'text/xml; charset=utf-8', ['SOAPAction' => '']);
        $body = $response->body();

        $resCode = (int) (self::xmlValue($body, 'ResCode') ?? -1);
        if ($response->status() !== 200 || $resCode !== 0) {
            return PurchaseResult::failure((string) $resCode, ['res_code' => $resCode]);
        }

        $refId = (string) (self::xmlValue($body, 'RefId') ?? '');

        return PurchaseResult::success(
            $refId,
            'https://bpm.shaparak.ir/pgwchannel/startpay.mellat?RefId=' . \rawurlencode($refId),
            ['ref_id' => $refId, 'res_code' => $resCode],
        );
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        // مرحله ۱: PayVerification
        $envelope = $this->soapEnvelope('ns1:PayVerification', $this->tag('RefId', $request->authority));
        $response = $this->postRaw($this->api(), $envelope, 'text/xml; charset=utf-8', ['SOAPAction' => '']);
        $body = $response->body();

        $payRes = (int) (self::xmlValue($body, 'ResCode') ?? -1);
        if ($response->status() !== 200 || $payRes !== 0) {
            return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, ['res_code' => $payRes]);
        }
        $saleRefId = (string) (self::xmlValue($body, 'SaleReferenceId') ?? '');

        // مرحله ۲: SaleConfirm (settle)
        $confirm = $this->soapEnvelope('ns1:SaleConfirm', $this->tag('SaleOrderId', '') . $this->tag('SaleReferenceId', $saleRefId));
        $confirmBody = $this->postRaw($this->api(), $confirm, 'text/xml; charset=utf-8', ['SOAPAction' => ''])->body();
        $confirmRes = (int) (self::xmlValue($confirmBody, 'ResCode') ?? -1);

        if ($confirmRes === 43) {
            // پیش‌تر settle شده (idempotent)
            return new PaymentResult(PaymentStatus::AlreadyVerified, $saleRefId, null, $request->amount, $request->authority, ['res_code' => $confirmRes]);
        }
        if ($confirmRes !== 0) {
            return new PaymentResult(PaymentStatus::Failed, $saleRefId, null, $request->amount, $request->authority, ['res_code' => $confirmRes]);
        }

        return new PaymentResult(PaymentStatus::Success, $saleRefId, null, $request->amount, $request->authority, [
            'res_code' => 0,
            'sale_reference_id' => $saleRefId,
        ]);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $envelope = $this->soapEnvelope('ns1:SaleReversal', $this->tag('SaleReferenceId', (string) ($request->referenceId ?? '')));
        $response = $this->postRaw($this->api(), $envelope, 'text/xml; charset=utf-8', ['SOAPAction' => '']);

        $resCode = (int) (self::xmlValue($response->body(), 'ResCode') ?? -1);

        return $resCode === 0
            ? RefundResult::ok($request->referenceId, ['res_code' => $resCode])
            : RefundResult::fail((string) $resCode, ['res_code' => $resCode]);
    }

    private function api(): string
    {
        return $this->apiUrl('https://bpm.shaparak.ir/pgwchannel/services/pgw');
    }

    private function soapEnvelope(string $operation, string $params): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:ns1="http://interfaces.pgw.zarinpal.com/">'
            . '<soapenv:Header/>'
            . '<soapenv:Body><' . $operation . '>' . $params . '</' . $operation . '></soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    private function tag(string $name, mixed $value): string
    {
        return '<' . $name . '>' . self::xmlEscape($value) . '</' . $name . '>';
    }
}
