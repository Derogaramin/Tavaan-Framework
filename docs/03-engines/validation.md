# DOC-051 — موتور Validation | اعتبارسنجی

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-051 |
| Engine ID | `tavaan.engine.validation` |
| Namespace | `Tavaan\Engine\Validation` |
| اولویت | **P0** — فاز M3 |
| وابستگی | `i18n`, `database`, `config` |
| Facade | `Validator` → `validator` |
| LOC | ۲۲۰۰ |

---

## ۱. هدف

اعتبارسنجی قدرتمند با **۸۰+ قاعدهٔ داخلی**، قواعد سفارشی، FormRequest، پیام‌های
ترجمه‌پذیر، و Validation مبتنی بر Attribute.

---

## ۲. API

```php
// مستقیم
$validator = Validator::make(
    data: ['email' => 'a@b.c', 'age' => 20, 'mobile' => '09121234567'],
    rules: [
        'email'  => ['required', 'email', 'max:255', 'unique:users,email'],
        'age'    => ['required', 'integer', 'between:18,120'],
        'mobile' => ['required', 'iran_mobile'],
    ],
    messages: ['email.unique' => 'این ایمیل قبلاً ثبت شده است.'],
    attributes: ['email' => 'ایمیل', 'age' => 'سن'],
);

if ($validator->fails()) {
    throw new ValidationException($validator->errors()->all());
}
$data = $validator->validate();        // دادهٔ validated (فقط فیلدهای قاعده‌دار)

// میان‌بر
$data = Validator::validate($request->all(), $rules);   // throws on fail

// FormRequest
final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('shop.order.create'); }
    public function rules(): array
    {
        return [
            'items'   => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:shop_products,id'],
            'items.*.qty'        => ['required','integer','min:1','max:100'],
        ];
    }
    public function messages(): array { return ['items.required' => 'سبد خرید خالی است.']; }
    protected function prepareForValidation(): void { $this->merge(['total' => $this->computeTotal()]); }
}
// در کنترلر: public function store(StoreOrderRequest $req) → خودکار authorize+validate
```

### ۲٫۱ Rule String Syntax
```
'field' => 'required|string|max:255|unique:users,email,{id}'
'field' => ['required', new MyCustomRule(), Rule::in(['a','b'])]
```
جداساز: `|`؛ پارامتر: `:`؛ پارامتر چندگانه: `,`.

---

## ۳. قواعد داخلی (۸۵ قاعده)

### ۳٫۱ حضور و نوع
`required`, `required_if:f,v`, `required_unless`, `required_with`, `required_without`,
`nullable`, `present`, `filled`, `sometimes`, `string`, `integer`, `numeric`, `boolean`,
`array`, `object`, `date`, `digits:n`, `digits_between:a,b`

### ۳٫۲ طول و بازه
`min:n`, `max:n`, `between:a,b`, `size:n`, `length:n`, `gt:f`, `gte:f`, `lt:f`, `lte:f`

### ۳٫۳ قالب
`email`, `url`, `uuid`, `ulid`, `ip`, `ipv4`, `ipv6`, `mac_address`, `regex:/…/`,
`alpha`, `alpha_num`, `alpha_dash`, `slug`, `json`, `date_format:Y-m-d`, `timezone`

### ۳٫۴ مقایسه و مجموعه
`in:a,b,c`, `not_in`, `confirmed`, `same:field`, `different:field`, `accepted`, `declined`

### ۳٫۵ فایل
`file`, `image`, `mimes:jpg,png`, `mimetypes:image/*`, `dimensions:min_width=100`,
`max_filesize:2048` (KB), `uploaded_file`

### ۳٫۶ دیتابیس
`unique:table,column,ignoreId,ignoreColumn`, `exists:table,column`,
`exists_multiple:table,column`, `unique_composite:table,col1,col2`

### ۳٫۷ بومی ایران 🇮🇷
`iran_mobile` (09xxxxxxxxx), `iran_national_code` (کد ملی ۱۰ رقمی با checksum),
`iran_postal_code` (۱۰ رقمی), `iran_sheba` (IR + ۲۴ رقم با checksum),
`iran_card_number` (۱۶ رقم با checksum), `iran_company_id` (شناسهٔ ملی شرکت),
`jalali_date`, `jalali_date_format:Y/m/d`, `persian_digits`

### ۳٫۸ امنیتی
`honeypot:field`, `no_html`, `safe_url` (SSRF), `password` (سیاست رمز),
`strong_password`, `not_compromised` (بررسی در blacklist)

