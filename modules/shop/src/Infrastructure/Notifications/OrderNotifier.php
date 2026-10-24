<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderNotifier — اعلان‌های سفارش به مشتری (DOC-093 §4.3/§6).
 */

namespace Modules\Shop\Infrastructure\Notifications;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * شنوندهٔ `shop.order.paid|shipped|canceled` — mail+database به مشتری.
 * ساخت پیام از binding `notification.message` (بدون ارجاع به موتور).
 *
 * @api 1.0
 */
final class OrderNotifier
{
    /** @param array<string, mixed> $config کلید config('shop.notifications') */
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly UserReaderInterface $users,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    public function onPaid(string $event, mixed $payload): void
    {
        $this->notifyCustomer((array) $payload, 'paid');
    }

    public function onShipped(string $event, mixed $payload): void
    {
        $this->notifyCustomer((array) $payload, 'shipped');
    }

    public function onCanceled(string $event, mixed $payload): void
    {
        $this->notifyCustomer((array) $payload, 'canceled');
    }

    /** @param array<string, mixed> $payload */
    private function notifyCustomer(array $payload, string $kind): void
    {
        if (!(bool) ($this->config['order_' . $kind] ?? true)) {
            return;
        }

        try {
            $order = $this->orders->findById((string) ($payload['order_id'] ?? ''));
            if ($order === null) {
                return;
            }
            $customer = $this->users->findNotifiable($order->customerId());
            if ($customer === null) {
                return;
            }

            $number = $order->orderNumber();
            $notification = $this->makeNotification([
                'via' => ['mail', 'database'],
                'mail' => [
                    'subject' => Lang::get('shop::messages.notify_' . $kind . '_subject', ['number' => $number]),
                    'lines' => [Lang::get('shop::messages.notify_' . $kind . '_line', [
                        'number' => $number,
                        'tracking' => (string) ($payload['tracking_code'] ?? ''),
                    ])],
                    'action' => [
                        'text' => Lang::get('shop::messages.notify_cta'),
                        'url' => '/shop/orders/' . $number,
                    ],
                ],
                'database' => [
                    'kind' => 'shop.order.' . $kind,
                    'order_id' => $order->id(),
                    'order_number' => $number,
                ],
            ]);

            if ($notification !== null) {
                Notification::send($customer, $notification);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /** @param array<string, mixed> $payload */
    private function makeNotification(array $payload): ?object
    {
        if (!$this->container->bound('notification.message')) {
            return null;
        }

        $notification = $this->container->make('notification.message', ['payload' => $payload]);

        return \is_object($notification) ? $notification : null;
    }
}
