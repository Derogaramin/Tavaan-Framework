# تیکت‌های `core/Autoloading`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

---

## TVN-0001 — Autoloader خودنوشته با ClassMap

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-027](../../docs/02-architecture/bootstrapping.md) • [DOC-082](../../docs/04-structure/composer-mapping.md) |
| برآورد LOC | ~400 |

### معیارهای پذیرش
- پروژه بدون vendor/ بالا می‌آید
- PSR-4 + wildcard (engines/*/src) کار می‌کند
- Path traversal → AutoloadException
- ClassMap cache ≥ ۷۰٪ سریع‌تر

### فایل‌های کلیدی (طبق تیکت)
- `core/Autoloading/Autoloader.php` ✓
- `core/Autoloading/AutoloaderRegistry.php` ✓
- `core/Autoloading/ClassMapGenerator.php` ✓
- `core/bootstrap.php` ✓

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test` (سوئیت سراسری)

### راهنمای پیاده‌سازی (agent_hints)
> DOC-027 §2 را دقیق پیاده کن. locked_prefixes برای امنیت. sortPrefixesByLength.

