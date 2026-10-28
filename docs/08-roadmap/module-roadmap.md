# DOC-123 — نقشهٔ راه توسعهٔ ماژول‌ها (M10–M13) 🧩

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-123 |
| وضعیت | Draft v0.1 — نیازمند بازبینی (پس از تأیید: Approved) |
| نقش | تحلیل جامع شکاف ماژول‌ها + نقشهٔ راه فازهای پس از 1.0 (M10 تا M13) |
| مرتبط | DOC-120 (فازها)، DOC-121 (بک‌لاگ)، DOC-122 (بدهی فنی)، DOC-022 (ماژولار مونولیت)، DOC-030 (رجیستری موتورها)، DOC-090 (الگوی اپلیکیشن)، DOC-101 (کاتالوگ مجوزها)، DOC-113 (DoD) |
| به‌روزرسانی | با شروع/اتمام هر ماژول؛ وضعیت تیکت‌های پیوست ماشین‌خوان (§۹) را تغییر دهید |

> ✅ این سند در `docs/manifest.json` ثبت شده — `build-manifest.py` و `verify-docs.py`
> پس از افزودن اجرا شدند (v0.20.0).

---

## ۱. خلاصهٔ اجرایی

فریمورک تَوان در نگارش `0.19.0` دارای **۵۲ موتور سبز (M1–M8)**، **۸ کیت اپلیکیشن (M9)** و
**۱۴ ماژول دامنه** است (۱۲ ماژول تا پیش از این سند + `poll` و `faq` که در اسپرینت جاری
افزوده شدند). هستهٔ قابلیت‌ها (پرداخت ۱۰ درگاهی، جست‌وجوی فارسی، workflow، گزارش،
tenancy، observability، realtime، backup، audit و…) **در سطح موتور کامل است**؛
شکاف اصلی پروژه در **لایهٔ ماژول** است:

1. **ماژول‌های تجاری-مالی**: کیف پول کاربری، صورتحساب/پرداخت یکپارچه، باشگاه مشتریان (وفاداری) — موتور payment وجود دارد اما هیچ ماژول دامنه‌ای روی آن نیست.
2. **رابط‌های گرافیکی مدیریتی برای موتورها**: امنیت (audit/rate-limit/2FA/scan)، کنسول API (OpenAPI تعاملی + توکن)، پایش سیستم (queue/schedule/health/log)، پشتیبان‌گیری، چنداجاره‌ای، feature-flag، ترجمه‌ها — همه CLI-محورند و UI ندارند.
3. **فایل و رسانهٔ پیشرفته**: FileManager (فراتر از کتابخانهٔ تصویری ماژول media)، گالری عمومی آلبومی.
4. **رشد و تعامل**: مرکز اعلان (in-app)، خبرنامه/فرم‌ساز، جست‌وجوی سراسری UI، مجموعهٔ SEO، تحلیل/KPI، چت، مستندات سایت (docs).

این سند **۱۹ ماژول جدید + ۱ اسپرینت تثبیت (M10-0)** را در ۴ فاز اولویت‌بندی می‌کند
(§۵ تا §۸)، با مشخصات مختصر هر ماژول، گراف وابستگی، رجیستری order ناوبری و
پیوست ماشین‌خوان تیکت‌ها (TVN-0100..TVN-0119).

**اصل حاکم:** هر ماژول = «لایهٔ دامنه/UI روی موتورهای موجود» — هیچ موتور جدیدی در
M10–M13 ساخته نمی‌شود مگر با ADR مستقل. مرز ماژول (ARCH-003) و الگوی DOC-022/DOC-090
بر همه حاکم است.

---

## ۲. روش تحلیل شکاف

ماتریس «قابلیت موتور → سطح ماژول» برای هر ۵۲ موتور پر شد:

| سطح | معنا |
|---|---|
| ✅ کامل | ماژول/کیت با UI و دامنه روی موتور وجود دارد |
| 🟡 نسبی | موتور استفاده شده اما UI/دامنهٔ مستقل ندارد |
| ❌ بدون لایهٔ ماژول | فقط CLI/Facade — مصرف‌کنندهٔ دامنه‌ای ندارد |

نتیجه برای موتورهای دارای شکاف:

| موتور | قابلیت کلیدی | وضعیت لایهٔ ماژول | ماژول پیشنهادی |
|---|---|---|---|
| payment | ۱۰ درگاه + wallet gateway + verify امن | 🟡 فقط checkout فروشگاه (shop) | `billing` + `wallet` |
| audit | hash chain + `audit:verify/show/export` | ❌ بدون GUI | `security-center` |
| security | CSRF/RateLimit/SSRF/Sanitizer/Headers | ❌ بدون GUI | `security-center` |
| auth | JWT/OTP/2FA/refresh-rotation | 🟡 فقط login/register (core-user) | `security-center` (مدیریت 2FA/نشست‌ها) |
| api | Resource/OpenAPI + `api:docs` | ❌ بدون کنسول تعاملی/مدیریت توکن | `api-console` |
| queue | worker/retry/failed/monitor (CLI) | ❌ بدون GUI | `system-monitor` |
| schedule | cron داخلی + `schedule:list/run` | ❌ بدون GUI | `system-monitor` |
| observability | metrics/trace | ❌ بدون داشبورد | `system-monitor` + `analytics` |
| log | چندکاناله + mask | ❌ بدون نمایشگر | `system-monitor` |
| backup | run/list/verify/restore/prune (CLI) | ❌ بدون GUI | `backup-manager` |
| tenancy | tenant:create/list/migrate/delete/stats | ❌ بدون GUI | `tenancy-admin` |
| feature-flag | flag/rollout | ❌ بدون GUI | `feature-flag-ui` |
| i18n | آرایه‌های زبان ماژول‌ها | ❌ بدون ویرایشگر | `i18n-manager` |
| search | ایندکس فارسی + `search:reindex` | 🟡 فقط جست‌وجوی داخلی shop | `search-ui` |
| notification | چندکاناله (mail/db/…) | ❌ بدون مرکز اعلان in-app | `notification-center` |
| mail | SMTP خودنوشته | 🟡 فقط کمپین CRM | `marketing` (خبرنامه) |
| realtime | SSE/WebSocket | ❌ بدون مصرف‌کنندهٔ دامنه | `chat` |
| filesystem | local/s3/ftp | 🟡 فقط کتابخانهٔ تصویر media | `filemanager` |
| media (engine) | پردازش تصویر | 🟡 UI ادمین media v0.2 | `filemanager` + `gallery` |
| report | html/xlsx/csv/pivot | 🟡 demo/report + گزارش‌های CRM/ERP | `analytics` |
| crypt/hash | AES-GCM/Argon2id | ✅ (پوشش در همه) | — |
| workflow | BPM کامل | ✅ (shop/bpms/erp/crm) | — |

