# DOC-073 — موتور Payment | درگاه پرداخت

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-073 |
| Engine ID | `tavaan.engine.payment` |
| Namespace | `Tavaan\Engine\Payment` |
| اولویت | **P2** — فاز M7 |
| وابستگی | `http-client`, `database`, `event`, `crypt` |
| Facade | `Payment` |
| LOC | ۱۸۰۰ |

## ۱. هدف
انتزاع یکپارچهٔ درگاه‌های پرداخت ایرانی و بین‌المللی با یک Contract مشترک،
مدیریت تراکنش، Verify، Refund، Callback امن و Idempotency.

## ۲. درگاه‌ها
| دسته | درگاه‌ها |
|---|---|
| ایرانی | زرین‌پال (ZarinPal)، به‌پرداخت ملت، سامان، پارسیان، پی‌پال (PayPing)، آیدی‌پی، سداد، ملی |
| بین‌المللی | Stripe، PayPal (Adapter) |
| کیف پول | داخلی (Wallet) |

## ۳. Contract مشترک
```php
interface GatewayInterface
{
    public function purchase(PurchaseRequest $request): PurchaseResult;   // → redirect URL
    public function verify(VerifyRequest $request): PaymentResult;         // تأیید پس از بازگشت
    public function refund(RefundRequest $request): RefundResult;
    public function inquire(string $authority): PaymentStatus;             // استعلام
    public function name(): string;
}

final readonly class PurchaseRequest
{
    public function __construct(
        public Money $amount, public string $callbackUrl,
        public string $mobile, public ?string $email,
        public ?string $description, public string $orderId,
        public array $meta = [],
    ) {}
}

final readonly class PurchaseResult
{
    public function __construct(
        public string $authority,      // کد رهگیری درگاه
        public string $redirectUrl,    // URL پرداخت
        public bool $success,
        public ?string $errorCode = null,
    ) {}
}

final readonly class PaymentResult
{
    public function __construct(
        public PaymentStatus $status,  // Success|Failed|Canceled|Pending
        public ?string $referenceId,   // کد رهگیری بانک
        public ?string $cardPan,       // masked: 6037****1234
        public Money $amount,
        public string $authority,
        public array $raw = [],
    ) {}
}
```

## ۴. جریان پرداخت
```
1. Purchase:
   کاربر → checkout → Payment::purchase(order)
     → gateway.purchase(request) → redirectUrl
     → ثبت Transaction (status=pending, authority)
     → redirect کاربر به درگاه

2. Callback (بازگشت کاربر):
   /payment/callback?authority=X&status=OK
     → Payment::verify(authority)
     → gateway.verify() → PaymentResult
     → اگر success: Transaction.status=success + رویداد payment.succeeded
     → اگر failed: Transaction.status=failed + رویداد payment.failed
     → redirect به نتیجه

3. Server-to-Server Callback (IPN/Webhook):
   POST /payment/notify/{gateway}
     → بررسی امضا 🔒
     → verify + ثبت

4. Refund:
   Payment::refund(transaction) → gateway.refund()
```

## ۵. پیاده‌سازی ZarinPal (نمونه)
```php
final class ZarinPalGateway implements GatewayInterface
{
    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $response = Http::asJson()->timeout(30)->post($this->apiUrl . '/payment/request.json', [
            'merchant_id' => $this->merchantId,
            'amount'      => $request->amount->inToman(),      // 🔒 تومان نه ریال
            'callback_url'=> $request->callbackUrl,
            'description' => $request->description,
            'mobile'      => $request->mobile,
            'email'       => $request->email,
        ]);

        $data = $response->json();
        if (($data['data']['code'] ?? -1) !== 100) {
            return new PurchaseResult('', '', false, (string)($data['data']['code'] ?? 'unknown'));
        }
        $authority = $data['data']['authority'];
        return new PurchaseResult($authority, $this->paymentUrl . '/' . $authority, true);
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        $response = Http::asJson()->post($this->apiUrl . '/payment/verify.json', [
            'merchant_id' => $this->merchantId,
            'amount'      => $request->amount->inToman(),
            'authority'   => $request->authority,
        ]);
        $data = $response->json()['data'] ?? [];
        $code = $data['code'] ?? -1;

        return match (true) {
            $code === 100 => new PaymentResult(PaymentStatus::Success, $data['ref_id'] ?? null,
                $this->maskPan($data['card_pan'] ?? ''), $request->amount, $request->authority, $data),
            $code === 101 => new PaymentResult(PaymentStatus::AlreadyVerified, null, null, $request->amount, $request->authority, $data),
            default => new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, $data),
        };
    }

    private function maskPan(string $pan): string
    {
        return \strlen($pan) >= 8 ? \substr($pan, 0, 6) . '****' . \substr($pan, -4) : '';
    }
}
```

