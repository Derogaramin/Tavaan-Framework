<?php

/**
 * تَوان | Tavaan — نمونهٔ پرداخت: کیف پول داخلی (M7 / DOC-073)
 *
 * اجرا: php examples/payment/wallet-checkout.php
 *
 * نمایش: purchase → verify (🔒 با مبلغ محلی) → رویداد payment.succeeded
 * → refund. بدون شبکه (درگاه wallet داخلی).
 *
 * برای درگاه واقعی (زرین‌پال): ZARINPAL_MERCHANT را در .env بگذارید و
 * Payment::purchase(...) را با gateway پیش‌فرض صدا کنید.
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\Payment\PaymentManager;
use Tavaan\Engine\Payment\PurchaseRequest;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();

$config = $app->make('config');
$config->set('database.default', 'sqlite_memory');
$config->set('database.connections.sqlite_memory', [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
]);
$config->set('payment.default', 'wallet');
$config->set('payment.gateways.wallet.accounts', ['user-42' => 2_500_000]);

// ساخت جدول تراکنش‌ها (در اپلیکیشن واقعی: tvn migrate)
$migration = require \base_path('engines/payment/migrations/2026_10_17_000002_create_payment_transactions_table.php');
$migration->up();

/** @var \Tavaan\Core\Contracts\Events\DispatcherInterface $events */
$events = $app->make('events');
$events->listen('payment.succeeded', static function (string $name, array $payload): void {
    echo '  🔔 رویداد payment.succeeded — تراکنش ' . $payload['transaction'] . PHP_EOL;
});
$events->listen('payment.refunded', static function (string $name, array $payload): void {
    echo '  🔔 رویداد payment.refunded — مبلغ ' . \number_format((float) $payload['amount']) . PHP_EOL;
});

/** @var PaymentManager $payments */
$payments = $app->make('payment');

// ── ۱) Purchase ───────────────────────────────────────────────────────────
$result = $payments->purchase(new PurchaseRequest(
    amount: Money::of(750_000, 'IRT'),          // ۷۵۰٬۰۰۰ تومان
    callbackUrl: 'http://localhost:8000/payment/callback',
    orderId: 'ORD-42',
    meta: ['user_id' => 'user-42', 'order_type' => 'shop.order'],
), 'wallet');

echo 'purchase: ' . ($result->success ? '✅' : '❌ ' . $result->errorCode) . PHP_EOL;
echo '  authority: ' . $result->authority . PHP_EOL;

// ── ۲) Verify (🔒 مبلغ از تراکنش محلی خوانده می‌شود) ──────────────────────
$verify = $payments->verify($result->authority);
echo 'verify: ' . $verify->status->value . PHP_EOL;

$wallet = $payments->gateway('wallet');
echo 'موجودی کیف پول پس از پرداخت: ' . \number_format($wallet->balanceOf('user-42')) . PHP_EOL;

// ── ۳) 🔒 Idempotency: verify مجدد بدون تماس با درگاه/اثر مضاعف ──────────
$again = $payments->verify($result->authority);
echo 'verify مجدد (بازگشت وضعیت ذخیره‌شده): ' . $again->status->value . PHP_EOL;

// ── ۴) Refund ─────────────────────────────────────────────────────────────
$tx = $payments->findByAuthority($result->authority);
$refund = $payments->refund($tx);
echo 'refund: ' . ($refund->success ? '✅' : '❌') . PHP_EOL;
echo 'موجودی پس از بازگشت: ' . \number_format($wallet->balanceOf('user-42')) . PHP_EOL;

Model::flushTestState();