جمع‌بندی: **۱۹ ماژول جدید** + توسعهٔ ۲ ماژول موجود (media→پایهٔ filemanager،
core-user→پروفایل امنیتی؛ §۶ و §۷).

---

## ۳. موجودی و بلوغ ماژول‌های فعلی

| # | ماژول | نسخه | دامنه | بلوغ | یادداشت |
|---|---|---|---|---|---|
| 1 | `core-user` | 0.2.0 | کاربران/احراز هویت/پروفایل | ✅ سبز | UI ورود/ثبت‌نام/پروفایل + `/admin/users` |
| 2 | `blog` | — | وبلاگ (کیت M9) | ✅ سبز | DOC-091 |
| 3 | `news` | — | خبرگزاری (کیت M9) | ✅ سبز | DOC-092 |
| 4 | `shop` | 0.1.0 | فروشگاه کامل | ✅ سبز | محصول/سبد/کوپن/checkout/سفارش-workflow/موجودی/نقدها |
| 5 | `forum` | — | انجمن (کیت M9) | ✅ سبز | DOC-094 |
| 6 | `lms` | — | آموزش (کیت M9) | ✅ سبز | DOC-095 |
| 7 | `crm` | 0.1.0 | مخاطب/سرنخ/pipeline/تیکت SLA/کمپین | ✅ سبز | DOC-096 — **بدون وفاداری/امتیاز** |
| 8 | `erp` | — | ERP (کیت M9) | ✅ سبز | DOC-097 |
| 9 | `bpms` | — | فرآیند (کیت M9) | ✅ سبز | DOC-098 |
| 10 | `content` | 0.2.0 | صفحات CMS + markdown/SEO/sitemap/feed | ✅ ۷۴ تست سبز | v0.20.0 تثبیت شد |
| 11 | `comment` | 0.2.0 | نظر پلی‌مورفیک رشته‌ای | ✅ ۴۳/۴۳ تست سبز | اسپرینت جاری |
| 12 | `media` | 0.2.0 | کتابخانهٔ رسانهٔ پنل + پورت MediaGateway | ✅ سبز | پایهٔ filemanager/gallery |
| 13 | `poll` | 0.1.0 | نظرسنجی + رأی یکتا | ✅ ۶۲ تست سبز | v0.20.0 |
| 14 | `faq` | 0.1.0 | پرسش‌های متداول + رأی مفید | ✅ ۵۱ تست سبز | v0.20.0 (TVN-FAQ-001..009) |

> ✅ اسپرینت تثبیت (TVN-STB-001) در v0.20.0 انجام شد: تست‌های هر سه ماژول
> content(pages)/poll/faq اجرا و سبز شدند (۷۴ + ۶۲ + ۵۱ = ۱۸۷ تست) — نتایج در §۵٫۰.

---

## ۴. نیازمندی‌های اعلام‌شدهٔ ذی‌نفع و نگاشت آن‌ها

| نیاز اعلام‌شده | نگاشت به نقشهٔ راه | توضیح |
|---|---|---|
| باشگاه مشتریان | `loyalty` (M10) | امتیاز/سطح/پاداش/معرف — CRM فعلی این دامنه را ندارد |
| API | `api-console` (M12) | کنسول تعاملی OpenAPI + مدیریت توکن/throttle — موتور api موجود است |
| FileManager | `filemanager` (M11) | مدیر فایل کامل (پوشه/جابجایی/سهمیه/اشتراک) — media فعلی فقط کتابخانهٔ تصویر است |
| گالری تصاویر | `gallery` (M11) | آلبوم عمومی با چرخهٔ انتشار — روی media/filemanager |
| امنیت (رابط گرافیکی) | `security-center` (M12) | GUI برای audit/rate-limit/2FA/scan/نشست‌ها |
| پرداخت | `billing` (M10) | صورتحساب/پرداخت/استرداد یکپارچه روی موتور payment |
| کیف پول | `wallet` (M10) | موجودی/دفتر کل/شارژ/انتقال/برداشت |
| و … | ۱۲ ماژول دیگر §۵–§۸ | از تحلیل ماتریس §۲ |

---

## ۵. فاز M10 — تثبیت + تجارت و مالی

### ۵٫۰ اسپرینت تثبیت (P0 — پیش‌نیاز همهٔ فازها)

```yaml
ticket:
  id: TVN-STB-001
  title: "تثبیت: اجرای تست‌های content/poll/faq + همگام‌سازی اسناد"
  phase: M10
  type: fix
  priority: P0
  acceptance:
    - "php bin/tvn test → کل سوئیت سبز (≈۱۷۰۴ + ≈۱۳۷ تست جدید)"
    - "php bin/tvn arch:check سبز (بدون نقض ARCH-003 در ۴ ماژول جدید)"
    - "tools/scripts/lint.sh سبز"
    - "DOC-101: کلیدهای مجوز content.*/poll.*/faq.* ثبت شود"
    - "DOC-086: جداول content_pages/poll_*/faq_* در کاتالوگ schema ثبت شود"
    - "README ریشه: شمار ماژول‌ها ۱۲ → ۱۴ و CHANGELOG به‌روز شود"
    - "docs/manifest.json بازتولید شود (build-manifest.py)"
```

> ✅ **نتیجهٔ اجرا (v0.20.0 — done):** **۱۹۱۷ تست / ۱۱٬۹۵۸ assertion / ۱ skip مستند** سبز
> (اجرای خردشده با `memory_limit=640M` — اجرای تک‌فرایندی کل سوئیت در سندباکس ۱GB
> OOM-kill می‌شد)؛ `arch:check` ۱۶۳۷ فایل / ۰ نقض؛ `lint` ۱۳۳۴ فایل / ۰ خطا؛
> DOC-101 §۱۲ افزوده شد؛ **DOC-086 تغییری نمی‌خواست** (طبق دیباچهٔ همان سند، جداول
> ماژول‌های دامنه در README/بلوپرینت هر ماژول مستند می‌شوند نه در کاتالوگ هسته)؛
> README ریشه + CHANGELOG + manifest همگام شدند. اصلاحات همراه: قاعدهٔ `required`
> (رشتهٔ فقط-فاصله = تهی)، قید یونیکد `{id}` در روت‌های comment، `asGuest()`/`forgetUser()`
> در هارنس تست.