### ۳٫۹ ریاضی/پول
`decimal:2`, `money`, `positive`, `negative`, `non_negative`, `percent`, `numeric_range:a,b`

### ۳٫۱۰ پیشرفته
`nested`, `each`, `prohibited`, `prohibited_if`, `exclude_if`, `distinct`,
`callback`, `rule_object`, `conditional`

---

## ۴. معماری و الگوریتم

```
Validator::make(data, rules)
   │
   ├─ parseRules()         → rule string → list<Rule>
   ├─ explodeRules()       → 'items.*.qty' → wildcard expansion
   ├─ prepareData()        → prepareForValidation, type coercion
   │
   └─ foreach attribute:
        foreach rule:
           ├─ implicit? (required حتی اگر نباشد)
           ├─ skip اگر nullable و مقدار null
           ├─ execute rule → passes/fails
           ├─ if fails → addError(attribute, messageId, params)
           └─ stopOnFirst (bail) اگر فعال
   │
   └─ ValidatedData (فقط فیلدهای قاعده‌دار) + MessageBag
```

### ۴٫۱ Wildcard (`items.*.qty`)
```
1. یافتن همهٔ مسیرهای واقعی مطابق الگو در data
2. برای هر مسیر، قاعده را اعمال کن
3. جمع‌آوری خطاها با کلید واقعی (items.0.qty)
```

### ۴٫۲ قاعدهٔ سفارشی
```php
interface RuleInterface
{
    public function passes(string $attribute, mixed $value): bool;
    public function message(): string;              // messageId
    public function implicit(): bool;               // حتی اگر null اجرا شود؟
}

final class IranianNationalCode implements RuleInterface
{
    public function passes(string $attribute, mixed $value): bool
    {
        $value = (string) $value;
        if (!\preg_match('/^\d{10}$/', $value)) { return false; }
        if (\in_array($value, ['0000000000','1111111111','2222222222'], true)) { return false; }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) { $sum += (int) $value[$i] * (10 - $i); }
        $remainder = $sum % 11;
        $check = (int) $value[9];

        return ($remainder < 2 && $check === $remainder) || ($remainder >= 2 && $check === 11 - $remainder);
    }
    public function message(): string { return 'validation.iran_national_code'; }
    public function implicit(): bool { return false; }
}
```

### ۴٫۳ قاعدهٔ ناهمزمان (Database)
```php
final class UniqueRule implements RuleInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table,
        private readonly string $column,
        private readonly ?string $ignoreId = null,
        private readonly string $ignoreColumn = 'id',
    ) {}

    public function passes(string $attribute, mixed $value): bool
    {
        $query = $this->db->table($this->table)->where($this->column, $value);
        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }
        return !$query->exists();
    }
    public function message(): string { return 'validation.unique'; }
    public function implicit(): bool { return false; }
}
```

---

## ۵. FormRequest

```php
abstract class FormRequest extends Request
{
    public function authorize(): bool { return true; }
    abstract public function rules(): array;
    public function messages(): array { return []; }
    public function attributes(): array { return []; }
    protected function prepareForValidation(): void {}
    protected function passedValidation(): void {}
    public function validated(?string $key = null, mixed $default = null): mixed { /* … */ }
    public function withValidator(Validator $v): void { /* افزودن قاعدهٔ پویا */ }
}
```
چرخهٔ زندگی در Controller Resolution:
```
1. ساخت FormRequest از Request جاری
2. prepareForValidation()
3. authorize() → اگر false، AuthorizationException (403)
4. Validator::make(all(), rules(), messages(), attributes())
5. اگر fail → ValidationException (422) با redirect back + errors (برای وب) یا JSON (برای API)
6. validated() → DTO
7. passedValidation()
```

---

## ۶. Attribute-Based Validation
```php
final class CreateUserDto
{
    public function __construct(
        #[Required, Email, MaxLength(255), Unique('users','email')]
        public readonly string $email,

        #[Required, Integer, Between(18, 120)]
        public readonly int $age,

        #[IranMobile]
        public readonly string $mobile,
    ) {}
}
// Validator::forDto(CreateUserDto::class, $data)->validate();
```

---

## ۷. MessageBag

```php
$errors = $validator->errors();
$errors->all();                        // list همهٔ پیام‌ها
$errors->first('email');               // اولین پیام email
$errors->get('email');                 // list پیام‌های email
$errors->has('email');
$errors->keys();
$errors->toArray();                    // ['email' => [...], 'age' => [...]]
$errors->toJson();
```

