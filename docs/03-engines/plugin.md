# DOC-079 — موتور Plugin | افزونهٔ ثالث

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-079 |
| Engine ID | `tavaan.engine.plugin` |
| Namespace | `Tavaan\Engine\Plugin` |
| اولویت | **P1** — فاز M8 |
| وابستگی | `module`, `filesystem`, `crypt` |
| Facade | `Plugin` |
| LOC | ۹۰۰ |

## ۱. هدف
سیستم افزونه برای کد ثالث (PHP) با Manifest، Sandbox، امضای رمزنگاریشده و چرخهٔ عمر.

> 🔑 **تفاوت مهم:** طبق صورت مسئله، افزونه‌های PHP از قاعدهٔ «صفر‌وابستگی» **معاف** هستند؛
> یعنی یک پلاگین *می‌تواند* پکیج Composer خودش را در `plugins/<x>/vendor/` داشته باشد.
> اما هسته و موتورها هرگز به آن وابسته نمی‌شوند.

## ۲. تفاوت Plugin و Module
| ویژگی | Module | Plugin |
|---|---|---|
| مالک | تیم اصلی | ثالث |
| وابستگی خارجی | ❌ ممنوع | ✅ مجاز (در vendor خودش) |
| Sandbox | مرز معماری | Sandbox قوی‌تر + امضا |
| دسترسی DB | مستقیم (با مرز) | فقط از طریق Contract |
| نصب | `module:install` | `plugin:install` با تأیید |

## ۳. Manifest پلاگین (`plugin.json`)
```json
{
  "id": "acme.shipping-tip",
  "name": "Shipping Tip Calculator",
  "version": "1.2.0",
  "author": {"name":"ACME","email":"dev@acme.com","url":"https://acme.com"},
  "license": "proprietary",
  "tavaan": ">=1.0 <2.0",
  "requires": {"php":">=8.2","modules":["tavaan.shop"]},
  "autoload": {"psr-4": {"Acme\\ShippingTip\\": "src/"}},
  "provider": "Acme\\ShippingTip\\ServiceProvider",
  "permissions": ["shipping.calculate"],
  "hooks": ["shop.checkout.summary", "shop.order.placed"],
  "signature": "ed25519:base64...",
  "capabilities": {
    "network": ["api.shipping.example.com"],
    "filesystem": "read:storage/app/shipping",
    "database": false,
    "shell": false
  }
}
```

## ۴. امضا و اعتبارسنجی 🔒
```php
final class PluginVerifier
{
    public function verify(string $pluginDir): void
    {
        $manifest = $this->readManifest($pluginDir);

        // 1. بررسی امضا (Ed25519)
        if ($this->requireSignature) {
            $payload = $this->canonicalPayload($manifest);      // بدون فیلد signature
            $signature = $this->decodeSignature($manifest['signature']);
            if (!$this->verifySignature($payload, $signature, $this->trustedKeys[$manifest['author']['name']])) {
                throw new PluginSignatureException(messageId: 'plugin.signature_invalid');
            }
        }

        // 2. بررسی hash فایل‌ها (plugins.lock)
        $this->verifyFileHashes($pluginDir, $manifest['id']);

        // 3. بررسی capabilities
        $this->assertAllowedCapabilities($manifest['capabilities']);
    }
}
```
🔒 `plugins.lock` شامل hash هر فایل پلاگین (SHA-256) برای تشخیص دستکاری.
🔒 کلیدهای مورد اعتماد در `config/plugins.php` (trusted_vendors).

## ۵. Sandbox
```php
final class PluginSandbox
{
    public function load(Plugin $plugin): void
    {
        // 1. Autoload جدا برای namespace پلاگین
        $this->autoloader->addNamespace($plugin->namespace(), $plugin->srcPath());

        // 2. اگر vendor دارد (معافیت)، autoload آن را هم ثبت کن — اما ایزوله
        if ($plugin->hasVendor()) {
            $this->registerIsolatedVendor($plugin);    // با prefix برای جلوگیری از تداخل
        }

        // 3. Capability enforcement
        $this->enforceCapabilities($plugin);

        // 4. ثبت Provider
        $this->container->register($plugin->provider());
    }

    private function enforceCapabilities(Plugin $plugin): void
    {
        $caps = $plugin->capabilities();
        if (!$caps['database']) {
            // 🔒 حذف دسترسی مستقیم DB برای این پلاگین (فقط Contract)
            $this->restrictDatabaseAccess($plugin->namespace());
        }
        if (!$caps['shell']) {
            // ممنوعیت exec/system/passthru در namespace پلاگین (با AST check در install)
        }
        // network: فقط دامنه‌های allowlist
    }
}
```

