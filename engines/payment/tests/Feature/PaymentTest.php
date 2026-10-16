<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Tests\Feature;

require_once __DIR__ . '/../Fixtures/PaymentFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Facade\Facades\Http;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\Payment\Exceptions\PaymentException;
use Tavaan\Engine\Payment\Exceptions\PaymentNotFoundException;
use Tavaan\Engine\Payment\Exceptions\SignatureMismatchException;
use Tavaan\Engine\Payment\Gateways\ZarinPalGateway;
use Tavaan\Engine\Payment\PaymentManager;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PurchaseRequest;
use Tavaan\Engine\Payment\Tests\Fixtures\FakeGateway;
use Tavaan\Engine\Payment\Transaction;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Payment — DOC-073 §9 (T-01..T-09) + §10 (AC-PAY-10).
 */
final class PaymentTest extends TestCase
{
    private Application $app;

    private PaymentManager $payments;

    /** @var \Tavaan\Core\Contracts\Events\DispatcherInterface */
    private mixed $events;

    /** @var array<string, int> */
    private array $seen = [];

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        Model::flushTestState();

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $config->set('payment.default', 'zarinpal');
        $config->set('payment.currency', 'IRT');
        $config->set('payment.gateways.zarinpal.merchant_id', 'test-merchant-id');
        $config->set('payment.app_url', 'https://shop.test');
        $config->set('payment.result_url', '/payment/result?transaction={id}&status={status}');

        $this->migrate();

        $this->events = $this->app->make('events');
        $this->seen = [];
        foreach (['payment.initiated', 'payment.succeeded', 'payment.failed', 'payment.refunded'] as $name) {
            $this->events->listen($name, function (string $event) {
                $this->seen[$event] = ($this->seen[$event] ?? 0) + 1;
            });
        }

