# گزارش پیاده‌سازی M9 — گام ۳: کیت فروشگاه 🛒 (TVN-0093)

| متادیتا | مقدار |
|---|---|
| تیکت | TVN-0093 — Kit: Shop |
| مستند مبنا | DOC-093 (`docs/05-applications/shop.md`) + DOC-090 (blueprint کیت) |
| نسخه | 0.11.0 |
| شاخه | `m9-kits` |
| تاریخ | 2026-10-24 |
| نتیجه | ✅ ۵۶ تست جدید (۱۴۲۵ کل / ~۹۳۰۰ assertion) • arch:check ۰ نقض • security:scan ۰ بحرانی • نصب واقعی کیت + smoke HTTP سبز |

---

## ۱. دامنهٔ تحویل

**بزرگ‌ترین تیکت M9** — ۷ محدودهٔ مفهومی DOC-093 §1.1 در یک ماژول شش‌ضلعی
(مطابق خود §2 که Tax/Shipping را Gateway داخلی shop می‌داند):

| Bounded Context | پیاده‌سازی |
|---|---|
| Catalog | Product (+variant با price_delta/موجودی مستقل)، ShopCategory تو‌در‌تو، ویژگی‌های پویا JSON، تصویر شاخص (ULID رسانه)، Markdown→sanitize |
| Cart & Order | Cart مهمان/کاربر (ادغام قلم + merge هنگام ورود)، snapshot قیمت، Order با invariant مالی و ماتریس انتقال شش‌وضعیتی |
| Payment & Checkout | پورت `PaymentGatewayPort` + آداپتر Sandbox (شبیه‌ساز قطعی) + آداپتر Engine (درگاه‌های واقعی M7) • verify server-to-server با مبلغ سفارش محلی (DOC-120 §5) |
| Inventory | `InventoryService.reserve/release/adjust` — UPDATE اتمیک شرطی، همه-یا-هیچ با rollback داخلی، حرکت‌نامه، رویداد stock.low |
| Shipping/Tax | `PricingService` — درصد مالیات (+shipping_taxable)، ارسال تخت با آستانهٔ رایگان — نرخ‌ها از preset |
| Customer | `/shop/orders` (مالکیت)، لغو توسط مشتری، نقد و بررسی (پورت ماژول comment روی `shop_comments`) |

### آمار کد

| جزء | تعداد |
|---|---|
| فایل‌های ماژول `modules/shop` | ۷۴ (۱۰ migration-table در ۱ فایل، ۸ entity، ۲ enum، ۲ VO، ۸ رویداد، ۳ دامنه-servis، ۵ پورت مخزن، ۷ UseCase، ۶ آداپتر Persistence، ۲ Gateway، indexer، notifier، reader، ۲ Policy، ۱۰ کنترلر، Provider) |
| ویو (`.tv.php`) | ۱۶ (۸ ویترین + ۷ پنل + layout ادمین) |
| کیت `kits/shop` | ۱۳ فایل (kit.json، ۳ preset، theme، roles، demo، ۲ راهنما، steps) |
| تست | ۵۶ (۲۸ Unit + ۲۸ Feature) |
| مسیر | ۱۹ وب + ۱۲ API + ۲۵ ادمین |
| مجوز RBAC | ۱۴ کلید `shop.*` + ۵ نقش |

## ۲. انطباق با سناریوهای E2E (DOC-093 §10) و AC (§11)

| سناریو | تست | نتیجه |
|---|---|---|
| E2E-01 مرور→سبد→checkout→پرداخت موفق→تأیید | `testE2E01_BrowseCartCheckoutPayConfirm` | ✅ + اعلان database + sold_count + تخلیهٔ سبد |
| E2E-02 موجودی ناکافی→خطا در reserve | `testE2E02` + `testE2E08b` | ✅ rollback تراکنش، موجودی دست‌نخورده |
| E2E-03 پرداخت ناموفق→pending+retry | `testE2E03` | ✅ authority با prefix `sbx-fail-`، retry موفق |
| E2E-04 لغو→بازگشت موجودی+refund | `testE2E04`/`testE2E04b` | ✅ حرکت‌نامهٔ release + refund خودکار سفارش پرداخت‌شده |
| E2E-05 کوپن→محاسبهٔ درست | `testE2E05`/`testE2E05b` | ✅ ۱۰٪، سقف ۵۰M، انقضا، used_count |
| E2E-06 مالیات و ارسال در total | `testE2E06`/`testE2E06b` | ✅ ۹٪ + تخت ۵۰۰k + آستانهٔ رایگان |
| E2E-07 جست‌وجوی فارسی | `testE2E07` | ✅ ایندکس رویدادمحور + رقم فارسی («پارس ۱۵») + API |
| E2E-08 همزمانی آخرین موجودی | `testE2E08` + `testE2E08b` | ✅ UPDATE اتمیک — بدون oversell، همه-یا-هیچ |