### ۵٫۱ `wallet` — کیف پول کاربر 💰 (P0, ~2500 LOC, TVN-WAL)

- **هدف:** کیف پول داخلی هر کاربر: موجودی، دفتر کل دوطرفه، شارژ از درگاه‌ها، انتقال به کاربر دیگر، درخواست برداشت.
- **جداول:** `wallet_wallets` (user_id یکتا, balance int minor-unit), `wallet_transactions` (ledger: wallet_id, type: credit/debit, ref_type/ref_id, balance_after, meta), `wallet_topups` (payment ref), `wallet_withdrawals` (status: pending/approved/paid/rejected + info کارت/شبا).
- **موتورها:** payment (gateway «کیف پول» موجود → تبدیل به funding-source دوطرفه)، database، notification، rbac، audit (تراکنش‌ها audit شوند). **ماژول‌ها:** core-user.
- **قواعد دامنه:** پول = int minor-unit (ADR-0013)؛ هر تراکنش در تراکنش DB اتمیک + ردیف ledger غیرقابل‌ویرایش (append-only)؛ موجودی منفی ممنوع (invariant)؛ idempotency شارژ با ref یکتا.
- **مسیرها:** `/wallet` (موجودی/تاریخچه/شارژ/انتقال)، `/admin/wallets` (دفتر کل/برداشت‌ها/تنظیم موجودی با audit).
- **مجوزها:** `wallet.view` `wallet.topup` `wallet.transfer` `wallet.admin` `wallet.withdraw.approve`
- **رویدادها:** `wallet.topped_up` `wallet.transferred` `wallet.withdrawal.requested/approved/paid` (کلاس) + `wallet.balance.adjusted` (payload).
- **AC کلیدی:** انتقال همزمان A→B و B→A بدون شرط مسابقه (ledger تراز)؛ شارژ با callback تکراری فقط یک‌بار اثر دارد؛ برداشت بدون مجوز → ۴۰۳؛ نمایش ارقام فارسی + jalali.

### ۵٫۲ `billing` — صورتحساب و پرداخت یکپارچه 🧾 (P0, ~3000 LOC, TVN-BIL)

- **هدف:** لایهٔ دامنهٔ پرداخت مشترک همهٔ ماژول‌ها: فاکتور (invoice) با اقلام، ثبت پرداخت/استرداد از هر درگاه، داشبورد مغایرت‌گیری، خروجی report. shop/lms/wallet به‌جای منطق پرداخت پراکنده، invoice صادر می‌کنند.
- **جداول:** `billing_invoices` (number, user_id, status: draft/pending/paid/canceled/refunded, amounts int, due_at), `billing_invoice_items` (polymorphic ref), `billing_payments` (invoice_id, gateway, ref_id/authority, amount, status, verified_at), `billing_refunds`.
- **موتورها:** payment، database، notification، report، schedule (invoice منقضی → cancel/یادآور)، rbac. **ماژول‌ها:** core-user؛ مصرف رویداد ماژول‌ها (بدون وابستگی کلاسی).
- **مسیرها:** `/billing` (فاکتورهای من + پرداخت)، `/billing/invoice/{number}` (چاپ)، `/admin/billing` (همه + مغایرت‌گیری + refund).
- **مجوزها:** `billing.view` `billing.pay` `billing.admin` `billing.refund`
- **رویدادها:** `billing.invoice.issued/paid/canceled` `billing.payment.failed` `billing.refund.issued`.
- **AC کلیدی:** verify فقط با مبلغ محلی (هم‌راستا AC-PAY)؛ دو callback = یک اثر؛ فاکتور paid قابل refund جزئی/کامل با پایستگی جمع مبالغ؛ report xlsx پرداخت‌ها.

### ۵٫۳ `loyalty` — باشگاه مشتریان 🏆 (P0, ~2500 LOC, TVN-LOY)

- **هدف:** امتیاز، سطح عضویت، پاداش و معرف. قواعد کسب/سوزاندن رویدادمحور (خرید، ثبت‌نام، نظر تأییدشده، رأی نظرسنجی، ورود روزانه).
- **جداول:** `loyalty_members` (user_id, tier_id, points_balance), `loyalty_tiers` (threshold, multiplier, benefits JSON), `loyalty_rules` (event → points formula, active window), `loyalty_points_ledger` (append-only), `loyalty_rewards` (cost_points, stock, type: coupon/wallet/badge), `loyalty_redemptions`, `loyalty_referrals` (inviter/invitee, status, reward).
- **موتورها:** event (consumer)، database، notification، schedule (انقضای امتیاز)، rbac. **ماژول‌ها:** core-user؛ مصرف‌کنندهٔ رویدادهای `shop.order.paid` `user.registered` `comment.approved` `poll.vote.cast` — **بدون ارجاع کلاسی** (مرز ماژول).
- **هم‌افزایی:** reward نوع coupon → صدور کوپن shop (رویداد مصرف‌شده توسط shop)؛ نوع wallet → شارژ کیف پول (M10-1)؛ سطح‌ها در پروفایل core-user نمایش یابد.
- **مسیرها:** `/loyalty` (امتیاز/سطح/پاداش‌ها/لینک معرف)، `/admin/loyalty` (قواعد/سطح‌ها/پاداش‌ها/ledger).
- **مجوزها:** `loyalty.view` `loyalty.redeem` `loyalty.rules.manage` `loyalty.adjust`
- **AC کلیدی:** امتیاز منفی نشود؛ ledger append-only و قابل بازسازی از رویدادها؛ upgrade سطح خودکار با عبور از آستانه؛ referral خود-ارجاع رد شود.

---

## ۶. فاز M11 — فایل، رسانه و مستندات

### ۶٫۱ `filemanager` — مدیر فایل 🗂️ (P0, ~2500 LOC, TVN-FM)

