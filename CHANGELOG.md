# Changelog — تَوان | Tavaan

فرمت: Keep a Changelog — نسخه‌گذاری: SemVer.

## [0.1.0] - 2026-10-15 — فاز M1: هستهٔ صفر‌وابستگی

### افزوده شد
- **TVN-0001** Autoloader خودنوشته: PSR-4 + Wildcard (`engines/*/src`) + ClassMap authoritative + امنیت path-traversal + `core/bootstrap.php` (DOC-027)
- **TVN-0002** Container: Auto-Wiring با ReflectionCache (dump/load)، Contextual Binding، Singleton/Scoped/Factory/Transient، Tagging تنبل، Extender، تشخیص چرخه با مسیر، Union/DNF قطعی، Method Injection (`call`)، LazyProxy بدون eval، ScopeManager
- **TVN-0003** Config: EnvLoader بدون putenv، EnvRepository (اولویت $_SERVER→.env→default)، Repository با dot-notation + LRU + freeze + masking، ConfigLoader با اولویت engine<module<app، ConfigCacher اتمیک، SchemaValidator
- **TVN-0004** Event: EventDispatcher با Priority قطعی، Wildcard Segment-Trie، Subscriber، Hierarchy (interface/parent)، Stoppable، Allowlist امنیتی، Fake/Assert برای تست (Outbox در M3)
- **TVN-0005** Pipeline: الگوی Onion عمومی، پارامتر (`auth:web,admin`)، alias، terminate، max_depth
- **TVN-0006** Error: ExceptionHandler (report/render/map/renderable/dontReport/level)، ErrorHandler (E_* → ErrorException)، Deprecation→CI failure، LogMasker، RefCodeGenerator (Crockford base32)، Renderers: JSON RFC 9457 / HTML امن / CLI / Debug غنی
- **TVN-0007** Log: LogManager چندکاناله (daily/json/audit/query/queue/security/deprecations/stderr/stack/null/array)، LogRecord readonly، Processorها (mask/trace/identity/memory/runtime/backtrace/environment)، Buffer، چرخش روزانه با retention، sanitization علیه Log Injection
- **TVN-0008** Compat: Compat (report/driver/require/forceFallbacks)، Utf8، **Decimal بدون bcmath** (دقت ۱۸ رقم — تطبیق با Python Decimal)، ZipWriterPure، MagicBytesDetector، jsonValidate fallback
- **TVN-0009** Optimizer: Cacherها (config/autoload)، Optimizer با زمان‌بندی، atomic write
- **TVN-0010** Module: ManifestParser با schema validation، ModuleRegistry (کشف engines+modules)، TopologicalSorter (Kahn قطعی)، ModuleManager (enable/disable با پایداری وضعیت)
- **TVN-0011** Console: SignatureParser، ArgvInput، Output رنگی (NO_COLOR)، Command (ask/confirm/choice/table)، ConsoleKernel با discovery، `bin/tvn` + ۱۳ دستور داخلی
- **Test Engine (حداقلی)**: TestCase + Assertionها + expectException + TestRunner بومی (`tvn test`)
- Application/ApplicationBuilder + ۸ Bootstrapper (idempotent) + Facadeها (App/Config/Event/Log/Module) + ServiceProvider
- قراردادها: Container/Config/Events/Log/Pipeline/Console/Support در `core/Contracts/`
- سلسله‌مراتب استثناها (DOC-013) با کدهای TCR/TCF/TEV/TKR/...
- ابزار کیفیت: `tools/scripts/lint.sh`، `check-forbidden.php` (gate موارد ممنوعه)، بنچمارک container و autoload
- ساختار پروژه مطابق DOC-080 + `tavaan.json` + `.env.example` + manifest موتورهای ۱۱گانه

### نتایج
- ۱۵۵ تست / ۳۶۲ assertion سبز • بوت ~۷٫۷ms • classmap ۹۰٫۶٪ سریع‌تر • صفر eval/putenv