| AC | وضعیت |
|---|---|
| AC-SHOP-01 (E2E §10 سبز) | ✅ ۸/۸ |
| AC-SHOP-02 (درگاه واقعی sandbox) | ◐ آداپتر `engine` به موتور payment (zarinpal/idpay/… با sandbox config در M7) وصل است و preset=enterprise فعالش می‌کند؛ تماس شبکه‌ای واقعی با sandbox درگاه خارج از CI است — smoke درون‌فرایندی sandbox ماژول سبز |
| AC-SHOP-03 (موجودی همزمان) | ✅ گارد اتمیک SQL + ۲ تست |
| AC-SHOP-04 (جست‌وجوی فارسی نرمال) | ✅ |
| AC-SHOP-05 (گزارش با report engine) | ◐ `salesStats` + داشبورد + CSV (`shop.report.view`)؛ اتصال ReportBuilder (read-model ثبت‌شده در config گزارش‌ها) به‌عنوان بدهی ثبت شد |
| AC-SHOP-06 (۰ نقض مرز) | ✅ arch:check — ۱۲۲۸ فایل، ۰ نقض |

## ۳. تصمیم‌های طراحی

1. **پول = int minor-unit (ADR-0013) به‌جای DECIMAL(18,4) مستند §3.**
   ADR بر مستند کاربرد مقدم است (خود §3 هم نمونهٔ MySQL-محور است؛ ستون‌ها
   BIGINT و سازگار با SQLite/PG). VO ماژولی `Money` تمام محاسبات را int
   انجام می‌دهد؛ گرد درصد **به پایین** (به نفع مشتری).Money موتور ORM به
   دلیل ARCH-003 در ماژول استفاده نشد (هم‌معنی محلی).
2. **پورت پرداخت ماژولی به‌جای پیاده‌سازی `GatewayInterface` هسته.**
   قرارداد هسته به VOهای موتور تایپ شده (TECH-DEBT-ARCH-01) و ماژول اجازهٔ
   ارجاع به `Tavaan\Engine\*` ندارد؛ `PaymentGatewayPort` با انواع ابتدایی +
   آداپتر Engine با خواندن dynamic نتایج Facade.
3. **Sandbox درگاه = صفحهٔ شبیه‌ساز ماژول** (`/shop/pay/sandbox/{authority}`)
   با دو دکمهٔ موفق/ناموفق — جریان redirect/callback دقیقاً مثل درگاه واقعی
   طی می‌شود (همان URLهای بازگشت، همان verify)؛ E2E-03 با
   `?simulate_failure=1` قطعی است.
4. **مالیات/ارسال/کوپن/موجودی درون ماژول shop** — مطابق ساختار خود DOC-093
   §2 (Infrastructure/Gateways) نه ماژول‌های جدا (tax/shipping/inventory در
   ماتریس DOC-090)؛ ارتقا به ماژول مستقل در آینده با پورت‌های موجود ممکن است.
5. **قیمت معتبر در checkout** — unit_price سبد فقط snapshot نمایشی است؛
   `PlaceOrderService.buildItems` قیمت/فعال‌بودن/variant را از کاتالوگ
   بازمی‌خواند (ضد دستکاری قیمت سمت کلاینت).
6. **نقد و بررسی = پورت ماژول comment** روی `shop_comments` (هم‌زیستی با
   blog/news بدون binding سراسری — الگوی گام ۲) + sanitize پروفایل `plain`
   هنگام ذخیره (دفاع در عمق علاوه بر escape ویو).

## ۴. نصب واقعی و smoke

```
$ php bin/tvn kit:install shop --preset=standard --with-demo
✓ کیت «shop» (v1.0.0) نصب شد — preset: standard
  • theme: ویوها → resources/views/vendor/shop + assets → public/assets/kits/shop
  • rbac: نقش‌های جدید: customer، shop-operator، shop-manager، inventory-keeper
  • demo seed: tavaan.core-user، tavaan.shop، storefront
```

