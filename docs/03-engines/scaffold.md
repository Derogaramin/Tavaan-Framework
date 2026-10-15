# DOC-077 — موتور Scaffold | تولید کد (make:*)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-077 |
| Engine ID | `tavaan.engine.scaffold` |
| Namespace | `Tavaan\Engine\Scaffold` |
| اولویت | **P1** — فاز M8 |
| وابستگی | `console`, `filesystem` |
| LOC | ۱۲۰۰ |

## ۱. هدف
تولید خودکار کد از `.stub` برای کاهش Boilerplate و حفظ یکدستی: ماژول، کنترلر، مدل،
migration، request، policy، command، job، event، listener، seeder، factory، test، widget، resource، موتور.

## ۲. دستورات
```bash
tvn make:module shop --domain=hexagonal
tvn make:controller Shop/ProductController --resource --api
tvn make:model Product --migration --factory --seeder --controller
tvn make:migration create_products_table --module=shop
tvn make:request StoreProductRequest --module=shop
tvn make:policy ProductPolicy --model=Product
tvn make:command OrdersCleanup
tvn make:job SendInvoiceEmail
tvn make:event OrderWasPaid
tvn make:listener SendInvoiceOnOrderPaid --event=OrderWasPaid
tvn make:seeder ProductSeeder
tvn make:factory ProductFactory
tvn make:test ProductTest --unit
tvn make:widget RecentOrdersWidget --module=shop
tvn make:resource ProductResource --module=shop
tvn make:engine cache
tvn make:middleware EnsureShopEnabled
```

## ۳. Stub Engine
```php
final class StubRenderer
{
    /**
     * @param array<string, string|bool> $vars
     */
    public function render(string $stubPath, array $vars): string
    {
        $content = \file_get_contents($stubPath) ?: '';

        // جایگزینی {{var}}
        $content = \preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static function (array $m) use ($vars): string {
            $key = $m[1];
            return isset($vars[$key]) ? (string) $vars[$key] : $m[0];
        }, $content) ?? $content;

        // بلوک‌های شرطی {{#if flag}} ... {{/if}}
        $content = $this->renderConditionals($content, $vars);
        // حلقه‌ها {{#each items}} ... {{/each}}
        $content = $this->renderLoops($content, $vars);

        return $content;
    }
}
```
🔒 Stub Engine **خودنوشته** (بدون Twig/Handlebars). قالب‌ها در `resources/stubs/`.

## ۴. نمونه Stub
```stub
<?php
declare(strict_types=1);

namespace {{namespace}};

use Tavaan\Engine\Http\Controller;
{{#if resource}}
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
{{/if}}

/**
 * {{description}}
 */
final class {{class}} extends Controller
{
{{#if resource}}
    public function index(): Response
    {
        return view('{{view_prefix}}index');
    }

    public function create(): Response
    {
        return view('{{view_prefix}}create');
    }

    public function store(Request $request): Response
    {
        // TODO: validate + store
        return redirect()->route('{{route_prefix}}index');
    }

    public function show({{model}} ${{modelVariable}}): Response
    {
        return view('{{view_prefix}}show', compact('{{modelVariable}}'));
    }
{{/if}}
{{#unless resource}}
    public function __invoke(Request $request): Response
    {
        // TODO: implement
    }
{{/unless}}
}
```

## ۵. Generator هر موجودیت
```php
final class ModelGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $name = $cmd->argument('name');
        $vars = [
            'namespace' => $this->namespaceFor($cmd),
            'class' => $name,
            'table' => Str::snake(Str::plural($name)),
            'fillable' => $cmd->option('fillable') ? "['" . \implode("','", $this->guessFields($cmd)) . "']" : '[]',
        ];
        $files = [$this->targetPath($cmd, $name . '.php') => $this->render('model.stub', $vars)];

        if ($cmd->option('migration')) {
            $files = \array_merge($files, $this->migrationGenerator->generate($cmd));
        }
        if ($cmd->option('factory')) { /* … */ }
        if ($cmd->option('controller')) { /* … */ }
        return $files;
    }
}
```

## ۶. make:module (کامل)
```bash
tvn make:module shop
```
ساختار تولیدشده:
```
modules/shop/
├── module.json              (با id/version/deps خالی)
├── composer.json
├── src/
│   ├── Http/Controllers/.gitkeep
│   ├── Domain/{Entities,ValueObjects,Services,Events}/.gitkeep
│   ├── Application/{UseCases,Commands,Queries}/.gitkeep
│   ├── Infrastructure/Persistence/.gitkeep
│   ├── Contracts/.gitkeep
│   └── Providers/ShopServiceProvider.php
├── config/shop.php
├── routes/{web,api,admin}.php
├── views/.gitkeep
├── lang/{fa,en}/shop.php
├── migrations/.gitkeep
├── seeds/.gitkeep
├── tests/Unit/.gitkeep
└── README.md
```
+ ثبت خودکار در `config/modules.php`.

## ۷. Idempotency و Overwrite
```php
// اگر فایل وجود داشت
if (\file_exists($target)) {
    if (!$cmd->option('force')) {
        $this->output->warn("فایل {$target} وجود دارد. با --force بازنویسی کنید.");
        return;
    }
    $this->backup($target);    // → .bak
}
```

## ۸. امنیت
| تهدید | کنترل |
|---|---|
| نام کلاس نامعتبر | validate با regex (PascalCase) |
| Path traversal در نام | sanitize + sandbox |
| Overwrite ناخواسته | `--force` + backup |

## ۹. تست
| T-01 | Stub rendering (var/if/each) |
| T-02 | make:model با همهٔ flagها |
| T-03 | make:module ساختار کامل |
| T-04 | overwrite با --force + backup |
| T-05 | namespace صحیح برای module/app |
| T-06 | نام نامعتبر → رد |

## ۱۰. معیار پذیرش 🧪
- AC-SCF-01: همهٔ دستورات §2 فایل معتبر تولید می‌کنند که `php -l` پاس شود.
- AC-SCF-02: `make:module` → `tvn module:validate` پاس.
- AC-SCF-03: فایل تولیدشده PHPStan level max پاس شود.

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.scaffold.v1
engine:
  id: tavaan.engine.scaffold
  namespace: Tavaan\Engine\Scaffold
  path: engines/scaffold
  priority: P1
  phase: M8
  depends_on: [console, filesystem]
  facade: null
  commands: [make:module, make:controller, make:model, make:migration, make:request,
             make:policy, make:command, make:job, make:event, make:listener,
             make:seeder, make:factory, make:test, make:widget, make:resource, make:engine, make:middleware]
  public_api:
    - class: GeneratorManager
      methods: [generate, register]
  loc_estimate: 1200
```
