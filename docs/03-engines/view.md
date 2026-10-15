# DOC-043 — موتور View | قالب‌ساز `.tv.php`

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-043 |
| Engine ID | `tavaan.engine.view` |
| Namespace | `Tavaan\Engine\View` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `event`, `filesystem`, `i18n`؛ نرم: `rbac`, `http` |
| Facade | `View` → `view` |
| LOC | ۱۸۰۰ |
| پسوند فایل | `.tv.php` (ADR-0008) |

---

## ۱. هدف

یک Template Engine **کامپایل‌شونده، امن (Auto-Escape)، سریع و خودنوشته** که جایگزین
Twig/Blade شود. بدون `eval` در زمان اجرا، با کش کامپایل و پشتیبانی کامل از
Component، Layout، Section، Stack، Include، Widget (HMVC) و Directive سفارشی.

- ✅ سینتک `{{ }}` (escape)، `{!! !!}` (raw)، `@directive`
- ✅ Component با Attribute و Slot (`<x-alert type="x">`)
- ✅ Layout/Section/Stack/Push/Prepend
- ✅ Include با Namespace (`shop::product.card`)
- ✅ Compile به PHP خالص + Cache با mtime check
- ✅ Auto-Escape زمینه‌ای (HTML/Attr)
- ✅ `@can`, `@auth`, `@guest`, `@if`, `@foreach`, `@forelse`, `@while`, `@for`, `@switch`
- ✅ `@module()` برای HMVC
- ✅ Escape سفارشی و Directive سفارشی
- ❌ خارج: JavaScript/React rendering (فقط SSR)

---

## ۲. معماری

```
 template.tv.php
      │  compile (یک‌بار، با mtime check)
      ▼
┌──────────────────────────────────────────────────────────────┐
│ TemplateCompiler                                              │
│   1. Preprocessor   (کامنت، whitespace، @verbatim)           │
│   2. DirectivePass  (@if/@foreach/@can/@module/…)             │
│   3. EchoPass       ({{ }} → escape / {!! !!} → raw)          │
│   4. ComponentPass  (<x-*> → Component class/include)         │
│   5. InheritancePass(@extends/@section/@yield/@stack)         │
│   6. PhpGuard       (جلوگیری از <? خام در خروجی ناامن)        │
└───────────────────────────┬──────────────────────────────────┘
                            ▼
              storage/framework/views/<hash>.php
                            │
                            ▼
                    ┌───────────────┐    ┌──────────────────┐
                    │ ViewFactory    │───▶│ View (render)    │
                    │ (finder+share) │    │ + Data + Escaper │
                    └───────────────┘    └──────────────────┘
```

### ۲٫۱ کلاس‌ها
| کلاس | مسئولیت |
|---|---|
| `ViewFactory` | make/share/exists/namespace |
| `View` | یک نمونهٔ ویو (render/with) |
| `ViewFinder` | یافتن فایل با سلسله‌مراتب override |
| `TemplateCompiler` | orchestrator کامپایل |
| `Compilers/*Pass` | ۶ pass کامپایل |
| `ComponentRegistry` | نگاشت `<x-name>` → class/file |
| `Component` | کلاس پایهٔ کامپوننت |
| `SectionStack` | مدیریت section/stack |
| `Escaper` | HTML/Attr/JS/URL escape |
| `DirectiveRegistry` | ثبت directive سفارشی |
| `ViewServiceProvider`, `ViewFacade` | ثبت |

---

## ۳. سینتک

### ۳٫۱ Echo
```blade
{{ $product->title }}              {{-- htmlspecialchars — همیشه --}}
{!! $product->descriptionHtml() !!}{{-- خام — فقط محتوای trusted --}}
{{ money($price) }}                {{-- هر عبارت PHP --}}
@{{ vueVariable }}                 {{-- بدون پردازش (برای JS frameworks) --}}
```

### ۳٫۲ کنترل جریان
```blade
@if ($user->isAdmin())
    ...
@elseif ($user->isEditor())
    ...
@else
    ...
@endif

@unless ($errors->any())
    <p>بدون خطا</p>
@endunless

@foreach ($orders as $order)
    <li>{{ $order->id }}</li>
@empty
    <li>سفارشی نیست</li>
@endforelse

@for ($i = 0; $i < 10; $i++) ... @endfor
@while ($x) ... @endwhile
@switch ($status)
    @case('paid') پرداخت @break
    @default نامشخص
@endswitch

@isset($x) ... @endisset
@empty($x) ... @endempty
```

