<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Payment\GatewayInterface;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\HttpClient\Factory;
use Tavaan\Engine\Payment\Events\PaymentFailed;
use Tavaan\Engine\Payment\Events\PaymentInitiated;
use Tavaan\Engine\Payment\Events\PaymentRefunded;
use Tavaan\Engine\Payment\Events\PaymentSucceeded;
use Tavaan\Engine\Payment\Exceptions\PaymentException;
use Tavaan\Engine\Payment\Exceptions\PaymentNotFoundException;
use Tavaan\Engine\Payment\Gateways\IdPayGateway;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Payment
 * PaymentManager — ریشهٔ Facade (`Payment::…`): purchase/verify/refund/inquire/gateway
 * + مدیریت تراکنش، 🔒 Idempotency و 🔒 Callback امن (DOC-073 §4/§7).
 *
 * اصول امنیتی پیاده‌شده:
 * - 🔒 verify همیشه با مبلغ Transaction محلی (نه مبلغ callback) — Amount tampering
 * - 🔒 هرگز به Status بازگشتی URL اعتماد نمی‌شود — verify از API درگاه
 * - 🔒 Idempotency-Key یکتا + replay بدون اثر مضاعف
 * - 🔒 PAN فقط mask‌شده ذخیره می‌شود
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentManager
{
    /** @var array<string, class-string<GatewayInterface>> درگاه‌های داخلی (DOC-073 §2) */
    private const BUILTIN = [
        'zarinpal' => Gateways\ZarinPalGateway::class,
        'idpay' => Gateways\IdPayGateway::class,
        'payping' => Gateways\PayPingGateway::class,
        'saman' => Gateways\SamanGateway::class,
        'behpardakht' => Gateways\BehpardakhtGateway::class,
        'parsian' => Gateways\ParsianGateway::class,
        'sadad' => Gateways\SadadGateway::class,
        'melli' => Gateways\MelliGateway::class,
        'stripe' => Gateways\StripeGateway::class,
        'wallet' => Gateways\WalletGateway::class,
    ];

    /** @var array<string, GatewayInterface> کش نمونه‌ها */
    private array $resolved = [];

    /** @var array<string, \Closure|class-string<GatewayInterface>> درگاه‌های سفارشی (AC-PAY-10) */
    private array $extensions = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
        private readonly ?Factory $http = null,
    ) {
    }

    // ─── درگاه‌ها ──────────────────────────────────────────────────────────

    /**
     * درگاه پیش‌فرض یا نام‌دار.
     *
     * @throws PaymentException درگاه ناشناخته
     */
    public function gateway(?string $name = null): GatewayInterface
    {
        $name = $name ?? $this->defaultGateway();

        return $this->resolved[$name] ??= $this->buildGateway($name);
    }

    /**
     * 🔌 ثبت درگاه سفارشی (AC-PAY-10: فقط پیاده‌سازی GatewayInterface لازم است).
     *
     * @param \Closure(array<string, mixed>, ?Factory): GatewayInterface|class-string<GatewayInterface> $factory
     */
    public function extend(string $name, \Closure|string $factory): void
    {
        unset($this->resolved[$name]);
        $this->extensions[$name] = $factory;
    }

    /** @return list<string> */
    public function gateways(): array
    {
        $names = \array_keys(self::BUILTIN);
        foreach (\array_keys($this->config['gateways'] ?? []) as $configured) {
            if (\is_string($configured) && !\in_array($configured, $names, true)) {
                $names[] = $configured;
            }
        }
        foreach (\array_keys($this->extensions) as $extended) {
            if (!\in_array($extended, $names, true)) {
                $names[] = $extended;
            }
        }

        return $names;
    }

    public function defaultGateway(): string
    {
        return (string) ($this->config['default'] ?? 'zarinpal');
    }

    public function defaultCurrency(): string
    {
        return (string) ($this->config['currency'] ?? 'IRT');
    }

    // ─── جریان پرداخت (DOC-073 §4) ────────────────────────────────────────

    /**
     * ۱) Purchase: ساخت تراکنش pending + گرفتن redirect URL از درگاه.
     *
     * 🔒 Idempotency: تکرار با همان order/amount → همان تراکنش pending
     * (بدون تماس مضاعف با درگاه)؛ تراکنش موفق قبلی → `payment.already_paid`.
     *
     * @param PurchaseRequest|array<string, mixed> $request
     */
    public function purchase(PurchaseRequest|array $request, ?string $gateway = null): PurchaseResult
    {
        $request = $request instanceof PurchaseRequest
            ? $request
            : PurchaseRequest::fromArray($request, $this->defaultCurrency());

        if ($request->orderId === '') {
            throw PaymentException::orderIdRequired();
        }
        if ($request->amount->amount <= 0) {
            throw PaymentException::invalidAmount('amount must be positive');
        }

        $gateway ??= $this->defaultGateway();
        $callbackUrl = $request->callbackUrl !== ''
            ? $request->callbackUrl
            : (string) ($this->config['callback_url'] ?? '');

        $idempotencyKey = $this->idempotencyKey($request, $gateway);

        // 🔒 Replay: تراکنش موجود با همین کلید
        $existing = Transaction::byIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            if ($existing->isSuccess()) {
                throw PaymentException::alreadyPaid((string) $existing->getKey());
            }
            if ($existing->isPending() && $existing->authority() !== null) {
                $payload = $existing->callbackPayload();

                return PurchaseResult::success(
                    (string) $existing->authority(),
                    (string) ($payload['redirect_url'] ?? ''),
                    $payload,
                );
            }
            // تراکنش ناموفق/لغوشده → تلاش مجدد با کلید جدید
            $idempotencyKey .= '-' . \strtolower(\substr(Ulid::generate()->toString(), -8));
        }

        $normalized = new PurchaseRequest(
            amount: $request->amount,
            callbackUrl: $callbackUrl,
            mobile: $request->mobile,
            email: $request->email,
            description: $request->description,
            orderId: $request->orderId,
            meta: $request->meta,
        );

        $result = $this->gateway($gateway)->purchase($normalized);

        $transaction = Transaction::create([
            'gateway' => $gateway,
            'order_type' => isset($normalized->meta['order_type']) ? (string) $normalized->meta['order_type'] : null,
            'order_id' => $normalized->orderId,
            'amount' => $normalized->amount->amount,
            'currency' => $normalized->amount->currency,
            'authority' => $result->authority !== '' ? $result->authority : null,
            'status' => $result->success ? PaymentStatus::Pending->value : PaymentStatus::Failed->value,
            'idempotency_key' => $idempotencyKey,
            'callback_payload' => $result->success
                ? ['redirect_url' => $result->redirectUrl, 'meta' => $normalized->meta, 'purchase' => $result->raw]
                : ['error' => $result->errorCode, 'meta' => $normalized->meta, 'purchase' => $result->raw],
            'created_at' => Clock::now()->format('Y-m-d H:i:s.v'),
        ]);

        if ($result->success) {
            $this->dispatch(new PaymentInitiated(
                (string) $transaction->getKey(),
                $gateway,
                $result->authority,
                $normalized->amount->amount,
                $normalized->amount->currency,
                $transaction->orderType(),
                $transaction->orderId(),
            ), 'payment.initiated', [
                'transaction' => (string) $transaction->getKey(),
                'gateway' => $gateway,
                'amount' => $normalized->amount->amount,
            ]);
        } else {
            $this->dispatch(new PaymentFailed(
                (string) $transaction->getKey(),
                $gateway,
                (string) $result->errorCode,
                $normalized->amount->amount,
                $normalized->amount->currency,
                $transaction->orderId(),
            ), 'payment.failed', [
                'transaction' => (string) $transaction->getKey(),
                'gateway' => $gateway,
                'reason' => (string) $result->errorCode,
            ]);
        }

        return $result;
    }

    /**
     * ۲) Verify: 🔒 با مبلغ محلی Transaction، از طریق API درگاه.
     *
     * Idempotent: تراکنش موفق → نتیجهٔ ذخیره‌شده بدون تماس با درگاه.
     *
     * @param string|Transaction      $authorityOrTx
     * @param array<string, mixed>    $callbackMeta دادهٔ callback (order_id/ref_id و …)
     */
    public function verify(string|Transaction $authorityOrTx, ?string $gateway = null, array $callbackMeta = []): PaymentResult
    {
        $transaction = $this->resolveTransaction($authorityOrTx, $gateway);

        // 🔒 Double payment: تراکنش موفق قبلی → بدون verify مجدد
        if ($transaction->isSuccess()) {
            return new PaymentResult(
                PaymentStatus::Success,
                $transaction->referenceId(),
                $transaction->cardPan(),
                $transaction->money(),
                (string) $transaction->authority(),
                $transaction->callbackPayload(),
            );
        }
        if ($transaction->isRefunded()) {
            throw PaymentException::notRefundable((string) $transaction->getKey(), $transaction->status());
        }

        $meta = [...(array) ($transaction->callbackPayload()['meta'] ?? []), ...$callbackMeta];

        $result = $this->gateway($transaction->gateway())->verify(new VerifyRequest(
            authority: (string) $transaction->authority(),
            amount: $transaction->money(),   // 🔒 مبلغ محلی — نه مبلغ callback
            meta: $meta,
        ));

        $wasSuccess = $transaction->isSuccess();
        $transaction->updateFromResult($result);
        $transaction->save();

        $this->dispatchResultEvents($transaction, $result, $wasSuccess);

        return $result;
    }

    /**
     * بازگشت user از درگاه (DOC-073 §7).
     *
     * 🔒 هرگز به `Status` داخل URL اعتماد نمی‌شود — فقط verify از API درگاه.
     *
     * @param array<string, mixed> $query پارامترهای callback
     */
    public function handleCallback(array $query, ?string $gateway = null): CallbackOutcome
    {
        $authority = (string) ($query['Authority'] ?? ($query['authority'] ?? ($query['id'] ?? ($query['token'] ?? ($query['RefNum'] ?? '')))));
        if ($authority === '') {
            throw PaymentNotFoundException::byAuthority('');
        }

        $transaction = $gateway !== null
            ? (Transaction::byAuthority($gateway, $authority) ?? throw PaymentNotFoundException::byAuthority($authority))
            : $this->findByAuthority($authority) ?? throw PaymentNotFoundException::byAuthority($authority);

        // 🔒 Idempotency: تراکنش موفق قبلی → redirect بدون verify مجدد
        if ($transaction->isSuccess() || $transaction->isRefunded()) {
            return new CallbackOutcome(
                (string) $transaction->getKey(),
                $transaction->statusEnum(),
                $this->resultUrl($transaction),
                alreadyProcessed: true,
            );
        }

        $result = $this->verify($transaction, null, $query);

        // redirect با وضعیت به‌روز (verify همان آبجکت را mutate کرده)
        return new CallbackOutcome(
            (string) $transaction->getKey(),
            $result->status === PaymentStatus::AlreadyVerified ? PaymentStatus::Success : $result->status,
            $this->resultUrl($transaction),
        );
    }

    /**
     * ۳) IPN/Webhook سرور-به-سرور (DOC-073 §4 گام ۳) با 🔒 بررسی امضا.
     *
     * @param array<string, string> $headers هدرهای درخواست (case-insensitive کلید)
     */
    public function notify(string $gateway, string $body, array $headers = []): CallbackOutcome
    {
        $instance = $this->gateway($gateway);

        // 🔒 بررسی امضا برای درگاه‌های امضادار (فعلاً IDPay)
        if ($instance instanceof IdPayGateway) {
            $signature = $this->headerValue($headers, 'x-idpay-signature');
            $instance->assertNotifySignature($body, $signature);
        }

        /** @var array<string, mixed> $payload */
        $payload = \json_decode($body, true) ?: [];
        $authority = (string) ($payload['id'] ?? ($payload['authority'] ?? ($payload['Authority'] ?? '')));
        if ($authority === '') {
            throw PaymentNotFoundException::byAuthority('');
        }

        return $this->handleCallback($payload, $gateway);
    }

    /**
     * ۴) Refund (DOC-073 §4 گام ۴).
     */
    public function refund(Transaction|string $transactionOrId, ?Money $amount = null): RefundResult
    {
        $transaction = $transactionOrId instanceof Transaction
            ? $transactionOrId
            : (Transaction::query()->where('id', $transactionOrId)->first()
                ?? throw PaymentNotFoundException::byId($transactionOrId));

        if (!$transaction->isSuccess()) {
            throw PaymentException::notRefundable((string) $transaction->getKey(), $transaction->status());
        }

        $amount ??= $transaction->money();
        $meta = (array) ($transaction->callbackPayload()['meta'] ?? []);

        $result = $this->gateway($transaction->gateway())->refund(new RefundRequest(
            authority: (string) $transaction->authority(),
            amount: $amount,
            referenceId: $transaction->referenceId(),
            meta: $meta,
        ));

        if ($result->success) {
            $transaction->setAttribute('status', PaymentStatus::Refunded->value);
            if ($result->referenceId !== null) {
                $transaction->setAttribute('reference_id', $result->referenceId);
            }
            $transaction->save();

            $this->dispatch(new PaymentRefunded(
                (string) $transaction->getKey(),
                $transaction->gateway(),
                $amount->amount,
                $amount->currency,
                $result->referenceId,
            ), 'payment.refunded', [
                'transaction' => (string) $transaction->getKey(),
                'amount' => $amount->amount,
            ]);
        }

        return $result;
    }

    /** استعلام وضعیت از درگاه. */
    public function inquire(string $authority, ?string $gateway = null): PaymentStatus
    {
        if ($gateway !== null) {
            return $this->gateway($gateway)->inquire($authority);
        }

        $transaction = $this->findByAuthority($authority);
        if ($transaction !== null) {
            return $this->gateway($transaction->gateway())->inquire($authority);
        }

        return $this->gateway()->inquire($authority);
    }

    // ─── تراکنش‌ها ─────────────────────────────────────────────────────────

    public function findTransaction(string $id): ?Transaction
    {
        /** @var Transaction|null */
        return Transaction::query()->where('id', $id)->first();
    }

    public function findByAuthority(string $authority, ?string $gateway = null): ?Transaction
    {
        $query = Transaction::query()->where('authority', $authority);
        if ($gateway !== null) {
            $query->where('gateway', $gateway);
        }

        /** @var Transaction|null */
        return $query->orderByDesc('created_at')->first();
    }

    /** آدرس صفحهٔ نتیجه (template با {id}/{status}/{order_id}/{gateway}). */
    public function resultUrl(Transaction $transaction): string
    {
        $template = (string) ($this->config['result_url'] ?? '/payment/result?transaction={id}&status={status}');
        $url = \str_replace(
            ['{id}', '{status}', '{order_id}', '{gateway}'],
            [
                \rawurlencode((string) $transaction->getKey()),
                \rawurlencode($transaction->status()),
                \rawurlencode((string) ($transaction->orderId() ?? '')),
                \rawurlencode($transaction->gateway()),
            ],
            $template,
        );

        if (\str_starts_with($url, '/')) {
            $appUrl = \rtrim((string) ($this->config['app_url'] ?? ''), '/');
            $url = $appUrl . $url;
        }

        return $url;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    // ─── داخلی ─────────────────────────────────────────────────────────────

    private function buildGateway(string $name): GatewayInterface
    {
        /** @var array<string, mixed> $gatewayConfig */
        $gatewayConfig = (array) ($this->config['gateways'][$name] ?? []);

        // ۱) extension ثبت‌شده (AC-PAY-10)
        if (isset($this->extensions[$name])) {
            $factory = $this->extensions[$name];
            $instance = $factory instanceof \Closure
                ? $factory($gatewayConfig, $this->http)
                : new $factory($gatewayConfig, $this->http);
            if (!$instance instanceof GatewayInterface) {
                throw PaymentException::gatewayNotFound($name);
            }

            return $instance;
        }

        // ۲) کلاس سفارشی از config
        $class = (string) ($gatewayConfig['class'] ?? (self::BUILTIN[$name] ?? ''));
        if ($class === '' || !\class_exists($class)) {
            throw PaymentException::gatewayNotFound($name);
        }

        $instance = new $class($gatewayConfig, $this->http);
        if (!$instance instanceof GatewayInterface) {
            throw PaymentException::gatewayNotFound($name);
        }

        return $instance;
    }

    /** @param string|Transaction $authorityOrTx */
    private function resolveTransaction(mixed $authorityOrTx, ?string $gateway): Transaction
    {
        if ($authorityOrTx instanceof Transaction) {
            return $authorityOrTx;
        }

        // شناسهٔ ULID (۲۶ کاراکتر) → lookup by id؛ وگرنه authority
        if (\strlen($authorityOrTx) === 26 && \preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $authorityOrTx) === 1) {
            $byId = $this->findTransaction($authorityOrTx);
            if ($byId !== null) {
                return $byId;
            }
        }

        $transaction = $this->findByAuthority($authorityOrTx, $gateway);

        return $transaction ?? throw PaymentNotFoundException::byAuthority($authorityOrTx);
    }

    private function idempotencyKey(PurchaseRequest $request, string $gateway): string
    {
        $explicit = $request->meta['idempotency_key'] ?? null;
        if (\is_string($explicit) && $explicit !== '') {
            return \hash('sha256', $explicit);
        }

        return \hash('sha256', \implode('|', [
            $gateway,
            $request->orderId,
            $request->amount->amount,
            $request->amount->currency,
        ]));
    }

    private function dispatchResultEvents(Transaction $transaction, PaymentResult $result, bool $wasSuccess): void
    {
        $status = $transaction->statusEnum();

        if (!$wasSuccess && ($status === PaymentStatus::Success)) {
            $this->dispatch(new PaymentSucceeded(
                (string) $transaction->getKey(),
                $transaction->gateway(),
                $transaction->referenceId(),
                $transaction->money()->amount,
                $transaction->money()->currency,
                $transaction->orderType(),
                $transaction->orderId(),
            ), 'payment.succeeded', [
                'transaction' => (string) $transaction->getKey(),
                'gateway' => $transaction->gateway(),
                'amount' => $transaction->money()->amount,
            ]);

            return;
        }

        if (\in_array($status, [PaymentStatus::Failed, PaymentStatus::Canceled], true)) {
            $this->dispatch(new PaymentFailed(
                (string) $transaction->getKey(),
                $transaction->gateway(),
                (string) ($result->raw['code'] ?? ($result->raw['status'] ?? $status->value)),
                $transaction->money()->amount,
                $transaction->money()->currency,
                $transaction->orderId(),
            ), 'payment.failed', [
                'transaction' => (string) $transaction->getKey(),
                'gateway' => $transaction->gateway(),
                'reason' => $status->value,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatch(object $event, string $name, array $payload): void
    {
        $this->events?->dispatch($event);
        $this->events?->dispatch($name, $payload);
    }

    /**
     * @param array<string, string> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (\strtolower((string) $key) === $name) {
                return (string) $value;
            }
        }

        return null;
    }
}
