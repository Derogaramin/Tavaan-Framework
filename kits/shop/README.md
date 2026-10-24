# کیت فروشگاه — `kits/shop` (DOC-093 + DOC-090)

نصب کامل فروشگاه اینترنتی تَوان با یک دستور:

```bash
php bin/tvn kit:install shop --preset=standard --with-demo
```

## آنچه نصب می‌شود

| جزء | جزئیات |
|---|---|
| ماژول‌ها | core-user, content, comment, media, **shop** (blog/news اختیاری) |
| جداول | ۱۰ جدول `shop_*` + جداول ماژول‌های پیش‌نیاز |
| نقش‌ها | customer, shop-operator, shop-manager, inventory-keeper, admin |
| مجوزها | ۱۴ کلید `shop.*` (rbac:sync خودکار) |
| پوسته | `themes/default` → `resources/views/vendor/shop/` + `public/assets/kits/shop/` |
| دمو | ۳ دسته، ۶ محصول (یک محصول با variant)، ۳ کوپن، انتصاب نقش کاربران دمو |

## Presetها

| Preset | مالیات | ارسال | پرداخت | کش |
|---|---|---|---|---|
| `minimal` | ۰٪ | تخت ۳۰۰٬۰۰۰ | sandbox | خاموش |
| `standard` (پیش‌فرض) | ۹٪ | تخت ۵۰۰٬۰۰۰ / رایگان ≥ ۵۰M | sandbox | ۶۰s |
| `enterprise` | ۱۰٪ (+ارسال) | تخت ۸۰۰٬۰۰۰ / رایگان ≥ ۱۰۰M | **engine** (درگاه واقعی) | ۱۲۰s |

> preset فقط overlay روی `config/shop.php` می‌نویسد (gitignored) — همیشه قابل
> ویرایش دستی است. برای درگاه واقعی: `shop.payment.driver=engine` +
> `shop.payment.gateway=zarinpal` و کلیدهای API در `.env`.

## مسیرهای کلیدی پس از نصب

- ویترین: `/shop` • جست‌وجو `/shop/search?q=` • سبد `/shop/cart` • سفارش‌ها `/shop/orders`
- پنل: `/admin/shop/products|orders|coupons|categories|comments` • گزارش `/admin/shop/orders/sales.csv`
- API: `/api/v1/shop/*` (DOC-093 §7)
- پرداخت sandbox: صفحهٔ شبیه‌ساز `/shop/pay/sandbox/{authority}` با دکمهٔ موفق/ناموفق

## کاربران دمو (`--with-demo`)

| ایمیل | رمز | نقش فروشگاه |
|---|---|---|
| admin@blog.test | password | admin |
| editor@blog.test | password | shop-manager |
| author@blog.test | password | shop-operator |
| reader@blog.test | password | customer |

کوپن‌های دمو: `TAVAAN10` (۱۰٪ سقف ۵۰M)، `OFF500K` (ثابت)، `WELCOME1405` (۱۵٪ سقف ۲۰M).

## راهنماها

- `docs/user-guide.md` — خرید تا پرداخت (ویترین)
- `docs/admin-guide.md` — مدیریت کاتالوگ/سفارش/کوپن/نقد