Smoke روی سرور واقعی (`php -S 127.0.0.1:8899`):

| بررسی | نتیجه |
|---|---|
| `GET /shop` | ۲۰۰ — theme کیت (نوار اعلان) + ۶ محصول دمو + قیمت فارسی |
| `GET /shop/products/laptop-pars-15` | ۲۰۰ — ویژگی‌ها + محصولات مرتبط |
| `GET /shop/search?q=شاهنامه` | ۲۰۰ — فقط hit مرتبط |
| `GET /api/v1/shop/products` | ۲۰۰ JSON — ۶ محصول با effective_price |
| Journey کامل (سفارش in-process → صفحهٔ sandbox → callback) | `paid` + اعلان + موجودی ۲۵→۲۴ + sold=۱ • total=۲۳۸٬۷۱۰٬۰۰۰ = ۲۱۹M + **۹٪ مالیات preset** + ارسال رایگان (آستانه) |

## ۵. اصلاحات موتور/تست

- `engines/api/tests/Feature/ApiTest.php::testRoutesForVersionFiltering` —
  فیلتر شمارش «products» ماژول‌ها را مستثنا کرد (فرض ضمنی تست با ثبت
  `/api/v1/shop/products` نقض شده بود). تنها تغییر بیرون از ماژول/کیت.

## ۶. بدهی‌ها و نکات

1. **AC-SHOP-05** — اتصال ReportBuilder (read model + خروجی xlsx) به آمار
   فروش؛ CSV فعلیread-model ساده است.
2. **AC-SHOP-02** — تست زندهٔ sandbox درگاه واقعی (نیاز به کلید API و شبکه).
3. **UI ورود/ثبت‌نام** هنوز در هیچ کیتی وجود ندارد (core-user فاقد
   Controller) — لینک `/login` ویوهای checkout به آینده اشاره دارد؛
   candidate مناسب برای Release 1.0 یا کیت auth.
4. **Abandoned cart** (DOC-093 §8: queue + notification) — رویداد
   `shop.cart.updated` منتشر می‌شود ولی job زمان‌بندی‌شدهٔ یادآوری ساخته
   نشد (نیازمند الگوی queue ماژولی).
5. **چندانبار/مارکت‌پلیس/امتیاز وفاداری (crm-lite)** — خارج از دامنهٔ
   minimum viable؛ پورت‌های `ProductReaderInterface` و رویدادهای سفارش برای
   مصرف‌کنندهٔ crm/report آماده‌اند.
6. **Widgetهای §2** (RecentOrdersWidget و…) — ساختار Widget موتور admin
   با ORM کار می‌کند؛ کارت‌های آمار در orders-index جایگزین شد.
7. `rating_avg` ستون دارد ولی ستارهٔ نقد (rating) در کامنت‌ها پیاده نشد
   (ماژول comment فیلد rating ندارد) — enhancement.
8. محیط تست: host خالی در Request → Location به شکل `http:///path` تولید
   می‌شود (artifact تست، نه باگ production) — تست‌ها با regex مسیر را
   استخراج می‌کنند.

## ۷. QA نهایی

| دروازه | نتیجه |
|---|---|
| `tvn test` | ✅ ۱۴۲۵/۱۴۲۵ — ~۹۳۰۰ assertion |
| `tvn arch:check` | ✅ ۰ نقض (۱۲۲۸ فایل) |
| `tvn security:scan` | ✅ high/critical: ۰ (۸ med = baseline موتور، بدون shop) |
| `tvn lint:php` | ✅ ۱۶۸۲ فایل، ۰ خطا |
| `tvn module:validate` | ✅ ۵۹ manifest |
| `tvn kit:install shop` | ✅ idempotent |
| PHPStan/CS-Fixer | ⚠ بدون vendor composer (مانند M8/گام‌های قبل) |

## ۸. پرونده‌های کلیدی

```
modules/shop/                 — ماژول (manifest/مهاجرت/دامنه/سرویس‌ها/کنترلرها/ویوها/lang/seed/README/tests)
kits/shop/                    — کیت (kit.json/presets/theme/roles/demo/docs/install)
docs/05-applications/shop.md  — §12 وضعیت + بلوک ماشین‌خوان
CHANGELOG.md                  — 0.11.0
docs/08-roadmap/agent-backlog.md — TVN-0093 done • داشبورد M9: 5 todo / 3 done
```

**قدم بعدی:** TVN-0094 — Kit: Forum (DOC-094).
