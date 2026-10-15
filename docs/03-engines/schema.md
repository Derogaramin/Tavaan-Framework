# DOC-048 — موتور Schema | Blueprint و DDL

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-048 |
| Engine ID | `tavaan.engine.schema` |
| Namespace | `Tavaan\Engine\Database\Schema` |
| اولویت | **P0** — فاز M3 |
| وابستگی | `database` |
| Facade | `Schema` → `schema` |
| LOC | ۱۱۰۰ |

## ۱. هدف
تعریف ساختار دیتابیس به‌صورت PHP (Blueprint) و تولید DDL معادل برای MySQL/PostgreSQL/SQLite.

## ۲. API
```php
Schema::create('users', function (Blueprint $t): void {
    $t->ulid('id')->primary();
    $t->string('name', 190);
    $t->string('email')->unique();
    $t->string('password');
    $t->text('bio')->nullable();
    $t->unsignedBigInteger('role_id');
    $t->decimal('balance', 18, 4)->default(0);
    $t->enum('status', ['active','banned'])->default('active');
    $t->json('settings')->nullable();
    $t->boolean('is_verified')->default(false);
    $t->timestamp('email_verified_at')->nullable();
    $t->timestamps(3);           // created_at, updated_at با دقت ۳
    $t->softDeletes();           // deleted_at
    $t->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
    $t->index(['status','created_at'], 'idx_users_status');
    $t->fullText(['name','bio']);
});

Schema::table('users', fn (Blueprint $t) => $t->string('phone', 20)->after('email'));
Schema::dropIfExists('users');
Schema::hasTable('users'); Schema::hasColumn('users','email');
Schema::rename('old','new');
Schema::getColumnType('users','balance');
Schema::getTables(); Schema::getColumns('users');
```

## ۳. انواع ستون
`id, ulid, uuid, bigIncrements, string, text, mediumText, longText, char, integer, bigInteger,
unsignedBigInteger, smallInteger, tinyInteger, boolean, decimal, double, float, date, dateTime,
timestamp, time, year, json, jsonb, enum, set, blob, binary, ipAddress, macAddress, ulid,
foreignId, morphs, nullableMorphs, timestamps, softDeletes, rememberToken`

## ۴. Blueprint → ColumnDefinition → Grammar
```php
final readonly class ColumnDefinition
{
    public function __construct(
        public string $name, public string $type,
        public ?int $length = null, public ?int $precision = null, public ?int $scale = null,
        public bool $nullable = false, public mixed $default = null,
        public bool $autoIncrement = false, public bool $unsigned = false,
        public bool $unique = false, public bool $primary = false, public bool $index = false,
        public ?array $enumValues = null, public ?string $after = null,
        public ?string $comment = null, public bool $useCurrent = false,
    ) {}

    // Fluent
    public function nullable(bool $v = true): self { /* clone + set */ }
    public function default(mixed $v): self { /* … */ }
    public function unique(): self { /* … */ }
    public function index(): self { /* … */ }
    public function after(string $col): self { /* … */ }
    public function comment(string $c): self { /* … */ }
}
```

### ۴٫۱ Grammar (نوع به SQL)
```php
// MySqlGrammar
protected function typeString(ColumnDefinition $c): string { return "varchar({$c->length})"; }
protected function typeDecimal(ColumnDefinition $c): string { return "decimal({$c->precision},{$c->scale})"; }
protected function typeEnum(ColumnDefinition $c): string
{
    $values = \implode(',', \array_map(fn($v) => "'{$v}'", $c->enumValues));
    return "enum({$values})";
}
protected function typeUlid(ColumnDefinition $c): string { return 'char(26)'; }
protected function typeJson(ColumnDefinition $c): string { return 'json'; }

// PostgresGrammar — تفاوت‌ها
protected function typeUlid(ColumnDefinition $c): string { return 'char(26)'; }
protected function typeJson(ColumnDefinition $c): string { return 'jsonb'; }   // PG: jsonb بهتر
protected function typeEnum(ColumnDefinition $c): string { /* CREATE TYPE یا varchar+check */ }
protected function typeBoolean(ColumnDefinition $c): string { return 'boolean'; }

// SqliteGrammar
protected function typeDecimal(ColumnDefinition $c): string { return 'numeric'; }
protected function typeEnum(ColumnDefinition $c): string { return 'text check(...)'; }
```

### ۴٫۲ Compile CREATE TABLE
```
CREATE TABLE `users` (
  `id` char(26) NOT NULL,
  `name` varchar(190) NOT NULL,
  ...
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `idx_users_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
```

## ۵. Reverse Engineering (Schema Diff)
```php
// tvn schema:diff → مقایسهٔ مدل فعلی با دیتابیس
Schema::introspect('users');   // خواندن INFORMATION_SCHEMA
```

## ۶. امنیت
| تهدید | کنترل |
|---|---|
| SQL Injection در نام ستون | identifier quoting + allowlist |
| DROP تصادفی | `dropIfExists` فقط در migration؛ تأیید در CLI |
| نوع نامناسب (float پول) | هشدار: `decimal` برای پول |

## ۷. تست
| T-01 | همهٔ انواع ستون → DDL درست در ۳ درایور |
| T-02 | foreign key + onDelete |
| T-03 | index/unique/fullText |
| T-04 | `table()` (ALTER) با after |
| T-05 | enum در ۳ درایور |
| T-06 | introspect |

## ۸. معیار پذیرش 🧪
- AC-SCH-01..06. AC-SCH-07: DDL سه درایور معادل منطقی تولید می‌کنند.

## ۹. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.schema.v1
engine:
  id: tavaan.engine.schema
  namespace: Tavaan\Engine\Database\Schema
  path: engines/schema
  priority: P0
  phase: M3
  depends_on: [database]
  facade: {name: Schema, accessor: schema}
  public_api:
    - class: SchemaBuilder
      methods: [create, table, drop, dropIfExists, rename, hasTable, hasColumn, getColumns, getTables, introspect]
    - class: Blueprint
      methods: [all column types, primary, unique, index, foreign, fullText, timestamps, softDeletes]
  loc_estimate: 1100
```
