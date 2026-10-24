<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CheckoutService — شروع پرداخت و رسیدگی به بازگشت (DOC-093 §4.3).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\Shop\Contracts\PaymentGatewayPort;
use Modules\Shop\Domain\Entities\Order;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;

/**
 * CheckoutService — purchase (شروع پرداخت) و verify (بازگشت کاربر).
 *
 * 🔒 verify همیشه با مبلغ سفارش **محلی** اعتبارسنجی می‌شود (DOC-120 §5:
 * «verify از API، نه callback» + تطبیق مبلغ درگاه).
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CheckoutService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderWorkflowService $workflow,
        private readonly PaymentGatewayPort $gateway,
    ) {
    }

    /**
     * شروع پرداخت برای سفارش pending.
     *
     * @return array{authority: string, redirect_url: string, order: Order}
     */
    public function startPayment(Order $order, string $callbackUrl, ?string $mobile = null, ?string $email = null, bool $simulateFailure = false): array
    {
        if ($order->status() !== \Modules\Shop\Domain\Enums\OrderStatus::Pending) {
            throw new ValidationException(
                errors: ['order' => ['فقط سفارش در انتظار پرداخت قابل پرداخت است.']],
                messageId: 'shop::messages.order_not_payable',
            );
        }

        $result = $this->gateway->purchase(
            amountMinor: $order->total()->minor,
            currency: $order->currency(),
            orderNumber: $order->orderNumber(),
            callbackUrl: $callbackUrl,
            mobile: $mobile,
            email: $email,
            meta: [
                'order_id' => $order->id(),
                'customer_id' => $order->customerId(),
                'simulate_failure' => $simulateFailure,
            ],
        );

        $order->setPaymentAuthority($result['authority']);
        $order->setPaymentMethod($this->gateway->name());
        $this->orders->save($order);

        return ['authority' => $result['authority'], 'redirect_url' => $result['redirect_url'], 'order' => $order];
    }

    /**
     * رسیدگی به بازگشت کاربر — verify موفق → markPaid.
     *
     * @param array<string, mixed> $callbackMeta
     */
    public function handleReturn(string $authority, array $callbackMeta = []): Order
    {
        $order = $this->orders->findByAuthority($authority);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }

        if ($order->status() !== \Modules\Shop\Domain\Enums\OrderStatus::Pending) {
            return $order;   // idempotent — قبلاً پردازش شده
        }

        $ok = $this->gateway->verify($authority, $order->total()->minor, $order->currency(), $callbackMeta);
        if (!$ok) {
            // E2E-03: پرداخت ناموفق → سفارش pending می‌ماند (retry مجاز)
            throw new ValidationException(
                errors: ['payment' => ['پرداخت تأیید نشد؛ سفارش در انتظار پرداخت ماند.']],
                messageId: 'shop::messages.payment_failed',
            );
        }

        return $this->workflow->markPaid($order, $authority);
    }
}