- **هدف:** FileManager کامل روی موتور filesystem: درخت پوشه، آپلود (چندفایلی + chunk برای بزرگ)، جابجایی/تغییرنام، حذف نرم، سهمیه، لینک اشتراکی امضاشده، پیش‌نمایش (تصویر/ویدیو/متن/PDF).
- **جداول:** `fm_nodes` (id, parent_id, type: dir/file, name, path, mime, size, disk, visibility, owner_id, meta JSON), `fm_shares` (node_id, token, expires_at, max_downloads, password_hash?), `fm_quotas`.
- **موتورها:** filesystem، media (thumbnail)، security (allowlist mime/extension + PathGuard)، cache، rbac. **ماژول‌ها:** media (پورت MediaGateway بازاستفاده)، core-user.
- **توسعهٔ ماژول media:** کتابخانهٔ فعلی media به «نمای تصویری» filemanager تبدیل می‌شود (media v0.3) — جدول `tvn_media` موتور بدون تغییر می‌ماند.
- **مسیرها:** `/admin/files` (مرورگر)، `/files/s/{token}` (دانلود اشتراکی)، API جزئی برای انتخابگر فایل در فرم‌های ماژول‌ها.
- **مجوزها:** `file.view` `file.upload` `file.update` `file.delete` `file.share` `file.quota.manage`
- **AC کلیدی (🔒 امنیتی):** path traversal (`../`, symlink, null-byte) → رد با RefCode؛ آپلود PHP/اجراشدنی روی disk عمومی → رد مطلق؛ لینک اشتراکی منقضی/بیش از سقف → ۴۰۴؛ سهمیهٔ پر → ۴۲۹/۴۲۲؛ حذف نرم قبل از حذف سخت (بازیابی ممکن).

### ۶٫۲ `gallery` — گالری تصاویر 🖼️ (P1, ~1500 LOC, TVN-GAL)

- **هدف:** آلبوم/گالری **عمومی** با چرخهٔ انتشار: `/galleries` (فهرست کارت‌ها)، `/galleries/{slug}` (گرید + lightbox بدون JS با `:target`/details)، اسلایدشو ترتیبی، برچسب، اشتراک‌گذاری.
- **جداول:** `gallery_albums` (slug, title, cover_node_id, status ContentStatus, published_at, seo), `gallery_items` (album_id, node_id, caption, sort_order).
- **موتورها:** view، media، search (ایندکس عنوان/کپشن). **ماژول‌ها:** filemanager (منبع فایل)، content (Slug + ContentStatus + SeoMetadata — همان الگوی faq).
- **مسیرها:** عمومی بالا + `/admin/galleries` (CRUD + ترتیب drag-free با sort_order).
- **مجوزها:** `gallery.view` `gallery.create` `gallery.update` `gallery.publish` `gallery.delete`
- **AC کلیدی:** فقط published/غیرtrash عمومی؛ کاور خودکار از نخستین آیتم؛ تصویر با renditions موتور media (وب‌پ/بندانگشتی) سرو شود نه اصل فایل.

### ۶٫۳ `docs` — مستندات سایت/ویکی 📚 (P1, ~2000 LOC, TVN-DOC)

- **هدف:** ویکی سلسله‌مراتبی عمومی برای مستندسازی محصول/راهنماها (نه SSOT خودِ فریمورک): بخش → صفحه → زیرصفحه، markdown، فهرست خودکار، جست‌وجوی فارسی، نسخهٔ چاپی.
- **جداول:** `docs_sections`, `docs_pages` (section_id, parent_id, slug سلسله‌ای, title, body_md, body_html, status, sort_order, revision_id?), `docs_revisions` (تاریخچهٔ ویرایش — append-only).
- **موتورها:** view، search، cache. **ماژول‌ها:** content (Markdown/Slug/ContentStatus/Excerpt — بازاستفادهٔ کامل).
- **مسیرها:** `/docs` `/docs/{section}/{slug}` `?q=`، `/admin/docs` (ویرایشگر دوپنجره markdown + پیش‌نمایش + تاریخچه/بازگردانی).
- **مجوزها:** `docs.view` `docs.create` `docs.update` `docs.publish` `docs.delete` `docs.revision.restore`
- **AC کلیدی:** slug یکتا در سطح هم‌نیایان؛ حذف بخش با صفحه → رد یا انتقال (انتخاب در UI)؛ revision قبل از هر update ثبت شود.

---

## ۷. فاز M12 — عملیات و امنیت (GUI روی موتورها)

### ۷٫۱ `security-center` — مرکز امنیت 🛡️ (P0, ~3000 LOC, TVN-SEC)

- **هدف:** GUI واحد امنیت: مرورگر audit (فیلتر/verify زنجیره/export)، نمایش rate-limit و lockoutها، تلاش‌های ورود نقشه‌ای، مدیریت 2FA (خود + ریست کاربران توسط ادمین)، نشست‌های فعال (revoke)، پیش‌نمایش security headers، نتایج `security:scan` با روند زمانی، وضعیت `key:generate`/مجوزها (doctor).
- **جداول:** `sec_scan_runs` (findings JSON, severity counts), `sec_login_attempts` (اگر موتور auth ذخیره نمی‌کند)، بقیه از جداول موجود موتورهای audit/auth/security/security:scan خوانده می‌شوند (بدون کپی).
- **موتورها:** audit، security، auth، observability، log، rbac. **ماژول‌ها:** core-user.
- **توسعهٔ core-user:** بخش «امنیت حساب» در پروفایل (فعال‌سازی TOTP/OTP، دستگاه‌ها/نشست‌ها، تغییر رمز) — TVN-USR-101.
- **مسیرها:** `/admin/security/*` (داشبورد/audit/scans/sessions/2fa/headers).
- **مجوزها:** `security.dashboard` `security.audit.view` `security.audit.export` `security.sessions.revoke` `security.2fa.manage-users` `security.scan.run`
- **AC کلیدی:** `audit:verify` از GUI با نتیجهٔ pass/fail + RefCode؛ revoke نشست دیگران بدون خود-قفل‌شدن؛ دادهٔ حساس در نمایش mask شود (همان LogMasker)؛ export با امضا/تاریخ.

### ۷٫۲ `api-console` — کنسول API 🔌 (P1, ~2000 LOC, TVN-APIC)