### ۳٫۳ Layout و Section
```blade
{{-- resources/views/layouts/app.tv.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head><title>@yield('title', config('app.name'))</title></head>
<body>
    @include('partials.header')
    <main>@yield('content')</main>
    @include('partials.footer')
    @stack('scripts')
</body>
</html>
```
```blade
{{-- صفحه --}}
@extends('layouts.app')
@section('title', 'محصولات')
@section('content')
    <h1>محصولات</h1>
@endsection
@push('scripts')
    <script src="{{ asset('shop.js') }}" defer></script>
@endpush
```

### ۳٫۴ Include و Namespace
```blade
@include('shop::product.card', ['product' => $p])
@includeIf('shop::product.badge')            {{-- اگر نبود، خطا نده --}}
@includeWhen($user->isAdmin(), 'admin.bar')
@includeFirst(['shop::product.v2', 'shop::product.v1'])
```

### ۳٫۵ Component
```blade
{{-- استفاده --}}
<x-alert type="danger" :dismissible="true" title="خطا">
    <x-slot name="icon">⚠️</x-slot>
    محتوای هشدار: {{ $message }}
</x-alert>

<x-form.input name="email" label="ایمیل" :value="old('email')" required />
<x-table :items="$orders" :columns="['id','total']" />
```
```php
{{-- resources/views/components/alert.tv.php --}}
@props(['type' => 'info', 'dismissible' => false, 'title' => null])
<div class="alert alert--{{ $type }}" @if($dismissible) data-dismissible @endif>
    @if($title)<strong>{{ $title }}</strong>@endif
    {{ $icon ?? '' }}
    {{ $slot }}
</div>
```
```php
// یا به‌صورت کلاس
final class AlertComponent extends Component
{
    public function __construct(public string $type = 'info', public bool $dismissible = false) {}
    public function render(): string { return view('components.alert'); }
}
```

### ۳٫۶ Directiveهای امنیتی/دامنه‌ای
```blade
@can('shop.product.update', $product) ... @endcan
@cannot('shop.product.delete') ... @endcannot
@auth ... @endauth
@guest ... @endguest
@role('admin') ... @endrole
@permission('shop.order.refund') ... @endpermission
@module('shop:cart/summary', ['compact' => true])      {{-- HMVC --}}
@defer('stats:heavy-chart')                            {{-- lazy widget --}}
@cache('sidebar.menu', ttl: 300) ... @endcache
@trans('shop.product.title')
@choice('shop.cart.items', $count)
@csrf                                                  {{-- hidden input --}}
@method('PUT')
@json($data)                                           {{-- JSON امن در <script> --}}
@verbatim ... @endverbatim
```

---

## ۴. کامپایلر — Passها

### ۴٫۱ ترتیب (حیاتی)
```
1. protectVerbatim()     → @verbatim…@endverbatim را با placeholder حفظ
2. stripComments()       → {{-- … --}} حذف (چندخطی، غیرحریص)
3. compileComponents()   → <x-*> → فراخوانی Component
4. compileDirectives()   → @if/@foreach/… (با recursive برای @section)
5. compileEchos()        → {{ }} و {!! !!}
6. restoreVerbatim()
7. guardRawPhp()         → بررسی <? خام
```

### ۴٫۲ EchoPass
```php
// {{ expr }}  →  escape
'/\{\{\s*(.+?)\s*\}\}/s'
  → '<?php echo \Tavaan\Engine\View\Escaper::html((string) ($1)); ?>'

// {!! expr !!} → raw
'/\{!!\s*(.+?)\s*!!\}/s'
  → '<?php echo $1; ?>'

// @{{ expr }} → بدون پردازش
'/@(\{\{.+?\}\})/s' → '$1'
```