## ۶. جدول تراکنش
```sql
CREATE TABLE tvn_payment_transactions (
  id CHAR(26) PRIMARY KEY,
  gateway VARCHAR(50) NOT NULL,
  order_type VARCHAR(190), order_id VARCHAR(64),
  amount BIGINT NOT NULL, currency CHAR(3) NOT NULL,
  authority VARCHAR(100), reference_id VARCHAR(100),
  status VARCHAR(20) NOT NULL,          -- pending|success|failed|canceled|refunded
  card_pan VARCHAR(30),                 -- masked
  callback_payload JSON,
  idempotency_key CHAR(64) NOT NULL,
  paid_at DATETIME(3), created_at DATETIME(3) NOT NULL,
  UNIQUE KEY uk_idem (idempotency_key),
  KEY idx_authority (gateway, authority), KEY idx_order (order_type, order_id)
) ENGINE=InnoDB;
```

## ۷. امنیت 🔒
| تهدید | کنترل |
|---|---|
| Callback جعلی | بررسی امضا/HMAC در IPN + verify از طریق API درگاه (نه اعتماد به status بازگشتی) |
| Amount tampering | 🔒 مبلغ از Transaction محلی، نه از callback |
| Replay | Idempotency-Key + authority یکتا |
| Double payment | verify یک‌بار + status check |
| نشت PAN | mask (فقط ۶ اول + ۴ آخر) |
| SSRF | SSRF guard در http-client |
| Credential نشت | merchant_id/keys در ENV، encrypted |

```php
// 🔒 حیاتی: هرگز به status بازگشتی از URL اعتماد نکن
public function handleCallback(Request $request): Response
{
    $authority = $request->input('Authority');
    $status = $request->input('Status');

    $transaction = Transaction::byAuthority($authority);
    if ($transaction === null) { throw new PaymentNotFoundException(); }

    // Idempotency
    if ($transaction->status === 'success') {
        return redirect()->route('payment.result', $transaction);   // قبلاً پردازش شده
    }

    // 🔒 verify از طریق API درگاه (با مبلغ محلی)
    $result = $this->gateway($transaction->gateway)->verify(new VerifyRequest(
        authority: $authority, amount: $transaction->money(),
    ));

    $transaction->updateFromResult($result);
    $transaction->save();

    if ($result->status === PaymentStatus::Success) {
        Event::dispatch(new PaymentSucceeded($transaction->id));
    }
    return redirect()->route('payment.result', $transaction);
}
```

## ۸. پیکربندی
```php
// config/payment.php
return [
    'default' => env_string('TVN_PAYMENT_GATEWAY', 'zarinpal'),
    'currency' => env_string('TVN_PAYMENT_CURRENCY', 'IRT'),   // تومان
    'gateways' => [
        'zarinpal' => [
            'merchant_id' => env_string('ZARINPAL_MERCHANT'),
            'sandbox' => env_bool('ZARINPAL_SANDBOX', false),
            'api_url' => 'https://api.zarinpal.com/pg/v4',
        ],
        'behpardakht' => ['terminal'=>env_string('BPM_TERMINAL'), 'username'=>/*…*/, 'password'=>/*…*/],
        'wallet' => ['enabled'=>true],
    ],
    'callback_url' => env_string('APP_URL') . '/payment/callback',
    'notify_url' => env_string('APP_URL') . '/payment/notify',
    'idempotency_ttl' => 86400,
];
```

## ۹. تست
| T-01 | purchase → redirect URL |
| T-02 | verify success/failed/already |
| T-03 | Callback با Idempotency (دو بار → یک اثر) |
| T-04 | Amount tampering → رد (مبلغ محلی) |
| T-05 | Callback جعلی بدون verify → رد |
| T-06 | Refund |
| T-07 | PAN mask |
| T-08 | چند درگاه با یک Contract |
| T-09 | Sandbox mode |

## ۱۰. معیار پذیرش 🧪
- AC-PAY-01..09. AC-PAY-10: افزودن درگاه جدید فقط با پیاده‌سازی `GatewayInterface`.

## ۱۱. مراجع
| منبع | تفاوت |
|---|---|
| shetabit/multipay | درگاه‌های ایرانی | خودنوشته، بدون وابستگی |
| Laravel Cashier | Stripe | افزودن درگاه‌های ایرانی |
| مستندات ZarinPal/Behpardakht | API | — |

## ۱۲. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.payment.v1
engine:
  id: tavaan.engine.payment
  namespace: Tavaan\Engine\Payment
  path: engines/payment
  priority: P2
  phase: M7
  depends_on: [http-client, database, event, crypt]
  facade: {name: Payment, accessor: payment}
  tables: [tvn_payment_transactions]
  events_emitted: [payment.succeeded, payment.failed, payment.refunded]
  gateways: [zarinpal, behpardakht, saman, parsian, payping, idpay, sadad, melli, stripe, wallet]
  config_file: config/payment.php
  public_api:
    - class: PaymentManager
      methods: [purchase, verify, refund, inquire, gateway]
  loc_estimate: 1800
```