- **هدف:** مستندات تعاملی از خروجی OpenAPI موتور api: مرور endpointها، «امتحان کردن» با توکن کاربر، مدیریت توکن‌های شخصی (PAT: صدور/فهرست/revoke)، نمایش سقف/مصرف throttle هر کلاینت، نمونهٔ کد cURL/JSON دوزبانه.
- **جداول:** `api_tokens` (user_id, name, prefix, hash, scopes JSON, last_used_at, expires_at, revoked_at), `api_usage_daily` (token_id, route, count, limited_count).
- **موتورها:** api، auth (JWT)، security (rate-limit stats)، hash. **ماژول‌ها:** core-user.
- **مسیرها:** `/api-console` (docs تعاملی)، `/api-console/tokens`، `/admin/api` (کلاینت‌ها/مصرف/revoker سراسری).
- **مجوزها:** `api.console.use` `api.tokens.manage-own` `api.admin`
- **AC کلیدی:** توکن فقط یک‌بار کامل نمایش یابد (hash-only ذخیره)؛ درخواست try-it از کنسول با rate-limit واقعی؛ اسکوپ‌ها در Gate اعمال شوند.

### ۷٫۳ `system-monitor` — پایش سیستم 📟 (P1, ~2500 LOC, TVN-SYS)

- **هدف:** داشبورد عملیات: health/metrics از observability، مانیتور queue (jobs/failed/retry/flush با تأیید)، تقویم و تاریخچهٔ schedule، نمایشگر log (tail + فیلتر سطح/کانال + mask تضمینی)، آمار cache شش‌درایور، وضعیت workerها.
- **جداول:** بدون جدول جدید (خواندن از جداول موتورهای queue/schedule/log/observability)؛ فقط `sys_alerts` (قاعده → آستانه → کانال اعلان).
- **موتورها:** observability، queue، schedule، log، cache، notification، realtime (SSE برای زنده‌سازی).
- **مسیرها:** `/admin/system` (داشبورد)، `/admin/system/{queue,schedule,logs,cache,alerts}`.
- **مجوزها:** `system.monitor` `system.queue.manage` `system.logs.view` `system.alerts.manage`
- **AC کلیدی:** retry/flush صف با تأیید دوم + audit؛ نمایش log هرگز mask را دور نزند؛ SSE بدون نشتی حافظه در worker.

### ۷٫۴ `backup-manager` — مدیریت پشتیبان 💾 (P2, ~1200 LOC, TVN-BKP)

- **هدف:** GUI موتور backup: اجرای دستی، فهرست + verify، بازیابی با تأیید دوگانه، prune، زمان‌بندی، دانلود با لینک امضاشدهٔ کوتاه‌عمر.
- **جداول:** بدون جدول جدید (از موتور)؛ `bkp_downloads` (token hash, expires_at) اختیاری.
- **مسیرها:** `/admin/backups`. **مجوزها:** `backup.view` `backup.run` `backup.restore` (🔒 فقط super-admin) `backup.delete`
- **AC کلیدی:** restore با snapshot قبلیِ خودکار (rollback-point) + audit؛ دانلود فقط لینک امضاشدهٔ یک‌بارمصرف.

### ۷٫۵ `tenancy-admin` — مدیریت اجاره‌ها 🏢 (P2, ~1500 LOC, TVN-TEN)

- **هدف:** GUI موتور tenancy: CRUD tenant، migrate per-tenant، دامنه/زیردامنه، آمار مصرف، غیرفعال/حذف با محافظت، ایزوله‌سازی داده در نمایش (tenant-guard).
- **مسیرها:** `/admin/tenants/*`. **مجوزها:** `tenancy.view` `tenancy.create` `tenancy.migrate` `tenancy.suspend` `tenancy.delete`
- **AC کلیدی:** هر صفحه با tenant-context صحیح رندر شود (بدون نشت بین‌اجاره‌ای — تست نشتی مانند RBAC M4)؛ حذف tenant فقط پس از backup.

### ۷٫۶ `feature-flag-ui` — مدیریت پرچم‌ها 🚩 (P2, ~1000 LOC, TVN-FF)

- **هدف:** GUI موتور feature-flag: فهرست/ساخت پرچم، rollout درصدی، هدف‌گیری (نقش/کاربر/tenant)، تاریخچهٔ تغییر (audit)، کد embed برای خواندن flag.
- **مسیرها:** `/admin/flags`. **مجوزها:** `flag.view` `flag.manage`
- **AC کلیدی:** تغییر flag فوری در کش اثر کند (invalidate)؛ درصد rollout قطعی بر اساس hash کاربر (بدون jitter بین درخواست‌ها).

### ۷٫۷ `i18n-manager` — مدیر ترجمه 🌐 (P2, ~1500 LOC, TVN-I18N)

- **هدف:** ویرایشگر کلیدهای زبان همهٔ ماژول‌ها/موتورها: درخت namespace::file.key، گزارش کلیدهای گم‌شده (locale fallback)، ویرایش درجا + ذخیرهٔ امن (فایل PHP فقط با allowlist)، خروجی/ورود JSON برای مترجم.
- **مسیرها:** `/admin/translations`. **مجوزها:** `i18n.view` `i18n.update` `i18n.export`
- **AC کلیدی (🔒):** نوشتن فایل زبان با escape کامل (بدون eval/execute)؛ فقط مسیرهای `lang/` ثبت‌شده در manifest قابل نوشتنند؛ تغییر → پاکسازی کش i18n.

---

## ۸. فاز M13 — رشد و تعامل

### ۸٫۱ `notification-center` — مرکز اعلان 🔔 (P1, ~1500 LOC, TVN-NTF)

- **هدف:** اعلان in-app: زنگوله با شمارندهٔ زنده (SSE realtime)، صفحهٔ `/notifications` (خوانده/نخوانده/بایگانی)، **ترجیحات کاربر** (هر نوع اعلان × کانال: in-app/mail/db خاموش)، digest روزانه/هفتگی (schedule).
- **جداول:** `ntf_notifications` (اگر موتور notification جدول db-channel دارد، مصرف همان؛ در غیر این صورت: user_id, type, title, body, data JSON, read_at), `ntf_preferences` (user_id, type, channels JSON).
- **موتورها:** notification، realtime، schedule، mail. **ماژول‌ها:** core-user.
- **مجوزها:** فقط کاربری (بدون گروه ادمین) + `notification.admin` برای broadcast.
- **AC کلیدی:** broadcast ادمین با throttle/queue (نه همگام)؛ ترجیحات قبل از dispatch بررسی شوند (کاهش هزینهٔ mail)؛ SSE badge بدون polling.

### ۸٫۲ `marketing` — خبرنامه و فرم‌ساز 📣 (P1, ~2500 LOC, TVN-MKT)