### ۴٫۳ DirectivePass (نمونه)
```php
private const array SIMPLE_DIRECTIVES = [
    'if'        => 'if (%s):',
    'elseif'    => 'elseif (%s):',
    'unless'    => 'if (! (%s)):',
    'for'       => 'for (%s):',
    'foreach'   => 'foreach (%s):',
    'forelse'   => '$__empty = true; foreach (%s): $__empty = false;',
    'while'     => 'while (%s):',
    'switch'    => 'switch (%s):',
    'isset'     => 'if (isset(%s)):',
    'empty'     => 'if (empty(%s)):',
    'auth'      => 'if (\Tavaan\Core\Facade\Facades\Auth::check()):',
    'guest'     => 'if (\Tavaan\Core\Facade\Facades\Auth::guest()):',
];

private const array CLOSE_DIRECTIVES = [
    'endif' => 'endif;', 'endunless' => 'endif;', 'endfor' => 'endfor;',
    'endforeach' => 'endforeach;', 'endwhile' => 'endwhile;', 'endswitch' => 'endswitch;',
    'endisset' => 'endif;', 'endempty' => 'endif;', 'endauth' => 'endif;', 'endguest' => 'endif;',
    'endcan' => 'endif;', 'endcannot' => 'endif;', 'endrole' => 'endif;', 'endpermission' => 'endif;',
];

private function compileDirective(string $name, ?string $args): string
{
    if (isset(self::SIMPLE_DIRECTIVES[$name])) {
        return '<?php ' . \sprintf(self::SIMPLE_DIRECTIVES[$name], $args ?? '') . ' ?>';
    }
    if ($name === 'empty' && $this->insideForelse) {          // @empty داخل forelse
        return '<?php endforeach; if ($__empty): ?>';
    }
    if ($name === 'endforelse') {
        return '<?php endif; ?>';
    }
    if (isset(self::CLOSE_DIRECTIVES[$name])) {
        return '<?php ' . self::CLOSE_DIRECTIVES[$name] . ' ?>';
    }
    if ($this->directives->has($name)) {                       // سفارشی
        $handler = $this->directives->get($name);
        return (string) $handler($args, $this);
    }
    if ($name === 'case')      { return '<?php case ' . $args . ': ?>'; }
    if ($name === 'break')     { return '<?php break; ?>'; }
    if ($name === 'default')   { return '<?php default: ?>'; }
    if ($name === 'include')   { return $this->compileInclude($args); }
    if ($name === 'extends')   { return $this->compileExtends($args); }
    if ($name === 'section')   { return $this->compileSection($args); }
    if ($name === 'yield')     { return $this->compileYield($args); }
    if ($name === 'stack')     { return $this->compileStack($args); }
    if ($name === 'push')      { return $this->compilePush($args); }
    if ($name === 'can')       { return $this->compileCan($args, negate: false); }
    if ($name === 'cannot')    { return $this->compileCan($args, negate: true); }
    if ($name === 'module')    { return $this->compileModule($args); }
    if ($name === 'csrf')      { return '<input type="hidden" name="_token" value="<?php echo \Tavaan\Core\Facade\Facades\Csrf::token(); ?>">'; }

    throw new TemplateCompilationException(
        messageId: 'view.directive.unknown',
        context: ['directive' => $name],
        hint: 'آیا منظور شما یکی از directiveهای ثبت‌شده بود؟ `tvn view:directives`',
    );
}
```

### ۴٫۴ ComponentPass
```
<x-alert type="danger" :count="$n" class="m-2">
  <x-slot name="icon">⚠️</x-slot>
  content
</x-alert>

→
<?php
$__component = \Tavaan\Core\Facade\Facades\View::component('alert', [
    'type' => 'danger',
    'count' => $n,
    'attributes' => new \Tavaan\Engine\View\ComponentAttributeBag(['class' => 'm-2']),
    'slots' => ['icon' => '⚠️'],
]);
echo $__component->withSlot('default', function () use (&$__data) { ?>
  content
<?php })->render(); ?>
```
🔒 attributeهای `:`-دار = عبارت PHP؛ بدون `:` = رشتهٔ خام.
🔒 attributeهای ناشناخته → `ComponentAttributeBag` (برای `{{ $attributes }}`).

### ۴٫۵ InheritancePass (`@extends`)
```php
public function render(): string
{
    $contents = $this->evaluate($this->compiledPath, $this->data);

    // اگر @extends داشت، layout را با sectionهای جمع‌شده رندر کن
    while (($parent = $this->sectionStack->parentLayout()) !== null) {
        $this->sectionStack->flushSectionsKeepParent();
        $contents = $this->factory->make($parent, $this->data, sections: $this->sectionStack->all())->render();
    }
    return $contents;
}
```
🔒 `@extends` باید **اولین** directive باشد (وگرنه هشدار).
🔒 nesting layout پشتیبانی می‌شود (max 10).

---

## ۵. Escaper (زمینه‌ای)