🔒 پیام‌ها از `lang/<locale>/validation.php` با کلید `validation.<rule>`.
🔒 Placeholderها: `:attribute`, `:min`, `:max`, `:other`, `:values`.

```php
// lang/fa/validation.php
return [
    'required'  => 'فیلد :attribute الزامی است.',
    'email'     => ':attribute باید یک ایمیل معتبر باشد.',
    'min'       => ['numeric' => ':attribute باید حداقل :min باشد.', 'string' => ':attribute باید حداقل :min نویسه باشد.'],
    'unique'    => ':attribute قبلاً ثبت شده است.',
    'iran_mobile' => 'شمارهٔ موبایل معتبر نیست.',
    'iran_national_code' => 'کد ملی معتبر نیست.',
    'between'   => ['numeric' => ':attribute باید بین :min و :max باشد.'],
    'attributes' => ['email' => 'ایمیل', 'age' => 'سن'],
];
```

---

## ۸. پیکربندی
```php
// engines/validation/config/validation.php
return [
    'locale'            => null,                 // null = app.locale
    'bail'              => false,                // توقف در اولین خطای هر فیلد
    'stop_on_first'     => false,                // توقف کل
    'implicit_rules'    => ['required','required_if','filled','present'],
    'exclude_unvalidated' => true,               // validated() فقط فیلدهای قاعده‌دار
    'short_array_keys'  => false,
    'password_rules'    => ['min:12','letters','numbers','not_compromised'],
    'custom_rules'      => [],
];
```

---

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| ReDoS در `regex:` | محدودیت طول الگو + رد nested quantifier |
| SQL Injection در `unique/exists` | Prepared + identifier allowlist |
| Mass Validation Bypass | `exclude_unvalidated` → فقط validated استفاده شود |
| Timing در `not_compromised` | مقایسهٔ constant-time |
| DoS با آرایهٔ عمیق | `max_depth` + `max_array_size` |

---

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| Validate یک DTO با ۱۰ فیلد | ≤ ۰٫۵ms |
| `unique` (با ایندکس) | ≤ ۳ms |
| Parse rule string | ≤ ۵µs |
| Wildcard روی ۱۰۰ آیتم | ≤ ۵ms |

---

## ۱۱. تست
| ID | سناریو |
|---|---|
| T-01 | هر ۸۵ قاعده: پاس و فیل |
| T-02 | `iran_national_code` با ۲۰ نمونهٔ معتبر/نامعتبر |
| T-03 | `iran_sheba`/`iran_card_number` با checksum |
| T-04 | `unique` با ignore |
| T-05 | Wildcard `items.*.qty` |
| T-06 | FormRequest: authorize fail → 403 |
| T-07 | پیام سفارشی + attribute سفارشی |
| T-08 | `validated()` فقط فیلدهای قاعده‌دار |
| T-09 | قاعدهٔ سفارشی (RuleInterface) |
| T-10 | Attribute-based روی DTO |
| T-11 | ReDoS regex → رد |
| T-12 | ترجمهٔ فارسی/انگلیسی |

---

## ۱۲. معیار پذیرش 🧪
- AC-VAL-01: همهٔ ۸۵ قاعده با تست پاس/فیل.
- AC-VAL-02: قواعد ایرانی با نمونهٔ واقعی (کد ملی/شبا/کارت) درست.
- AC-VAL-03: FormRequest چرخهٔ کامل (authorize→validate→DTO).
- AC-VAL-04: پیام‌ها در fa و en کامل.
- AC-VAL-05: کارایی §10.

---

## ۱۳. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Laravel Validation | rule string، FormRequest | افزودن قواعد ایرانی، Attribute-based |
| Symfony Validator | Constraint/Attribute | ترکیب هر دو سبک |
| respect/validation | Fluent | — |

---

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.validation.v1
engine:
  id: tavaan.engine.validation
  namespace: Tavaan\Engine\Validation
  path: engines/validation
  priority: P0
  phase: M3
  depends_on: [i18n, database, config]
  facade: {name: Validator, accessor: validator}
  tables: []
  config_file: engines/validation/config/validation.php
  rules_count: 85
  iran_specific_rules: [iran_mobile, iran_national_code, iran_postal_code, iran_sheba, iran_card_number, iran_company_id, jalali_date, persian_digits]
  public_api:
    - class: Validator
      methods: [make, validate, extend, replacer, forDto, sometimes]
    - class: FormRequest
      methods: [authorize, rules, messages, attributes, validated, validateResolved]
  loc_estimate: 2200
  acceptance:
    - {id: AC-VAL-02, test: 'engines/validation/tests/Unit/IranianRulesTest.php'}
```
