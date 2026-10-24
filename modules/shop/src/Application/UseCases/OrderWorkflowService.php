<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderWorkflowService — انتقال‌های وضعیت سفارش + عوارض (DOC-093 §4.3/§6).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\Shop\Contracts\PaymentGatewayPort;
use Modules\Shop\Domain\Entities\Order;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Modules\Shop\Domain\Services\InventoryService;
use Tavaan\Core\Facade\Facades\Event;

/**
 * OrderWorkflowService — markPaid/ship/deliver/cancel/refund.
 *
 * عوارض هر انتقال:
 * - paid     → رویداد shop.order.paid (فاکتور/notify/crm)
 * - canceled → آزادسازی موجودی (inventory.release) + refund اگر پرداخت‌شده
 * - refunded → refund درگاه + رویداد
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class OrderWorkflowService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly InventoryService $inventory,
        private readonly PaymentGatewayPort $gateway,
    ) {
    }

    public function markPaid(Order $order, string $authority = ''): Order
    {
        $order->markPaid($authority);
        $this->orders->save($order);
        $this->flushEvents($order);

        return $order;
    }

    public function ship(Order $order, string $trackingCode = ''): Order
    {
        $order->ship($trackingCode);
        $this->orders->save($order);
        $this->flushEvents($order);

        return $order;
    }

    public function deliver(Order $order): Order
    {
        $order->deliver();
        $this->orders->save($order);
        $this->flushEvents($order);

        return $order;
    }

    /**
     * لغو سفارش — DOC-093 §6: آزادسازی موجودی + refund اگر پرداخت‌شده بود.
     */
    public function cancel(Order $order, string $reason = ''): Order
    {
        $wasPaid = $order->isPaid();

        $order->cancel($reason);
        $this->orders->save($order);

        // آزادسازی موجودی (همه‌یا‑هیچ در reserve انجام شده بود)
        $this->inventory->release($order->items(), $order->id());

        // استرداد وجه سفارش پرداخت‌شده — best-effort (خطای درگاه لغو را نمی‌شکند)
        if ($wasPaid && $order->paymentAuthority() !== null && $order->paymentAuthority() !== '') {
            try {
                $this->gateway->refund($order->paymentAuthority(), $order->total()->minor, $order->currency());
            } catch (\Throwable) {
                // در گزارش/audit قابل ردیابی —refund دستی توسط مدیر
            }
        }

        $this->flushEvents($order);

        return $order;
    }

    /** استرداد سفارش پرداخت‌شده (مدیریتی — DOC-093 §5: shop.order.refund). */
    public function refund(Order $order, string $reason = ''): Order
    {
        $refunded = false;
        if ($order->paymentAuthority() !== null && $order->paymentAuthority() !== '') {
            $refunded = $this->gateway->refund($order->paymentAuthority(), $order->total()->minor, $order->currency());
        }

        $order->refund($reason !== '' ? $reason : ($refunded ? 'refund via gateway' : 'manual refund'));
        $this->orders->save($order);
        $this->flushEvents($order);

        return $order;
    }

    private function flushEvents(Order $order): void
    {
        foreach ($order->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