```php
final class Escaper
{
    public static function html(?string $value): string
    {
        if ($value === null || $value === '') { return ''; }
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    public static function attr(?string $value): string
    {
        return self::html($value);   // با ENT_QUOTES کافی است
    }

    public static function js(mixed $value): string
    {
        $json = \json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $json === false ? 'null' : $json;
    }

    public static function url(?string $value): string
    {
        return $value === null ? '' : \rawurlencode($value);
    }

    public static function css(?string $value): string
    {
        return $value === null ? '' : \preg_replace('/[^a-zA-Z0-9\s\-_#,%.]/', '', $value) ?? '';
    }
}
```
🔒 در HTML، `{{ }}` همیشه `Escaper::html` می‌شود — حتی در attribute (چون `ENT_QUOTES`).
🔒 برای JS داخل `<script>` از `@json()` استفاده شود، نه `{{ }}`.

### ۵٫۱ SafeString
```php
final readonly class SafeString implements \Stringable
{
    public function __construct(private string $value) {}
    public function __toString(): string { return $this->value; }
}

// Escaper::html اگر SafeString باشد → بدون escape
```
⚠️ استفاده از `SafeString` فقط برای خروجی sanitizer (موتور `security`) مجاز است.

---

## ۶. ViewFinder — سلسله‌مراتب Override

```
یافتن 'shop::product.card':
1. resources/views/vendor/shop/product/card.tv.php      ← override کاربر
2. modules/shop/views/product/card.tv.php               ← خود ماژول

یافتن 'product.card' (بدون namespace):
1. resources/views/product/card.tv.php
2. app/views/product/card.tv.php

یافتن 'mail::invoice':
1. resources/views/vendor/mail/invoice.tv.php
2. engines/mail/views/invoice.tv.php
```
🔒 اولین فایل موجود برنده است.
🔒 `namespace:path` در `ViewFactory::addNamespace()` ثبت می‌شود.

---

## ۷. Compile Cache

```php
public function compiledPath(string $template): string
{
    $path = $this->finder->find($template);
    $hash = \hash('xxh128', $path . '|' . \filemtime($path) . '|' . self::COMPILER_VERSION);
    return $this->cachePath . '/' . $hash . '.php';
}

public function isExpired(string $template): bool
{
    $compiled = $this->compiledPath($template);
    return !\is_file($compiled) || \filemtime($compiled) < \filemtime($this->finder->find($template));
}
```
🔒 hash شامل `filemtime` است → تغییر خودکار template باعث invalidate می‌شود (بدون نیاز به `view:clear` در dev).
🔒 در PROD با `tvn view:cache` همه از قبل کامپایل شده‌اند و `filemtime` چک نمی‌شود (سرعت).
🔒 `COMPILER_VERSION` برای invalidate پس از تغییر کامپایلر.

---

## ۸. Directive سفارشی

```php
View::directive('money', function (?string $expression): string {
    return "<?php echo \Tavaan\Support\Money::parse({$expression})->format(); ?>";
});

View::directive('jalali', fn (?string $e): string => "<?php echo jalali({$e}); ?>");

View::if('production', fn (): bool => app()->isProduction());
// @production ... @endproduction

View::composer('shop::product.card', function (View $view): void {
    $view->with('related', $this->products->related($view->getData('product')));
});
```
🔒 Directive سفارشی باید خروجی PHP **escape‌شده** تولید کند.

---

## ۹. پیکربندی

```php
// engines/view/config/view.php
return [
    'paths' => [
        base_path('resources/views'),
        base_path('app/views'),
    ],
    'extension'    => '.tv.php',
    'compiled'     => storage_path('framework/views'),
    'cache'        => env_bool('TVN_VIEW_CACHE', false),
    'auto_escape'  => true,           // 🔒 هرگز در prod خاموش نشود
    'namespaces'   => [],             // افزوده توسط ماژول‌ها
    'components'   => [
        'paths'      => [base_path('resources/views/components')],
        'namespaces' => ['x' => 'components'],
    ],
    'directives'   => [],
    'shared'       => [],             // دادهٔ مشترک همهٔ ویوها
    'max_depth'    => 10,             // حداکثر تودرتویی layout/include
    'hmvc'         => ['max_depth' => 4, 'isolate_errors' => true],
];
```

---

## ۱۰. امنیت

