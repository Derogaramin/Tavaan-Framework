# موتور پرداخت — `Payment`

> M7 — انتزاع درگاه‌های پرداخت با GatewayInterface مشترک (core/Contracts/Payment): زرین‌پال/آیدی‌پی/پی‌پینگ/سامان/به‌پرداخت/پارسیان/سداد/ملی/Stripe (خودنوشته، بدون SDK) + کیف پول داخلی (WalletStoreInterface). 🔒 امنیت: verify فقط از API درگاه با مبلغ محلی Transaction (ضد amount tampering)، عدم اعتماد به Status بازگشتی، idempotency_key یکتا (ضد replay/double payment)، PAN mask، امضای HMAC برای IPN، SSRF guard موتور http-client. routeهای /payment/callback و /payment/notify/{gateway}. درگاه سفارشی فقط با extend() یا config class (AC-PAY-10).
>
> مستند کامل: [PAYMENT](../../docs/03-engines/payment.md) • تیکت: TVN-0073 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.payment` |
| namespace | `Tavaan\Engine\Payment\` |
| Facade | `Tavaan\Core\Facade\Facades\Payment` (accessor: `payment`) |
| پیکربندی | `config/payment.php` → `config('payment.*')` |
| Provider | `Tavaan\Engine\Payment\PaymentServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `http-client`، `database`، `migration`، `orm`، `event`، `crypt`، `http`، `console` |
| فرمان‌های CLI | `tvn payment:list` |
| آمار | 34 فایل / ~3,064 خط در src • 1 فایل تست (31 متد) |

## شروع سریع

```bash
# تست‌های موتور (31 متد)
php bin/tvn test engines/payment

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Payment;

// API عمومی و مثال‌ها: docs/03-engines/payment.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/payment/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/payment.md`](../../docs/03-engines/payment.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