- **هدف:** دو زیربخش: **(الف) خبرنامه** — فرم اشتراک عمومی با double opt-in، لیست‌ها/برچسب‌ها، لغو اشتراک یک‌کلیکی، ارسال از طریق mail+queue (یا ارجاع به کمپین CRM)؛ **(ب) فرم‌ساز سبک** — ساخت فرم (schema JSON فیلدها: text/select/date/file/checkbox)، صفحهٔ عمومی `/f/{slug}`، ذخیرهٔ submission + notify + export CSV (ضد CSV-injection همان الگوی report).
- **جداول:** `mkt_subscribers` (email hash? + token unsubscribe), `mkt_lists`, `mkt_subscriptions`, `mkt_newsletters`, `mkt_forms` (fields JSON), `mkt_submissions`.
- **موتورها:** mail، queue، validation (قواعد ایرانی)، security (throttle/honeypot ضد اسپم)، filesystem (فایل فرم → filemanager)، notification.
- **مسیرها:** `/subscribe` `/unsubscribe/{token}` `/f/{slug}`، `/admin/marketing/*`.
- **مجوزها:** `marketing.subscribers.view/manage` `marketing.newsletter.send` `marketing.forms.manage`
- **AC کلیدی:** double opt-in واقعی (تأیید قبل از فعال‌سازی)؛ rate-limit + honeypot روی فرم عمومی؛ submission با فایل → ذخیره در مسیر خصوصی filemanager.

### ۸٫۳ `search-ui` — جست‌وجوی سراسری 🔎 (P1, ~1200 LOC, TVN-SRCH)

- **هدف:** صفحهٔ `/search` با جست‌وجوی فارسی (نرمال‌سازی ی/ک، نیم‌فاصله — موتور search) در همهٔ منابع ثبت‌شده: registry ماژول‌ها (content page، blog post، shop product، faq، docs، gallery…) با فیلتر نوع، highlight، پیشنهاد (suggest)، صفحه‌بندی؛ ادمین: وضعیت ایندکس هر منبع + reindex تفکیکی + زمان‌بندی.
- **موتورها:** search، cache. **ماژول‌ها:** مصرف‌کنندهٔ SearchableRegistry — هر ماژول provider ثبت می‌کند (بدون وابستگی معکوس).
- **مسیرها:** `/search?q=&type=`، `/admin/search`. **مجوزها:** `search.admin` `search.reindex`
- **AC کلیدی:** منبع خاموش/حذف‌شده از registry حذف شود بدون خطا؛ نتیجه فقط از محتوای published/عمومی؛ reindex بزرگ در queue.

### ۸٫۴ `seo-suite` — مجموعهٔ SEO 📈 (P1, ~1200 LOC, TVN-SEO)

- **هدف:** sitemap index تجمیعی (provider-based: صفحات content، محصولات shop، مقالات blog/news، faq، gallery، docs)، مدیریت redirect 301/302 (با آمار برخورد)، robots.txt سازنده، تنظیمات پیش‌فرض meta/OG per ماژول، JSON-LD schema.org (Article/Product/FAQ/Breadcrumb)، گزارش کنسول (ثبت verify codeها).
- **جداول:** `seo_redirects` (from, to, code, hits), `seo_settings` (module, key, value JSON), `seo_verifications`.
- **مسیرها:** `/sitemap.xml` `/robots.txt` `/r/{code-path}`، `/admin/seo/*`. **مجوزها:** `seo.manage`
- **AC کلیدی:** sitemap با >۵۰k URL به sitemap-chunk تقسیم شود؛ redirect حلقوی رد شود؛ JSON-LD FAQPage روی `/faq` (هم‌افزایی با ماژول faq).

### ۸٫۵ `analytics` — تحلیل و KPI 📊 (P2, ~2000 LOC, TVN-ANL)

- **هدف:** داشبورد KPI با بازهٔ جلالی: ترافیک (از observability/پیگیری page-view سبک و privacy-first بدون کوکی ثالث)، فروش (shop)، جذب (crm)، رشد کاربر، funnel ساده؛ کارت‌های قابل تنظیم؛ export با موتور report.
- **جداول:** `anl_daily_metrics` (date, metric, dimension JSON, value), `anl_dashboards` (layout JSON per نقش).
- **موتورها:** report، observability، schedule (تجمع شبانه)، cache. **ماژول‌ها:** مصرف رویدادها (shop.order.paid، user.registered، …).
- **مسیرها:** `/admin/analytics`. **مجوزها:** `analytics.view` `analytics.manage`
- **AC کلیدی:** تجمع ۱M رویداد شبانه ≤ ۶۰s (queue)؛ بدون PII خام در جداول تجمع (mask/aggregate).

### ۸٫۶ `chat` — گفت‌وگوی زنده 💬 (P2, ~2500 LOC, TVN-CHAT)

- **هدف:** چت پشتیبانی/کاربر-کاربر روی موتور realtime (SSE؛ WS اختیاری): مکالمه، پیام رسانده‌نشده/خوانده‌شده، حضور (online/typing)، پیوست فایل (از filemanager)، انتقال به اپراتور، آرشیو؛ ویجت embed برای صفحات.
- **جداول:** `chat_conversations`, `chat_messages`, `chat_participants` (last_read_at), `chat_presence`.
- **موتورها:** realtime، notification، queue، security (sanitize پیام). **ماژول‌ها:** core-user، filemanager.
- **مسیرها:** `/chat` `/chat/{conversation}`، `/admin/chat` (پایش/انتقال)، `/api/chat/*` (SSE stream).
- **مجوزها:** `chat.use` `chat.operate` `chat.moderate`
- **AC کلیدی:** تحویل پیام با idempotency (client-msg-id)؛ sanitize کامل پیام کاربر (۰ XSS)؛ history با cursor-pagination (بدون OFFSET سنگین).

---

## ۹. پیوست ماشین‌خوان — تیکت‌های ماژول (قالب DOC-121 §1)