| تهدید | کنترل |
|---|---|
| XSS از `{{ }}` | Auto-Escape با `ENT_QUOTES\|ENT_HTML5` |
| XSS از `{!! !!}` | فقط برای محتوای sanitize شده؛ بررسی در Review |
| XSS در `<script>` | فقط `@json()` با `JSON_HEX_TAG` |
| Code Injection از directive سفارشی | Directiveها فقط در Provider ثبت می‌شوند (نه از ورودی) |
| Path Traversal در include | `ViewFinder` با realpath + sandbox |
| Recursive include | `max_depth` |
| `<?php` خام در template کاربر | `guardRawPhp` — در templateهای آپلودی ممنوع |
| نشت داده در `@json` | Masking برای فیلدهای حساس |

🔒 **قانون طلایی:** template هرگز از ورودی کاربر ساخته نمی‌شود (`View::make($_GET['tpl'])` ممنوع).

---

## ۱۱. کارایی

| عملیات | هدف |
|---|---|
| کامپایل یک template ۱۰۰ خطی | ≤ ۳ms |
| رندر template کامپایل‌شده ۱۰۰ خط | ≤ ۰٫۵ms |
| `Escaper::html` برای ۱KB | ≤ ۵µs |
| Resolve ViewFinder (با کش namespace) | ≤ ۵µs |
| Component با ۵ attribute | ≤ ۰٫۱ms |
| حافظهٔ رندر صفحهٔ کامل | ≤ ۳MB |

---

## ۱۲. تست
| ID | سناریو |
|---|---|
| T-01 | `{{ }}` روی `<script>` → escaped |
| T-02 | `{!! !!}` → raw |
| T-03 | `@if/@elseif/@else/@endif` ترکیبی |
| T-04 | `@foreach/@empty/@endforelse` |
| T-05 | `@extends` + `@section` + `@yield` |
| T-06 | Nested layout (۳ سطح) |
| T-07 | `@push`/`@stack` با ترتیب صحیح |
| T-08 | `@include` با namespace و override |
| T-09 | `<x-alert>` با props و slot |
| T-10 | Component class-based |
| T-11 | `@can` با Policy |
| T-12 | `@module` HMVC با depth limit |
| T-13 | Compile cache: تغییر template → invalidate |
| T-14 | Directive نامعتبر → `TemplateCompilationException` |
| T-15 | Recursive include → خطا در max_depth |
| T-16 | `@json` با دادهٔ فارسی → Unicode سالم |
| T-17 | `@verbatim` → بدون پردازش |
| T-18 | XSS Fuzzing: ۲۰۰ payload |

---

## ۱۳. معیار پذیرش 🧪
- AC-VIEW-01: همهٔ تست‌های §12.
- AC-VIEW-02: Auto-Escape در همهٔ `{{ }}` (۰ مورد XSS در Fuzzing).
- AC-VIEW-03: کارایی §11.
- AC-VIEW-04: `tvn view:cache` و `view:clear` بدون خطا.
- AC-VIEW-05: Override سلسله‌مراتبی درست کار می‌کند.

---

## ۱۴. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Blade (Laravel) | سینتک `@directive` و `{{ }}` | کامپایل به PHP خالص بدون `eval`، Component سبک‌تر |
| Twig | Auto-Escape، Sandbox | بدون Runtime parser (کامپایل)، سینتک ساده‌تر |
| Laravel Components | `<x-*>` | AttributeBag ساده‌تر، بدون anonymous class سنگین |
| Plates PHP | Plain PHP templates | افزودن کامپایلر و directive |

---

## ۱۵. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.view.v1
engine:
  id: tavaan.engine.view
  namespace: Tavaan\Engine\View
  path: engines/view
  priority: P0
  phase: M2
  depends_on: [event, filesystem, i18n]
  soft_depends_on: [rbac, http]
  facade: {name: View, accessor: view}
  tables: []
  events_emitted: [view.composing, view.created, view.compiled]
  config_file: engines/view/config/view.php
  public_api:
    - class: ViewFactory
      methods: [make, share, exists, addNamespace, addLocation, directive, composer,
                component, renderEach, file, first]
    - class: View
      methods: [render, with, withData, getData, nest, extends, section]
    - class: Escaper
      methods: [html, attr, js, url, css]
  loc_estimate: 1800
  acceptance:
    - {id: AC-VIEW-02, test: 'engines/view/tests/Security/XssFuzzTest.php'}
    - {id: AC-VIEW-03, test: 'engines/view/benchmarks/render.bench.php'}
```
