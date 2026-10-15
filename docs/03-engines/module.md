# DOC-038 — موتور Module | کشف و مدیریت ماژول

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-038 |
| Engine ID | `tavaan.engine.module` |
| Namespace | `Tavaan\Engine\Module` |
| اولویت | **P0** — فاز M1 |
| وابستگی | `container`, `config` |
| Facade | `Module` |
| LOC | ۹۰۰ |
| مرتبط | DOC-022 (Modular Monolith) |

## ۱. هدف
کشف، اعتبارسنجی، مرتب‌سازی توپولوژیک، ثبت و مدیریت چرخهٔ عمر ماژول‌ها بر اساس
`module.json`. (منطق معماری در DOC-022؛ این موتور پیاده‌سازی است.)

## ۲. API
```php
Module::all();                       // list<Module>
Module::get('tavaan.shop');
Module::enabled('tavaan.shop');
Module::enable('tavaan.shop');
Module::disable('tavaan.shop');
Module::install('tavaan.shop');
Module::sorted();                    // topological
Module::run('shop:cart/summary');    // HMVC (DOC-021)
Module::dependencies('tavaan.shop');
Module::dependentsOf('tavaan.core-user');
```

## ۳. کلاس‌ها
| کلاس | مسئولیت |
|---|---|
| `Module` | یک ماژول (manifest + path + status) |
| `ManifestParser` | پارس + Schema validation `module.json` |
| `ModuleRegistry` | ثبت/جست‌وجو/مرتب‌سازی |
| `ModuleContext` | context جاری (برای HMVC/tenancy) |
| `TopologicalSorter` | مرتب‌سازی وابستگی |
| `ModuleManager` | install/enable/disable/update |
| `ModuleRunner` | اجرای Sub-Request (HMVC) |
| `ModuleServiceProvider` | ثبت |

## ۴. ManifestParser (با Schema Validation)
```php
final class ManifestParser
{
    public function parse(string $path): Manifest
    {
        $raw = \file_get_contents($path);
        if ($raw === false) { throw new ModuleException(messageId: 'module.manifest_unreadable'); }

        try {
            $data = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ModuleException(messageId: 'module.manifest_invalid_json',
                context: ['path'=>$path, 'error'=>$e->getMessage()]);
        }

        $this->validateSchema($data, $path);       // فیلدهای اجباری + نوع
        return Manifest::fromArray($data, \dirname($path));
    }

    private function validateSchema(array $data, string $path): void
    {
        $required = ['id','name','version','autoload','providers'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ModuleException(messageId: 'module.manifest_missing_field',
                    context: ['path'=>$path, 'field'=>$field]);
            }
        }
        if (!\preg_match('/^[a-z0-9\-]+\.[a-z0-9\-]+$/', $data['id'])) {
            throw new ModuleException(messageId: 'module.invalid_id', context: ['id'=>$data['id']]);
        }
        if (!$this->isSemver($data['version'])) {
            throw new ModuleException(messageId: 'module.invalid_version');
        }
    }
}
```

## ۵. TopologicalSorter
→ الگوریتم در DOC-022 §4.2 (Kahn's algorithm با خروجی قطعی).

```php
final class TopologicalSorter
{
    /** @param array<string, Module> $modules @return list<Module> */
    public function sort(array $modules): array
    {
        $inDegree = []; $graph = [];
        foreach ($modules as $id => $m) {
            $inDegree[$id] ??= 0;
            foreach ($m->dependencies() as $dep) {
                $graph[$dep][] = $id;
                $inDegree[$id] = ($inDegree[$id] ?? 0) + 1;
            }
        }
        $queue = [];
        foreach ($inDegree as $id => $deg) { if ($deg === 0) { $queue[] = $id; } }
        \sort($queue);                        // 🔒 قطعیت

        $result = [];
        while ($queue !== []) {
            $node = \array_shift($queue);
            $result[] = $modules[$node];
            foreach ($graph[$node] ?? [] as $next) {
                if (--$inDegree[$next] === 0) {
                    $queue[] = $next;
                    \sort($queue);            // 🔒 حفظ ترتیب قطعی
                }
            }
        }
        if (\count($result) !== \count($modules)) {
            $cycle = \array_diff(\array_keys($modules), \array_column($result, 'id'));
            throw new CircularModuleDependencyException(
                messageId: 'module.circular_dependency',
                context: ['modules'=>\array_values($cycle)],
                hint: 'وابستگی چرخه‌ای بین ماژول‌ها را برطرف کنید.',
            );
        }
        return $result;
    }
}
```

## ۶. چرخهٔ عمر
```php
final class ModuleManager
{
    public function install(string $id): void
    {
        $module = $this->registry->get($id);
        $this->assertDependenciesInstalled($module);

        $this->runSteps($module->manifest()->install['steps'] ?? [
            'migrate', 'seed', 'publish:assets', 'cache:clear',
        ]);
        $this->setStatus($id, 'installed');
        $this->enable($id);
        $this->events->dispatch(new ModuleInstalled($id, $module->version()));
    }

    public function enable(string $id): void
    {
        $module = $this->registry->get($id);
        foreach ($this->registry->dependentsOf($id) as $dep) {
            if (!$this->registry->enabled($dep)) { /* warn */ }
        }
        $this->setStatus($id, 'active');
        $this->cache->forget('modules:manifest');
    }

    public function disable(string $id): void
    {
        $dependents = $this->registry->dependentsOf($id);
        if ($dependents !== []) {
            throw new ModuleException(messageId: 'module.has_dependents',
                context: ['dependents'=>$dependents],
                hint: 'ابتدا ماژول‌های وابسته را غیرفعال کنید.', );
        }
        $this->setStatus($id, 'disabled');
    }
}
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| manifest مخرب | Schema validation + id/version format |
| ماژول جعلی با namespace هسته | `locked_prefixes` (DOC-082) |
| چرخهٔ وابستگی | TopologicalSorter با تشخیص |
| کد مخرب در provider | review + sandbox برای plugin |

## ۸. تست
| T-01 | parse manifest معتبر/نامعتبر |
| T-02 | Topological sort با ۵ ماژول |
| T-03 | چرخه → exception با مسیر |
| T-04 | install/enable/disable |
| T-05 | disable با dependent → رد |
| T-06 | cache manifest |
| T-07 | `Module::run` (HMVC) |

## ۹. معیار پذیرش 🧪
- AC-MOD-01..08 (مطابق DOC-022 §9).

## ۱۰. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.module.v1
engine:
  id: tavaan.engine.module
  namespace: Tavaan\Engine\Module
  path: engines/module
  priority: P0
  phase: M1
  depends_on: [container, config]
  facade: {name: Module, accessor: modules}
  events_emitted: [module.installed, module.enabled, module.disabled, module.updated]
  config_file: config/modules.php
  public_api:
    - class: ModuleRegistry
      methods: [discover, all, get, sorted, enabled, dependencies, dependentsOf]
    - class: ModuleManager
      methods: [install, uninstall, enable, disable, update, validate]
    - class: ModuleRunner
      methods: [run]
  loc_estimate: 900
```