        $this->payments = $this->app->make('payment');
    }

    protected function tearDown(): void
    {
        Http::fake(null);
        Http::real();
        Clock::setTestNow(null);
        Model::flushTestState();
    }

    private function migrate(): void
    {
        static $migration = null;
        $migration ??= require \base_path('engines/payment/migrations/2026_10_17_000002_create_payment_transactions_table.php');
        $migration->up();
    }

    /** manager تازه با config زنده (برای تست‌هایی که config را پس از boot تغییر می‌دهند). */
    private function freshManager(): PaymentManager
    {
        return new PaymentManager(
            (array) $this->app->make('config')->get('payment'),
            $this->events,
            $this->app->make('http.client'),
        );
    }

    private function request(int $amount = 150_000, string $orderId = 'order-1', array $meta = []): PurchaseRequest
    {
        return new PurchaseRequest(
            amount: Money::of($amount, 'IRT'),
            callbackUrl: 'https://shop.test/payment/callback',
            mobile: '09121234567',
            email: 'user@example.com',
            description: 'سفارش تست',
            orderId: $orderId,
            meta: $meta,
        );
    }

    /** تعداد درخواست‌های ثبت‌شده با الگوی URL. */
    private function sentCount(string $urlPart): int
    {
        $count = 0;
        foreach (Http::recorded() as $record) {
            if (\str_contains($record['request']->url(), $urlPart)) {
                $count++;
            }
        }

        return $count;
    }

    // ─── T-01: purchase → redirect URL ─────────────────────────────────────

    public function testPurchaseReturnsRedirectAndPersistsPending(): void
    {
        Http::fake(['*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A00BC4']], 200)]);

        $result = $this->payments->purchase($this->request());

        self::assertTrue($result->success);
        self::assertSame('A00BC4', $result->authority);
        self::assertStringContainsString('payment.zarinpal.com/pg/StartPay/A00BC4', $result->redirectUrl);

        $tx = $this->payments->findByAuthority('A00BC4');
        self::assertNotNull($tx);
        self::assertTrue($tx->isPending());
        self::assertSame(150_000, $tx->money()->amount);
        self::assertSame('IRT', $tx->money()->currency);
        self::assertSame('zarinpal', $tx->gateway());
        self::assertSame('order-1', $tx->orderId());
        self::assertSame(64, \strlen((string) $tx->getAttribute('idempotency_key')));

        self::assertSame(1, $this->seen['payment.initiated'] ?? 0);
    }

    public function testPurchaseReplayIsIdempotent(): void
    {
        Http::fake(['*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A00BC4']], 200)]);

        $first = $this->payments->purchase($this->request());
        $second = $this->payments->purchase($this->request());

        self::assertSame($first->authority, $second->authority);
        // 🔒 فقط یک تماس با درگاه
        self::assertSame(1, $this->sentCount('payment/request.json'));
    }

    public function testPurchaseAfterSuccessRejected(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A00BC4']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 999]], 200),
        ]);

        $this->payments->purchase($this->request());
        $this->payments->verify('A00BC4');

        // 🔒 Double payment: خرید مجدد روی سفارش پرداخت‌شده
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageId('payment.already_paid');
        $this->payments->purchase($this->request());
    }

    public function testPurchaseFailurePersistedAsFailed(): void
    {
        Http::fake(['*payment/request.json' => Http::response(['data' => ['code' => -9, 'message' => 'خطا'], 'errors' => ['code' => -9]], 400)]);

        $result = $this->payments->purchase($this->request(amount: 1000, orderId: 'order-f'));

        self::assertFalse($result->success);
        self::assertSame('-9', $result->errorCode);
        self::assertSame(1, $this->seen['payment.failed'] ?? 0);
    }

    public function testOrderIdRequired(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageId('payment.order_id_required');
        $this->payments->purchase($this->request(orderId: ''));
    }

    // ─── T-02: verify success/failed/already ──────────────────────────────

    public function testVerifySuccessUpdatesTransaction(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A1']], 200),
            '*payment/verify.json' => Http::response(['data' => [
                'code' => 100, 'ref_id' => 12345, 'card_pan' => '6037991234567890',
            ]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-v1'));
        $result = $this->payments->verify('A1');

        self::assertTrue($result->isSuccess());
        self::assertSame('12345', $result->referenceId);
        // 🔒 PAN mask (T-07)
        self::assertSame('603799****7890', $result->cardPan);

        $tx = $this->payments->findByAuthority('A1');
        self::assertNotNull($tx);
        self::assertTrue($tx->isSuccess());
        self::assertNotNull($tx->getAttribute('paid_at'));
        self::assertSame('603799****7890', $tx->cardPan());   // 🔒 mask در DB
        self::assertSame(1, $this->seen['payment.succeeded'] ?? 0);
    }

    public function testVerifyTwiceIsIdempotent(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A2']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 77]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-v2'));
        $this->payments->verify('A2');
        $second = $this->payments->verify('A2');

        self::assertTrue($second->isSuccess());
        // 🔒 verify دوم بدون تماس با درگاه
        self::assertSame(1, $this->sentCount('payment/verify.json'));
        self::assertSame(1, $this->seen['payment.succeeded'] ?? 0);
    }

    public function testVerifyFailedAndCanceledCodes(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A3']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => -21]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-v3'));
        $result = $this->payments->verify('A3');

        self::assertSame(PaymentStatus::Canceled, $result->status);
        $tx = $this->payments->findByAuthority('A3');
        self::assertNotNull($tx);
        self::assertSame('canceled', $tx->status());
        self::assertSame(1, $this->seen['payment.failed'] ?? 0);
    }

    public function testVerifyAlreadyVerifiedCode101(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A4']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => 101]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-v4'));
        $result = $this->payments->verify('A4');

        self::assertTrue($result->isAlreadyVerified());
        $tx = $this->payments->findByAuthority('A4');
        self::assertNotNull($tx);
        self::assertTrue($tx->isSuccess());   // 🔒 وضعیت success حفظ/ثبت شد
    }

    // ─── T-03: Callback با Idempotency ────────────────────────────────────

    public function testCallbackIdempotencyTwoCallsOneEffect(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'CB1']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 555]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-cb'));

        $first = $this->payments->handleCallback(['Authority' => 'CB1', 'Status' => 'OK']);
        self::assertFalse($first->alreadyProcessed);
        self::assertTrue($first->isSuccess());
        self::assertStringContainsString('status=success', $first->redirectUrl);
        self::assertStringContainsString('https://shop.test/payment/result', $first->redirectUrl);

        $second = $this->payments->handleCallback(['Authority' => 'CB1', 'Status' => 'OK']);
        self::assertTrue($second->alreadyProcessed);

        // 🔒 دو callback → یک verify، یک رویداد
        self::assertSame(1, $this->sentCount('payment/verify.json'));
        self::assertSame(1, $this->seen['payment.succeeded'] ?? 0);
    }

    // ─── T-04: Amount tampering → رد (مبلغ محلی) ──────────────────────────

    public function testCallbackAmountTamperingUsesLocalAmount(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'T1']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 1]], 200),
        ]);

        $this->payments->purchase($this->request(amount: 150_000, orderId: 'order-t'));

        // 🔒 کاربر مبلغ را در callback دستکاری می‌کند
        $this->payments->handleCallback(['Authority' => 'T1', 'Status' => 'OK', 'amount' => '1']);

        // درخواست verify باید با مبلغ محلی (۱۵۰٬۰۰۰ تومان) رفته باشد
        $found = false;
        foreach (Http::recorded() as $record) {
            if (!\str_contains($record['request']->url(), 'verify.json')) {
                continue;
            }
            $body = \json_decode((string) $record['request']->body(), true);
            self::assertSame(150_000, (int) ($body['amount'] ?? -1));
            self::assertSame('test-merchant-id', (string) ($body['merchant_id'] ?? ''));
            $found = true;
        }
        self::assertTrue($found);
    }

    // ─── T-05: Callback جعلی بدون verify → رد ────────────────────────────

    public function testForgedCallbackStatusOkButVerifyFails(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'F1']], 200),
            // 🔒 درگاه می‌گوید ناموفق — Status=OK در URL بی‌ارزش است
            '*payment/verify.json' => Http::response(['data' => ['code' => -1]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-f1'));
        $outcome = $this->payments->handleCallback(['Authority' => 'F1', 'Status' => 'OK']);

        self::assertFalse($outcome->isSuccess());
        self::assertSame(PaymentStatus::Failed, $outcome->status);
        self::assertSame(0, $this->seen['payment.succeeded'] ?? 0);

        $tx = $this->payments->findByAuthority('F1');
        self::assertNotNull($tx);
        self::assertSame('failed', $tx->status());
    }

    public function testCallbackUnknownAuthorityRejected(): void
    {
        $this->expectException(PaymentNotFoundException::class);
        $this->expectExceptionMessageId('payment.not_found');
        $this->payments->handleCallback(['Authority' => 'GHOST', 'Status' => 'OK']);
    }

    // ─── T-06: Refund ─────────────────────────────────────────────────────

    public function testRefundFlow(): void
    {
        Http::fake([
            '*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'R1']], 200),
            '*payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 31]], 200),
            '*payment/refund.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 777]], 200),
        ]);

        $this->payments->purchase($this->request(orderId: 'order-r1'));
        $this->payments->verify('R1');

        $tx = $this->payments->findByAuthority('R1');
        self::assertNotNull($tx);

        $result = $this->payments->refund($tx);
        self::assertTrue($result->success);
        self::assertSame('777', $result->referenceId);

        $refunded = $this->payments->findTransaction((string) $tx->getKey());
        self::assertNotNull($refunded);
        self::assertTrue($refunded->isRefunded());
        self::assertSame(1, $this->seen['payment.refunded'] ?? 0);

        // refund دوباره → رد
        try {
            $this->payments->refund($refunded);
            self::fail('Expected not_refundable');
        } catch (PaymentException $e) {
            self::assertSame('payment.not_refundable', $e->messageId);
        }

        // مبلغ verify شده در refund ارسالی = مبلغ محلی
        self::assertSame(1, $this->sentCount('payment/refund.json'));
    }

    // ─── T-07: PAN mask ───────────────────────────────────────────────────

    public function testPanMasking(): void
    {
        self::assertSame('603799****7890', ZarinPalGateway::maskPan('6037991234567890'));
        self::assertSame('603799****7890', ZarinPalGateway::maskPan('6037-9912-3456-7890'));
        self::assertSame('603799****7890', ZarinPalGateway::maskPan('6037 9912 3456 7890'));
        self::assertSame('', ZarinPalGateway::maskPan('123'));
        self::assertSame('', ZarinPalGateway::maskPan(''));
    }

    // ─── T-08: چند درگاه با یک Contract ───────────────────────────────────

    public function testWalletGatewayFullCycle(): void
    {
        $this->app->make('config')->set('payment.gateways.wallet.accounts', ['user-9' => 500_000]);
        $manager = $this->freshManager();

        $purchase = $manager->purchase(
            $this->request(amount: 100_000, orderId: 'wallet-1', meta: ['user_id' => 'user-9', 'order_type' => 'wallet']),
            'wallet',
        );
        self::assertTrue($purchase->success);
        self::assertTrue(\str_starts_with($purchase->authority, 'wallet_'));
        self::assertStringContainsString('Authority=', $purchase->redirectUrl);

        $verify = $manager->verify($purchase->authority);
        self::assertTrue($verify->isSuccess());

        // 🔒 کسر موجودی فقط در verify
        $wallet = $manager->gateway('wallet');
        self::assertInstanceOf(\Tavaan\Engine\Payment\Gateways\WalletGateway::class, $wallet);
        self::assertSame(400_000, $wallet->balanceOf('user-9'));

        // refund → بازگشت موجودی (meta خرید باید پس از verify هم سالم بماند)
        $tx = $manager->findByAuthority($purchase->authority);
        self::assertNotNull($tx);
        $refund = $manager->refund($tx);
        self::assertTrue($refund->success);
        self::assertSame(500_000, $wallet->balanceOf('user-9'));
        self::assertTrue($manager->findByAuthority($purchase->authority)?->isRefunded() ?? false);

        // موجودی ناکافی
        $poor = $manager->purchase(
            $this->request(amount: 900_000, orderId: 'wallet-2', meta: ['user_id' => 'user-9']),
            'wallet',
        );
        self::assertFalse($poor->success);
        self::assertSame('wallet.insufficient_funds', $poor->errorCode);
    }

    public function testIdPayGatewayWithHeaders(): void
    {
        $this->app->make('config')->set('payment.gateways.idpay.api_key', 'idpay-test-key');
        $manager = $this->freshManager();

        Http::fake(['*webapp/request' => Http::response(['id' => 'IDP-1', 'link' => 'https://idpay.ir/p/ws/abc'], 201)]);

        $result = $manager->purchase($this->request(amount: 50_000, orderId: 'idp-1'), 'idpay');
        self::assertTrue($result->success);
        self::assertSame('IDP-1', $result->authority);
        self::assertSame('https://idpay.ir/p/ws/abc', $result->redirectUrl);

        // هدرهای API ارسال شدند
        $sawKey = false;
        foreach (Http::recorded() as $record) {
            if (\str_contains($record['request']->url(), 'webapp/request')) {
                $headers = $record['request']->headers();
                $sawKey = ($headers['x-api-key'] ?? '') === 'idpay-test-key';
            }
        }
        self::assertTrue($sawKey);
    }

    public function testStripeGatewayCheckoutSession(): void
    {
        $this->app->make('config')->set('payment.gateways.stripe.secret_key', 'sk_test_x');
        $manager = $this->freshManager();

        Http::fake(['*checkout/sessions*' => Http::response([
            'id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/pay/cs_test_1',
        ], 200)]);

        $result = $manager->purchase(new PurchaseRequest(
            amount: Money::of(2500, 'USD'),
            callbackUrl: 'https://shop.test/payment/callback',
            orderId: 'usd-1',
        ), 'stripe');

        self::assertTrue($result->success);
        self::assertSame('cs_test_1', $result->authority);
        self::assertStringContainsString('checkout.stripe.com', $result->redirectUrl);
    }

    // ─── IPN با امضا ──────────────────────────────────────────────────────

    public function testNotifyVerifiesHmacSignature(): void
    {
        $this->app->make('config')->set('payment.gateways.idpay.api_key', 'idpay-test-key');
        $manager = $this->freshManager();

        Http::fake([
            '*webapp/request' => Http::response(['id' => 'IDP-9', 'link' => 'https://idpay.ir/p/ws/z'], 201),
            '*webapp/verify' => Http::response(['status' => 200, 'ref_id' => 'R-9'], 200),
        ]);

        $manager->purchase($this->request(amount: 30_000, orderId: 'idp-9'), 'idpay');

        $body = (string) \json_encode(['id' => 'IDP-9', 'status' => 200, 'order_id' => 'idp-9']);
        $signature = \hash_hmac('sha256', $body, 'idpay-test-key');

        // 🔒 امضای نامعتبر → ۴۰۳ معنایی
        try {
            $manager->notify('idpay', $body, ['X-Idpay-Signature' => 'deadbeef']);
            self::fail('Expected signature mismatch');
        } catch (SignatureMismatchException $e) {
            self::assertSame('payment.signature_mismatch', $e->messageId);
        }

        // امضای معتبر → verify کامل
        $outcome = $manager->notify('idpay', $body, ['X-Idpay-Signature' => $signature]);
        self::assertTrue($outcome->isSuccess());
        self::assertSame(1, $this->seen['payment.succeeded'] ?? 0);
    }

    // ─── T-09: Sandbox ────────────────────────────────────────────────────

    public function testSandboxModeUsesSandboxHost(): void
    {
        $this->app->make('config')->set('payment.gateways.zarinpal.sandbox', true);
        $manager = $this->freshManager();

        Http::fake(['*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'SB-1']], 200)]);

        $result = $manager->purchase($this->request(orderId: 'order-sb'));
        self::assertStringContainsString('sandbox.zarinpal.com/pg/StartPay/SB-1', $result->redirectUrl);
    }

    // ─── AC-PAY-10: درگاه سفارشی ──────────────────────────────────────────

    public function testExtendCustomGateway(): void
    {
        $fake = new FakeGateway();
        $this->payments->extend('fakegw', static fn (): FakeGateway => $fake);

        $purchase = $this->payments->purchase($this->request(amount: 200_000, orderId: 'fx-1'), 'fakegw');
        self::assertTrue($purchase->success);
        self::assertSame('fake-fx-1', $purchase->authority);

        $verify = $this->payments->verify('fake-fx-1');
        self::assertTrue($verify->isSuccess());
        self::assertSame('603799****4321', $verify->cardPan);

        // 🔒 مبلغ verify = مبلغ محلی تراکنش
        self::assertSame([
            ['op' => 'purchase', 'authority' => 'fx-1', 'amount' => 200_000],
            ['op' => 'verify', 'authority' => 'fake-fx-1', 'amount' => 200_000],
        ], $fake->calls);

        $tx = $this->payments->findByAuthority('fake-fx-1');
        self::assertNotNull($tx);
        self::assertSame('fakegw', $tx->gateway());
        self::assertTrue($tx->isSuccess());
    }

    // ─── سایر درگاه‌ها (Contract یکسان — مسیرهای XML/SOAP) ────────────────

    public function testBehpardakhtSoapGatewayPurchaseAndVerify(): void
    {
        $config = $this->app->make('config');
        $config->set('payment.gateways.behpardakht.terminal_id', 'T-100');
        $config->set('payment.gateways.behpardakht.username', 'u');
        $config->set('payment.gateways.behpardakht.password', 'p');
        $manager = $this->freshManager();

        Http::fake([
            '*pgwchannel/services/pgw*' => Http::response(
                '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                . '<ns1:PayRequestResponse><ResCode>0</ResCode><RefId>9988776655</RefId></ns1:PayRequestResponse>'
                . '</soap:Body></soap:Envelope>',
                200,
                ['Content-Type' => 'text/xml'],
            ),
        ]);

        $purchase = $manager->purchase($this->request(orderId: 'bpm-1'), 'behpardakht');
        self::assertTrue($purchase->success);
        self::assertSame('9988776655', $purchase->authority);
        self::assertStringContainsString('startpay.mellat?RefId=9988776655', $purchase->redirectUrl);

        // verify: اولین پاسخ PayVerification (ResCode=0 + SaleReferenceId)
        Http::fake([
            '*pgwchannel/services/pgw*' => Http::response(
                '<soap:Envelope><soap:Body><ns1:PayVerificationResponse><ResCode>0</ResCode>'
                . '<SaleReferenceId>123123</SaleReferenceId></ns1:PayVerificationResponse></soap:Body></soap:Envelope>',
                200,
            ),
        ]);
        $verify = $manager->verify($purchase->authority, 'behpardakht');
        self::assertTrue($verify->isSuccess());
        self::assertSame('123123', $verify->referenceId);
    }

    public function testBehpardakhtRejectsBadResCode(): void
    {
        $config = $this->app->make('config');
        $config->set('payment.gateways.behpardakht.terminal_id', 'T-100');
        $config->set('payment.gateways.behpardakht.username', 'u');
        $config->set('payment.gateways.behpardakht.password', 'p');
        $manager = $this->freshManager();

        Http::fake([
            '*pgwchannel/services/pgw*' => Http::response(
                '<soap:Envelope><soap:Body><ns1:PayRequestResponse><ResCode>41</ResCode></ns1:PayRequestResponse></soap:Body></soap:Envelope>',
                200,
            ),
        ]);

        $purchase = $manager->purchase($this->request(orderId: 'bpm-2'), 'behpardakht');
        self::assertFalse($purchase->success);
        self::assertSame('41', $purchase->errorCode);
    }

    public function testSamanGatewayTokenFlow(): void
    {
        $this->app->make('config')->set('payment.gateways.saman.merchant_id', 'sep-merchant');
        $manager = $this->freshManager();

        Http::fake([
            '*Payments/GetToken*' => Http::response(['Token' => 'TOK-1', 'ErrorCode' => 0], 200),
            '*Payments/Verify*' => Http::response(['ResultCode' => 1, 'TraceNo' => 'TR-9', 'SecurePan' => '6037991234567890'], 200),
        ]);

        $purchase = $manager->purchase($this->request(orderId: 'sep-1'), 'saman');
        self::assertTrue($purchase->success);
        self::assertStringContainsString('sep.ir/Payments/Redirect?Token=TOK-1', $purchase->redirectUrl);

        $verify = $manager->verify('TOK-1', 'saman', ['RefNum' => 'RN-55']);
        self::assertTrue($verify->isSuccess());
        self::assertSame('603799****7890', $verify->cardPan);
        self::assertSame('TR-9', $verify->referenceId);
    }

    public function testParsianGatewayFlow(): void
    {
        $this->app->make('config')->set('payment.gateways.parsian.login_account', 'pars-login');
        $manager = $this->freshManager();

        Http::fake([
            '*SalesPaymentRequest*' => Http::response(
                '<Envelope><Body><Rs>0</Rs><Token>PTOK-7</Token></Body></Envelope>',
                200,
            ),
        ]);

        $purchase = $manager->purchase($this->request(orderId: 'par-1'), 'parsian');
        self::assertTrue($purchase->success);
        self::assertSame('PTOK-7', $purchase->authority);
        self::assertStringContainsString('Token=PTOK-7', $purchase->redirectUrl);
    }

    public function testPayPingGatewayFlow(): void
    {
        $this->app->make('config')->set('payment.gateways.payping.token', 'pp-token');
        $manager = $this->freshManager();

        Http::fake([
            '*v2/pay/verify*' => Http::response(['refId' => 'REF-1', 'cardNumber' => '6037991111112222'], 200),
            '*v2/pay*' => Http::response(['code' => 'PP-CODE-1'], 200),
        ]);

        $purchase = $manager->purchase($this->request(orderId: 'pp-1'), 'payping');
        self::assertTrue($purchase->success);
        self::assertSame('PP-CODE-1', $purchase->authority);
        self::assertStringContainsString('startipg?code=PP-CODE-1', $purchase->redirectUrl);

        $verify = $manager->verify('PP-CODE-1', 'payping', ['refId' => 'REF-1']);
        self::assertTrue($verify->isSuccess());
        self::assertSame('603799****2222', $verify->cardPan);
    }

    public function testSadadSignedPayloadFlow(): void
    {
        $config = $this->app->make('config');
        $config->set('payment.gateways.sadad.merchant_id', 'sad-1');
        $config->set('payment.gateways.sadad.sign_key', 'sadad-key');
        $manager = $this->freshManager();

        Http::fake([
            '*PaymentRequest*' => Http::response(['ResCode' => 0, 'Token' => 'SAD-TOK'], 200),
        ]);

        $purchase = $manager->purchase($this->request(orderId: 'sad-1'), 'sadad');
        self::assertTrue($purchase->success);
        self::assertSame('SAD-TOK', $purchase->authority);

        // 🔒 payload امضاشده (base64.hmac) ارسال شده است
        $sawSigned = false;
        foreach (Http::recorded() as $record) {
            if (\str_contains($record['request']->url(), 'PaymentRequest')) {
                $body = \json_decode((string) $record['request']->body(), true);
                $data = (string) ($body['Data'] ?? '');
                $sawSigned = \substr_count($data, '.') === 1
                    && \base64_decode(\explode('.', $data)[0], true) !== false
                    && \strlen(\explode('.', $data)[1]) === 64;
            }
        }
        self::assertTrue($sawSigned);
    }

    // ─── متفرقه ────────────────────────────────────────────────────────────

    public function testUnknownGatewayThrows(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageId('payment.gateway_not_found');
        $this->payments->gateway('bitcoin-bank');
    }

    public function testUnconfiguredGatewayThrows(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageId('payment.gateway_not_configured');
        $this->payments->purchase($this->request(orderId: 'order-p'), 'parsian');
    }

    public function testInquireRoutesToGateway(): void
    {
        $fake = new FakeGateway();
        $this->payments->extend('fakegw', static fn (): FakeGateway => $fake);

        self::assertSame(PaymentStatus::Success, $this->payments->inquire('anything', 'fakegw'));
    }

    public function testTransactionsPersistedInDatabase(): void
    {
        Http::fake(['*payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'DB1']], 200)]);
        $this->payments->purchase($this->request(orderId: 'order-db'));

        /** @var Transaction|null $tx */
        $tx = Transaction::byAuthority('zarinpal', 'DB1');
        self::assertNotNull($tx);
        self::assertSame('pending', $tx->status());

        // idempotency_key یکتا است (unique index)
        $count = (int) $this->app->make('db')->table('tvn_payment_transactions')->count();
        self::assertSame(1, $count);
    }
}