```yaml
# machine-readable: tavaan.modules.roadmap.v1
tickets:
  # ── M10 ──────────────────────────────────────────────
  - {id: TVN-STB-001, title: "اسپرینت تثبیت (تست‌های content/poll/faq + اسناد)", phase: M10, type: fix, priority: P0, depends_on: [], docs: [DOC-123, DOC-113], status: done}
  - {id: TVN-0100, title: "ماژول wallet (کیف پول)", phase: M10, type: module, priority: P0, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-073], estimate_loc: 2500, status: todo}
  - {id: TVN-0101, title: "ماژول billing (صورتحساب/پرداخت یکپارچه)", phase: M10, type: module, priority: P0, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-073], estimate_loc: 3000, status: todo}
  - {id: TVN-0102, title: "ماژول loyalty (باشگاه مشتریان)", phase: M10, type: module, priority: P0, depends_on: [TVN-0100, TVN-0101], docs: [DOC-123, DOC-096], estimate_loc: 2500, status: todo}
  # ── M11 ──────────────────────────────────────────────
  - {id: TVN-0103, title: "ماژول filemanager + media v0.3", phase: M11, type: module, priority: P0, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-063, DOC-074], estimate_loc: 2500, status: todo}
  - {id: TVN-0104, title: "ماژول gallery (گالری عمومی)", phase: M11, type: module, priority: P1, depends_on: [TVN-0103], docs: [DOC-123], estimate_loc: 1500, status: todo}
  - {id: TVN-0105, title: "ماژول docs (ویکی/مستندات سایت)", phase: M11, type: module, priority: P1, depends_on: [TVN-STB-001], docs: [DOC-123], estimate_loc: 2000, status: todo}
  # ── M12 ──────────────────────────────────────────────
  - {id: TVN-0106, title: "ماژول security-center + پروفایل امنیتی core-user", phase: M12, type: module, priority: P0, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-058, DOC-059, DOC-056], estimate_loc: 3000, status: todo}
  - {id: TVN-0107, title: "ماژول api-console (کنسول تعاملی + PAT)", phase: M12, type: module, priority: P1, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-044], estimate_loc: 2000, status: todo}
  - {id: TVN-0108, title: "ماژول system-monitor (queue/schedule/log/health)", phase: M12, type: module, priority: P1, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-061, DOC-062, DOC-067], estimate_loc: 2500, status: todo}
  - {id: TVN-0109, title: "ماژول backup-manager", phase: M12, type: module, priority: P2, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-068], estimate_loc: 1200, status: todo}
  - {id: TVN-0110, title: "ماژول tenancy-admin", phase: M12, type: module, priority: P2, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-082E, DOC-028], estimate_loc: 1500, status: todo}
  - {id: TVN-0111, title: "ماژول feature-flag-ui", phase: M12, type: module, priority: P2, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-075], estimate_loc: 1000, status: todo}
  - {id: TVN-0112, title: "ماژول i18n-manager", phase: M12, type: module, priority: P2, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-069], estimate_loc: 1500, status: todo}
  # ── M13 ──────────────────────────────────────────────
  - {id: TVN-0113, title: "ماژول notification-center", phase: M13, type: module, priority: P1, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-066], estimate_loc: 1500, status: todo}
  - {id: TVN-0114, title: "ماژول marketing (خبرنامه + فرم‌ساز)", phase: M13, type: module, priority: P1, depends_on: [TVN-0103], docs: [DOC-123, DOC-065], estimate_loc: 2500, status: todo}
  - {id: TVN-0115, title: "ماژول search-ui (جست‌وجوی سراسری)", phase: M13, type: module, priority: P1, depends_on: [TVN-STB-001], docs: [DOC-123, DOC-052], estimate_loc: 1200, status: todo}
  - {id: TVN-0116, title: "ماژول seo-suite", phase: M13, type: module, priority: P1, depends_on: [TVN-STB-001], docs: [DOC-123], estimate_loc: 1200, status: todo}
  - {id: TVN-0117, title: "ماژول analytics", phase: M13, type: module, priority: P2, depends_on: [TVN-0108], docs: [DOC-123, DOC-072, DOC-067], estimate_loc: 2000, status: todo}
  - {id: TVN-0118, title: "ماژول chat (realtime)", phase: M13, type: module, priority: P2, depends_on: [TVN-0103, TVN-0113], docs: [DOC-123, DOC-045], estimate_loc: 2500, status: todo}
```

### ۹٫۱ ترتیب پیشنهادی اجرا (صف ایجنت)

```
TVN-STB-001 → TVN-0100 (wallet) → TVN-0101 (billing) → TVN-0103 (filemanager)
→ TVN-0102 (loyalty) → TVN-0106 (security-center) → TVN-0104 (gallery)
→ TVN-0105 (docs) → TVN-0113 (notification-center) → TVN-0107 (api-console)
→ TVN-0108 (system-monitor) → TVN-0114 (marketing) → TVN-0115 (search-ui)
→ TVN-0116 (seo-suite) → TVN-0109..0112 (مدیریتی P2) → TVN-0117 → TVN-0118
```

---

## ۱۰. گراف وابستگی ماژول‌ها

```
                 ┌────────────┐
                 │ core-user  │ (پایه — همه به آن نیاز دارند)
                 └─────┬──────┘
       ┌───────────────┼───────────────────┬─────────────────┐
       ▼               ▼                   ▼                 ▼
  ┌─────────┐    ┌──────────┐        ┌────────────┐   ┌──────────────────┐
  │ wallet  │◀───│ billing  │        │filemanager │◀──│ media (v0.3)     │
  └────┬────┘    └────┬─────┘        └──┬────┬────┘   └──────────────────┘
       │              │                 │    │
       ▼              ▼                 ▼    ▼
  ┌─────────────────────┐          ┌─────────┐ ┌──────────┐
  │ loyalty (باشگاه)    │          │ gallery │ │   chat   │
  │  ← رویداد shop/crm  │          └─────────┘ └──────────┘
  └─────────────────────┘                ▲
       (بدون وابستگی کلاسی —             │
        فقط رویداد/قرارداد)        ┌──────────┐     ┌───────────────┐
                                   │   docs   │     │ marketing     │
                                   └──────────┘     │ (فرم→filemgr) │
  مستقل (GUI روی موتور): security-center,           └───────────────┘
  api-console, system-monitor, backup-manager,
  tenancy-admin, feature-flag-ui, i18n-manager,      ┌───────────────┐
  notification-center, search-ui (registry),         │ analytics     │
  seo-suite (provider-based sitemap), analytics      │ ←system-monitor│
                                                     └───────────────┘
```

🔒 **قاعدهٔ مرز (ARCH-003/DOC-022):** وابستگی ماژول→ماژول فقط از راه
(۱) قرارداد/پورت مشترک (مانند `UserReaderInterface`، `MediaGateway`)،
(۲) رویداد (consumed/published در module.json)، یا (۳) دامنهٔ ماژولِ
وابستگیِ صریحاً اعلام‌شده در `requires.modules` (الگوی content در poll/faq).
هیچ ماژولی مستقیم به موتور ارجاع نمی‌دهد — فقط Facade/Contract هسته.