### ۵٫۱ Static Analysis در نصب
```php
// tvn plugin:scan قبل از نصب
final class PluginScanner
{
    private const array DANGEROUS = [
        'eval', 'exec', 'system', 'passthru', 'shell_exec', 'popen', 'proc_open',
        'assert', 'create_function', 'unserialize', 'file_get_contents', 'fopen',
        'curl_exec', 'include', 'require',
    ];

    public function scan(string $dir): ScanReport
    {
        $findings = [];
        foreach ($this->phpFiles($dir) as $file) {
            $tokens = \token_get_all(\file_get_contents($file));
            foreach ($tokens as $token) {
                if (\is_array($token) && \in_array(\token_name($token[0]), ['T_EVAL','T_STRING'], true)) {
                    if (\in_array(\strtolower((string) $token[1]), self::DANGEROUS, true)) {
                        $findings[] = ['file'=>$file, 'line'=>$token[2], 'func'=>$token[1]];
                    }
                }
            }
        }
        return new ScanReport($findings);
    }
}
```
🔒 یافته‌های خطرناک → هشدار در نصب + نیازمند تأیید صریح admin.

## ۶. Hooks (نقاط اتصال)
```php
// پلاگین به hook متصل می‌شود
final class ServiceProvider extends PluginProvider
{
    public function boot(): void
    {
        Hook::listen('shop.checkout.summary', function (CheckoutContext $ctx): void {
            $ctx->addLine('shipping_tip', $this->calculateTip($ctx->order));
        });
        Hook::listen('shop.order.placed', [$this, 'onOrderPlaced']);
    }
}
```
🔒 Hooks در `module.json` ماژول میزبان تعریف می‌شوند (`"hooks": [...]`).
🔒 پلاگین فقط به hookهای اعلام‌شده می‌تواند متصل شود.

## ۷. چرخهٔ عمر
```
plugin:install <path|id>   → verify → scan → lock → enable
plugin:enable/disable <id>
plugin:update <id>         → backup → verify → migrate → enable
plugin:uninstall <id>      → disable → cleanup (با تأیید)
plugin:list
plugin:verify <id>         → بررسی امضا/hash
```

## ۸. پیکربندی
```php
// config/plugins.php
return [
    'path' => base_path('plugins'),
    'enabled' => env_bool('TVN_PLUGINS', true),
    'require_signature' => env_bool('TVN_PLUGIN_SIGNATURE', true),
    'trusted_vendors' => [
        'Acme' => 'ed25519:base64:PUBKEY...',
    ],
    'allow_vendor' => true,             // معافیت composer برای پلاگین
    'sandbox' => ['database'=>false, 'shell'=>false, 'network_allowlist'=>true],
    'lock_file' => base_path('plugins/plugins.lock'),
];
```

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| کد مخرب | امضا + scan + capabilities |
| دستکاری فایل | `plugins.lock` hash |
| تداخل namespace | prefix ایزوله |
| دسترسی DB غیرمجاز | capability + Contract-only |
| SSRF از پلاگین | network allowlist |
| DoS | timeout + memory limit برای hook |

## ۱۰. تست
| T-01 | verify امضا معتبر/نامعتبر |
| T-02 | lock hash → دستکاری تشخیص |
| T-03 | scan توابع خطرناک |
| T-04 | capability enforcement (بدون DB) |
| T-05 | hook listener |
| T-06 | install/enable/disable |
| T-07 | vendor ایزوله (بدون تداخل با هسته) |
| T-08 | پلاگین با وابستگی composer → کار می‌کند (معافیت) |

## ۱۱. معیار پذیرش 🧪
- AC-PLG-01..08. AC-PLG-09: پلاگین بدون امضا (در `require_signature=true`) نصب نمی‌شود.

## ۱۲. مراجع
| منبع | تفاوت |
|---|---|
| WordPress Plugins | hook/manifest | امضای رمزنگاریشده + sandbox قوی |
| Drupal Modules | permissions | capabilities مدل |
| Shopware Plugins | — | — |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.plugin.v1
engine:
  id: tavaan.engine.plugin
  namespace: Tavaan\Engine\Plugin
  path: engines/plugin
  priority: P1
  phase: M8
  depends_on: [module, filesystem, crypt]
  facade: {name: Plugin, accessor: plugins}
  config_file: config/plugins.php
  exempt_from_zero_dependency: true    # 🔑 پلاگین‌ها می‌توانند vendor داشته باشند
  public_api:
    - class: PluginManager
      methods: [install, uninstall, enable, disable, update, list, verify, scan]
    - class: Hook
      methods: [listen, fire, has]
  loc_estimate: 900
```
