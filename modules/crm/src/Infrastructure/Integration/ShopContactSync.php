<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ShopContactSync — همگام‌سازی مشتری فروشگاه (DOC-096 §7 — E2E-06).
 */

namespace Modules\Crm\Infrastructure\Integration;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\ContactService;
use Tavaan\Core\Facade\Facades\Log;

/**
 * ShopContactSync — شنوندهٔ `shop.order.paid`:
 *
 * customer_id → snapshot کاربر (core-user) → upsert مخاطب با source=shop
 * و custom_fields (مجموع خرید/آخرین سفارش).
 *
 * 🔒 مرز ماژول: فقط رویداد رشته‌ای + قرارداد core-user — هیچ ارجاعی به
 * کلاس‌های ماژول shop نیست (هم‌زیستی کیت‌ها بدون وابستگی سخت).
 *
 * @api 1.0
 */
final class ShopContactSync
{
    /** @param array<string, mixed> $config کلید config('crm.sync') */
    public function __construct(
        private readonly ContactService $contacts,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    public function onShopOrderPaid(string $event, mixed $payload): void
    {
        if (!(bool) ($this->config['shop_orders'] ?? true)) {
            return;
        }

        $payload = (array) $payload;
        $customerId = (int) ($payload['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return;
        }

        try {
            $snapshot = $this->users->findById($customerId);
            if ($snapshot === null) {
                return;
            }

            $data = \is_array($snapshot) ? $snapshot : (array) $snapshot;

            $name = \trim((string) ($data['name'] ?? ($data['display_name'] ?? '')));
            $parts = $name === '' ? ['مشتری'] : ((\preg_split('/\s+/', $name) ?: ['مشتری']));

            $this->contacts->syncFromExternal('shop', [
                'user_id' => $customerId,
                'first_name' => (string) $parts[0],
                'last_name' => \implode(' ', \array_slice($parts, 1)),
                'email' => (string) ($data['email'] ?? ''),
                'mobile' => isset($data['mobile']) ? (string) $data['mobile'] : null,
                'custom_fields' => [
                    'last_order' => (string) ($payload['order_number'] ?? ''),
                    'last_order_total' => (int) ($payload['total_minor'] ?? 0),
                    'shop_customer' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('crm.sync.shop_failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
