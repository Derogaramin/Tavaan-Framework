<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Tests\Fixtures;

use Tavaan\Core\Contracts\Payment\GatewayInterface;
use Tavaan\Engine\Payment\PaymentResult;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PurchaseRequest;
use Tavaan\Engine\Payment\PurchaseResult;
use Tavaan\Engine\Payment\RefundRequest;
use Tavaan\Engine\Payment\RefundResult;
use Tavaan\Engine\Payment\VerifyRequest;

/**
 * فیکسچرهای تست موتور Payment (DOC-073 §9).
 */

/** درگاه سفارشی تست — AC-PAY-10 (فقط پیاده‌سازی GatewayInterface). */
final class FakeGateway implements GatewayInterface
{
    /** @var list<array{op: string, authority: string, amount: int}> */
    public array $calls = [];

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $this->calls[] = ['op' => 'purchase', 'authority' => $request->orderId, 'amount' => $request->amount->amount];

        return PurchaseResult::success('fake-' . $request->orderId, 'https://fake.gateway/pay/' . $request->orderId);
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        $this->calls[] = ['op' => 'verify', 'authority' => $request->authority, 'amount' => $request->amount->amount];

        return new PaymentResult(PaymentStatus::Success, 'fake-ref-1', '603799****4321', $request->amount, $request->authority);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $this->calls[] = ['op' => 'refund', 'authority' => $request->authority, 'amount' => $request->amount->amount];

        return RefundResult::ok('fake-refund-1');
    }

    public function inquire(string $authority): PaymentStatus
    {
        return PaymentStatus::Success;
    }

    public function name(): string
    {
        return 'fakegw';
    }
}
