# موتور جست‌وجو — `Search`

> M6 — ایندکس معکوس خودنوشتهٔ فارسی‌آگاه (BM25 + نرمال‌سازی ی/ک/نیم‌فاصله/اعراب + stemming + ایست‌واژه‌ها)، درایورهای SQLite FTS5/MySQL FULLTEXT(ngram)/PostgreSQL tsvector، آداپترهای HTTP Meilisearch/Typesense، Facet/Suggest/Highlight با escape-then-mark، پایستگی اتمیک JSON، ایندکس خودکار از رویدادهای مدل (Searchable trait)، محافظ DoS (طول کوئری/تعداد توکن).
>
> مستند کامل: [SEARCH](../../docs/03-engines/search.md) • تیکت: TVN-0052 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.search` |
| namespace | `Tavaan\Engine\Search\` |
| Facade | `Tavaan\Core\Facade\Facades\Search` (accessor: `search`) |
| پیکربندی | `config/search.php` → `config('search.*')` |
| Provider | `Tavaan\Engine\Search\SearchServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `database`، `event`، `orm` |
| فرمان‌های CLI | `tvn search:reindex` |
| آمار | 15 فایل / ~4,002 خط در src • 1 فایل تست (34 متد) |

## شروع سریع

```bash
# تست‌های موتور (34 متد)
php bin/tvn test engines/search

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Search;

// API عمومی و مثال‌ها: docs/03-engines/search.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/search/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/search.md`](../../docs/03-engines/search.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
