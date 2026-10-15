# DOC-078 — موتور Admin | پیشخان و CRUD Generator

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-078 |
| Engine ID | `tavaan.engine.admin` |
| Namespace | `Tavaan\Engine\Admin` |
| اولویت | **P2** — فاز M8 |
| وابستگی | `view`, `rbac`, `orm`, `validation`, `http` |
| Facade | `Admin` |
| LOC | ۲۵۰۰ |

## ۱. هدف
پیشخان مدیریت آماده با **CRUD Generator** (از Model → صفحات List/Create/Edit/Show)،
منوی پویا از manifest ماژول‌ها، Widgetهای داشبورد، و RTL کامل — بدون نوشتن کد تکراری.

## ۲. CRUD Generator
```php
// تعریف یک Resource مدیریتی
Admin::resource(Product::class, function (Resource $r): void {
    $r->title('محصولات');
    $r->icon('package');
    $r->permission('shop.product');            // → shop.product.view/create/update/delete

    $r->columns(['id','title','price','stock','status','created_at'])
      ->searchable(['title','slug'])
      ->filterable(['status'=>'select','category_id'=>'relation'])
      ->sortable(['price','created_at'])
      ->perPage(25);

    $r->form(function (Form $f): void {
        $f->text('title')->required()->maxLength(255);
        $f->slug('slug')->from('title');
        $f->textarea('summary')->rows(3);
        $f->richtext('description')->sanitizer('rich_text');   // 🔒 با Sanitizer
        $f->money('price')->currency('IRR');
        $f->number('stock')->min(0);
        $f->select('status')->options(ProductStatus::class);
        $f->relation('category_id')->model(Category::class)->display('title');
        $f->media('images')->multiple()->disk('public');
        $f->repeater('attributes', function (Repeater $rep): void {
            $rep->text('key'); $rep->text('value');
        });
    });

    $r->actions([
        Action::make('publish')->permission('shop.product.publish')->handler(fn($m) => $m->update(['status'=>'active'])),
        Action::bulk('delete')->permission('shop.product.delete'),
    ]);

    $r->show(function (Show $s): void {
        $s->fields(['title','price','stock']);
        $s->relation('reviews')->columns(['author','rating','body']);
    });
});
```

## ۳. تولید خودکار صفحات
```
Admin::resource(Product::class, …)
  ↓
تولید خودکار:
  GET    /admin/products          → List (با filter/search/sort/paginate)
  GET    /admin/products/create   → Form
  POST   /admin/products          → Store (با validation خودکار از form)
  GET    /admin/products/{id}     → Show
  GET    /admin/products/{id}/edit→ Form
  PUT    /admin/products/{id}     → Update
  DELETE /admin/products/{id}     → Delete (با policy)
```
🔒 همهٔ این‌ها با **Policy/Permission** محافظت می‌شوند (از rbac).
🔒 Validation خودکار از تعریف Form.

## ۴. منوی پویا (از manifest)
```php
// در module.json: "navigation": [...]
Admin::menu(function (Menu $m): void {
    $m->add('داشبورد', route: 'admin.dashboard', icon: 'home', order: 1);
    // بقیه خودکار از navigation ماژول‌ها + permission filter
});
```
🔒 آیتم‌های منو بر اساس مجوز کاربر فیلتر می‌شوند (`@can`).

## ۵. داشبورد Widget
```php
Admin::dashboard(function (Dashboard $d): void {
    $d->widget(StatsWidget::class, ['label'=>'فروش امروز', 'metric'=>'sales_today']);
    $d->widget(RecentOrdersWidget::class);        // HMVC widget از ماژول shop
    $d->chart('sales_chart', data: fn() => /*…*/, type: 'line');
});
```
→ Widgetها با HMVC (`@module`) رندر می‌شوند (DOC-021).

## ۶. Form Field Types
`text, textarea, number, money, email, password, select, multiselect, checkbox, radio,
switch, date, jalali_date, time, datetime, file, media, richtext, code, json, relation,
repeater, hidden, static, color, slug, tags`

### ۶٫۱ فیلد تاریخ جلالی
```php
$f->jalaliDate('published_at')->format('Y/m/d');
// در UI: date picker جلالی؛ در DB: UTC میلادی
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| دسترسی غیرمجاز | Policy/Permission روی هر عملیات |
| Mass Assignment | فرم فقط فیلدهای تعریف‌شده |
| XSS در richtext | Sanitizer (موتور security) |
| CSRF | middleware |
| IDOR در show/edit | Policy + Ownership |
| نشت در list | فقط ستون‌های تعریف‌شده |

🔒 Admin همیشه پشت `auth` + `can:*` + (برای نقش‌های بالا) 2FA.

## ۸. RTL و i18n
🔒 Layout پیش‌فرض RTL، فونت فارسی (Vazirmatn تعبیه‌شده)، همهٔ برچسب‌ها از `trans()`.
💡 Theme قابل override در `resources/views/vendor/admin/`.

## ۹. تست
| T-01 | CRUD generator → ۷ route |
| T-02 | Permission روی هر عملیات |
| T-03 | Form validation خودکار |
| T-04 | Sanitizer در richtext |
| T-05 | منو بر اساس مجوز |
| T-06 | Widget داشبورد |
| T-07 | jalali date field |
| T-08 | RTL layout |

## ۱۰. معیار پذیرش 🧪
- AC-ADM-01: `Admin::resource` بدون کد اضافی CRUD کامل تولید می‌کند.
- AC-ADM-02: همهٔ عملیات با Policy محافظت می‌شوند.
- AC-ADM-03: UI کاملاً RTL و ترجمه‌شده.
- AC-ADM-04: Theme قابل override.

## ۱۱. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Nova/Filament | CRUD generator | خودنوشته، RTL/جلالی native، بدون Livewire |
| Voyager | Admin | — |
| Django Admin | auto CRUD | الهام از declarative resource |

## ۱۲. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.admin.v1
engine:
  id: tavaan.engine.admin
  namespace: Tavaan\Engine\Admin
  path: engines/admin
  priority: P2
  phase: M8
  depends_on: [view, rbac, orm, validation, http]
  facade: {name: Admin, accessor: admin}
  config_file: config/admin.php
  public_api:
    - class: AdminManager
      methods: [resource, menu, dashboard, widget]
    - class: Resource
      methods: [title, columns, searchable, filterable, sortable, form, actions, show]
    - class: Form
      methods: [text, textarea, number, money, select, relation, media, richtext, jalaliDate, repeater]
  loc_estimate: 2500
```