---

## ۱۱. رجیستری order ناوبری (جلوگیری از تداخل)

| بازه | صاحب | وضعیت |
|---|---|---|
| 10–18 | (آزاد — هسته/داشبورد) | آزاد |
| 19–23 | content 19 • blog 20–21 • poll 22 • faq 23 | پر |
| 24–29 | آزاد (رشد/تعامل کوچک) | آزاد |
| 30–33 | shop | پر |
| 34–49 | کیت‌های forum/lms (بررسی شود — قبل از تخصیص، module.json آن‌ها خوانده شود) | ⚠️ |
| 50–56 | crm | پر |
| 57–79 | آزاد (تجاری) | آزاد |
| 80–84 | آزاد | آزاد |
| 85 | media | پر |
| 86–89 | آزاد (comment=88 پر) | 86,87,89 آزاد |
| 90 | core-user | پر |
| 91–99 | آزاد (erp/bpms احتمالی — بررسی شود) | ⚠️ |
| **100–160** | **رزرو ماژول‌های DOC-123** | رزرو |

تخصیص M10–M13 (همه در بازهٔ رزروشده): wallet=100، billing=101، loyalty=102،
filemanager=103، gallery=104، docs=105، notification-center=106، search-ui=107،
seo-suite=108، marketing=109، security-center=110، api-console=111،
system-monitor=112، backup-manager=113، tenancy-admin=114، feature-flag-ui=115،
i18n-manager=116، analytics=117، chat=118.

> ⚠️ پیش از پیاده‌سازی هر ماژول، `module.json` ماژول‌های forum/lms/erp/bpms
> بررسی و ردیف‌های 34–49/91–99 این جدول قطعی شود.

---

## ۱۲. حاکمیت — تعریف انجام‌شدهٔ هر ماژول (DoD — DOC-113 + DOC-123)

هر ماژول جدید فقط وقتی `done` است که:

1. `module.json` معتبر طبق `docs/schemas/module.v1.json` (همهٔ بلوک‌ها: autoload/providers/requires/routes/views/lang/migrations/events/permissions/navigation/database/docs).
2. `README.md` + `TICKET.md` (تولید با `tools/scripts/generate-ticket-docs.php` یا دستی هم‌قالب).
3. معماری لایه‌ای DOC-022: `Domain` (Entities/Enums/Events/Repositories-قرارداد/Services خالص) • `Application/UseCases` • `Infrastructure/Persistence` (فقط Facade DB) • `Http/Controllers` (کلاس ساده + Gate) • `Providers` • `Dashboard` (Renderable).
4. تست: ≥ ۳۰ تست (Unit دامنه + Unit سرویس + Feature HTTP با RBAC واقعی) — الگوی مرجع: `modules/poll/tests` و `modules/faq/tests`.
5. `php bin/tvn test` + `php bin/tvn arch:check` + `tools/scripts/lint.sh` سبز.
6. lang دوزبانه fa/en (messages + status در صورت چرخهٔ وضعیت)؛ کلیدها با messageId در استثناها.
7. مهاجرت با prefix ماژول + `Schema::hasTable` guard + down کامل؛ ثبت جداول در DOC-086.
8. مجوزها در DOC-101 و order ناوبری در §۱۱ ثبت شود.
9. ویجت داشبورد (Renderable) + seed دمو (`seeds/demo.php`) برای کیت‌پذیری.
10. پول/مقدار: int minor-unit (ADR-0013)؛ تاریخ: Clock یگانه (UTC) + jalali در نمایش.
11. به‌روزرسانی `docs/manifest.json` (build-manifest.py) و `CHANGELOG.md` + README ریشه (شمار ماژول‌ها/تست‌ها).

---

## ۱۳. ریسک‌ها و mitigation

| ریسک | احتمال | اثر | mitigation |
|---|---|---|---|
| تلنبار شدن تست‌های اجرا‌نشده (وضع فعلی content/poll/faq) | رخ‌داده | بالا | TVN-STB-001 پیش از هر ماژول جدید؛ هر ماژول با تست سبز merge شود |
| دامنهٔ wallet/billing بسیار بزرگ شود | متوسط | بالا | MVP: ledger+topup+withdraw بدون چندارزی؛ چندارزی → فاز بعد (هم‌راستا TECH-ERP-01) |
| security-center دادهٔ حساس را نشت دهد | متوسط | بحرانی | mask اجباری (LogMasker)، مجوزهای ریز، audit روی هر کنش مدیریتی |
| i18n-manager روی فایل بنویسد و کد را بشکند | متوسط | بالا | نوشتن با serializer کنترل‌شده + lint خودکار پس از ذخیره + snapshot/rollback |
| تداخل order/نام با کیت‌ها | پایین | متوسط | رجیستری §۱۱ + بررسی manifest کیت‌ها پیش از شروع |
| وابستگی پنهان ماژول→موتور (نقض ARCH-003) | متوسط | بالا | arch:check در CI هر ماژول + بازبینی imports در review |
| chat روی SSE در هاست اشتراکی (DOC-134) | متوسط | متوسط | fallback به polling کوتاه؛ مستندسازی محدودیت |

---

## ۱۴. جمع‌بندی

| فاز | ماژول‌ها | LOC برآوردی | خروجی |
|---|---|---|---|
| M10-0 | تثبیت | ~0 (رفع) | ✅ انجام شد (v0.20.0) — ۱۹۱۷ تست سبز + اسناد همگام |
| M10 | wallet • billing • loyalty | ~8000 | چرخهٔ کامل مالی کاربر (شارژ→خرید→امتیاز→پاداش) |
| M11 | filemanager • gallery • docs | ~6000 | فایل/رسانه/مستندات سرتاسری |
| M12 | security-center • api-console • system-monitor • backup-manager • tenancy-admin • feature-flag-ui • i18n-manager | ~12700 | پنل عملیات/امنیت کامل (هر موتور CLI-دار یک GUI دارد) |
| M13 | notification-center • marketing • search-ui • seo-suite • analytics • chat | ~10900 | لایهٔ رشد و تعامل |
| **جمع** | **۱۹ ماژول** | **~37600** | پوشش کامل ماتریس §۲ |

پس از M13: همهٔ موتورهای 🟡/❌ ماتریس §۲ لایهٔ ماژول دارند و تَوان از
«فریمورک کامل» به «پلتفرم ماژولار کامل با GUI» می‌رسد — آمادهٔ انتشار ماژولی 1.x.
