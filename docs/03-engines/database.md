# DOC-047 — موتور Database | اتصال، Query Builder و Grammar

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-047 |
| Engine ID | `tavaan.engine.database` |
| Namespace | `Tavaan\Engine\Database` |
| اولویت | **P0** — فاز M3 |
| وابستگی | `config`, `event`, `log` |
| Facade | `DB` → `db` |
| افزونه | `pdo` + یکی از `pdo_mysql`/`pdo_pgsql`/`pdo_sqlite` (اجباری) |
| LOC | ۳۲۰۰ |

---

## ۱. هدف

لایهٔ دادهٔ صفر‌وابستگی: مدیریت اتصال، **Query Builder** کامل، **Grammar** مخصوص هر درایور،
تراکنش با Savepoint، Query Log، Read/Write Splitting و Pooling برای Resident.

- ✅ اتصال Lazy با Reconnect خودکار
- ✅ Query Builder: Select/Join/SubQuery/Union/CTE/Aggregate/Window/Lock
- ✅ Prepared Statement ۱۰۰٪ (بدون الحاق رشته)
- ✅ Grammar: MySQL/MariaDB، PostgreSQL، SQLite
- ✅ Transaction با Nesting (Savepoint)، Deadlock Retry، Hook
- ✅ Query Log + Slow Query Detection + N+1 Hint
- ✅ Read/Write Splitting
- ✅ Cursor/Chunk/Lazy برای مجموعهٔ بزرگ
- ✅ Pagination
- ❌ خارج: ORM (موتور `orm`)، Migration (موتور `migration`)

---

## ۲. معماری

```
        ┌──────────────────────────────────────────────┐
        │              ConnectionManager               │  (چند اتصال: default, tenant, reporting)
        └───────┬──────────────────────────────────────┘
                │
        ┌───────▼────────┐   ┌──────────────┐   ┌─────────────────┐
        │  Connection    │──▶│ QueryBuilder │──▶│ Grammar (driver) │
        │ (PDO wrapper)  │   └──────┬───────┘   └────────┬────────┘
        └───────┬────────┘          │ toSql()+bindings   │ compile
                │                   ▼                    ▼
        ┌───────▼────────┐   ┌──────────────────────────────┐
        │ TransactionMgr │   │      PreparedStatement        │
        └───────┬────────┘   └───────────────┬──────────────┘
                │                            ▼
                │                    ┌──────────────┐
                └───────────────────▶│    PDO        │──▶ MySQL/PG/SQLite
                                     └──────┬───────┘
                                            ▼
                              ┌──────────────────────────┐
                              │ QueryLog • Events • Cache │
                              └──────────────────────────┘
```

### ۲٫۱ کلاس‌ها
| کلاس | مسئولیت | LOC |
|---|---|---|
| `ConnectionManager` | چند اتصال، default/tenant | 200 |
| `Connection` (abstract) | PDO wrapper + log + events | 400 |
| `MySqlConnection`/`PostgresConnection`/`SqliteConnection` | تفاوت‌های درایور | 300 |
| `QueryBuilder` | Fluent builder | 900 |
| `Grammar` (abstract) | compile SQL | 400 |
| `MySqlGrammar`/`PostgresGrammar`/`SqliteGrammar` | — | 450 |
| `Processor` | پس‌پردازش نتیجه (cast) | 120 |
| `TransactionManager` | begin/commit/rollback/savepoint | 200 |
| `ConnectionPool` | Resident pooling | 150 |
| `QueryLog` | ثبت کوئری‌ها | 120 |
| `Paginator`/`CursorPaginator` | صفحه‌بندی | 200 |
| `DatabaseServiceProvider` | ثبت | 80 |

---

## ۳. اتصال

### ۳٫۱ پیکربندی
```php
// config/database.php
return [
    'default' => env_string('TVN_DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env_string('TVN_DB_HOST', '127.0.0.1'),
            'port'      => env_int('TVN_DB_PORT', 3306),
            'database'  => env_string('TVN_DB_DATABASE', 'tavaan'),
            'username'  => env_string('TVN_DB_USERNAME', 'root'),
            'password'  => env_string('TVN_DB_PASSWORD', ''),
            'unix_socket' => env_string('TVN_DB_SOCKET', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'prefix'    => env_string('TVN_DB_PREFIX', ''),
            'strict'    => true,
            'engine'    => 'InnoDB',
            'options'   => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,        // 🔒 prepared واقعی
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::ATTR_PERSISTENT         => false,
            ],
            'read'   => ['host' => env_list('TVN_DB_READ_HOST')],   // replica
            'write'  => ['host' => env_string('TVN_DB_HOST')],
            'sticky' => true,                                       // پس از write، read از master
        ],

        'pgsql' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 5432,
            'database' => 'tavaan', 'username' => 'postgres', 'password' => '',
            'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer',
        ],

        'sqlite' => [
            'driver' => 'sqlite', 'database' => storage_path('database/tavaan.sqlite'),
            'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ],

        'sqlite_memory' => ['driver' => 'sqlite', 'database' => ':memory:'],   // تست
    ],

    'pool' => ['enabled' => false, 'min' => 1, 'max' => 10],

    'query_log' => [
        'enabled'        => env_bool('TVN_DB_QUERY_LOG', false),
        'slow_threshold' => 100,        // ms
        'max_entries'    => 500,
    ],

    'n_plus_one_detection' => env_bool('TVN_DB_N1_DETECT', false),
];
```

### ۳٫۲ اتصال Lazy + Reconnect
```php
final class Connection implements ConnectionInterface
{
    private ?\PDO $pdo = null;

    public function pdo(): \PDO
    {
        return $this->pdo ??= $this->createPdo();
    }

    private function createPdo(): \PDO
    {
        $dsn = $this->grammar->dsn($this->config);
        try {
            $pdo = new \PDO($dsn, $this->config['username'] ?? null, $this->config['password'] ?? null,
                            $this->config['options'] ?? []);
        } catch (\PDOException $e) {
            throw new ConnectionException(
                messageId: 'database.connection_failed',
                context: ['driver' => $this->driver(), 'host' => $this->config['host'] ?? null,
                          'database' => $this->config['database'] ?? null],
                hint: 'اعتبارنامه و دسترسی DB را بررسی کنید.',
                previous: $e,
            );
        }
        $this->configure($pdo);      // charset, timezone, sql_mode, …
        return $pdo;
    }

    private function configure(\PDO $pdo): void
    {
        match ($this->driver()) {
            'mysql' => $pdo->exec("SET NAMES '{$this->config['charset']}' COLLATE '{$this->config['collation']}'"),
            'pgsql' => $pdo->exec("SET TIME ZONE 'UTC'"),
            default => null,
        };
        if (($this->config['strict'] ?? false) && $this->driver() === 'mysql') {
            $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }
    }

    /** اجرای کوئری با Reconnect خودکار در صورت «server has gone away» */
    protected function runWithReconnect(string $sql, array $bindings, \Closure $callback): mixed
    {
        try {
            return $callback($this->pdo(), $sql, $bindings);
        } catch (\PDOException $e) {
            if ($this->causedByLostConnection($e) && !$this->reconnected) {
                $this->reconnected = true;
                $this->pdo = null;
                $this->events->dispatch(new DatabaseReconnected($this->name()));
                return $callback($this->pdo(), $sql, $bindings);
            }
            throw $this->mapException($e, $sql, $bindings);
        }
    }

    private function causedByLostConnection(\PDOException $e): bool
    {
        return \in_array($e->getCode(), ['HY000', '2006', '2013'], true)
            && \str_contains(\strtolower($e->getMessage()), 'gone away')
            || \str_contains(\strtolower($e->getMessage()), 'lost connection')
            || \str_contains(\strtolower($e->getMessage()), 'broken pipe');
    }
}
```

---

## ۴. Query Builder — API

→ امضای کامل در **DOC-014 §2.4**. در اینجا نکات رفتاری و مثال:

```php
// SELECT
$users = DB::table('users')
    ->select('id', 'name', 'email')
    ->where('is_active', true)
    ->where(fn ($q) => $q->where('age', '>=', 18)->orWhere('vip', true))
    ->whereIn('role_id', [1, 2, 3])
    ->whereNotNull('email_verified_at')
    ->orderByDesc('created_at')
    ->limit(20)->offset(40)
    ->get();

// JOIN + GROUP + HAVING
$rows = DB::table('orders as o')
    ->join('users as u', 'u.id', '=', 'o.user_id')
    ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
    ->selectRaw('o.status, COUNT(DISTINCT o.id) as cnt, SUM(oi.price * oi.qty) as total')
    ->whereBetween('o.created_at', [$from, $to])
    ->groupBy('o.status')
    ->having('total', '>', 1000)
    ->orderBy('total', 'desc')
    ->get();

// SubQuery
$sub = DB::table('order_items')->selectRaw('order_id')->where('product_id', $pid);
$orders = DB::table('orders')->whereIn('id', $sub)->get();

// CTE
$cte = DB::withCte('recent_orders', fn ($q) => $q->from('orders')->where('created_at', '>', $date));
$rows = $cte->from('recent_orders')->get();

// Union
DB::table('posts')->select('id','title')->union(DB::table('pages')->select('id','title'))->get();

// Aggregate
$count = DB::table('users')->count();
$sum   = DB::table('orders')->sum('total');
$avg   = DB::table('orders')->avg('total');
$exists= DB::table('users')->where('email', $e)->exists();

// Insert / Update / Delete
$id = DB::table('users')->insertGetId(['name' => 'a', 'email' => 'b@c.d', 'created_at' => Clock::utcNow()]);
DB::table('users')->where('id', $id)->update(['name' => 'x', 'updated_at' => Clock::utcNow()]);
DB::table('users')->where('id', $id)->increment('login_count');
DB::table('users')->where('id', $id)->delete();
DB::table('users')->upsert($rows, uniqueBy: ['email'], update: ['name','updated_at']);
DB::table('logs')->insertOrIgnore($rows);

// Lock
DB::table('orders')->where('id', $id)->forUpdate()->first();
DB::table('orders')->where('id', $id)->sharedLock()->first();

// Chunk / Lazy / Cursor
DB::table('products')->orderBy('id')->chunk(500, function (array $products): bool {
    foreach ($products as $p) { /* … */ }
    return true;   // false = توقف
});

foreach (DB::table('products')->orderBy('id')->lazy(1000) as $product) { /* … */ }

// Keyset pagination (سریع برای صفحات عمیق)
$page = DB::table('products')->orderBy('id')->keysetPaginate(perPage: 50, afterId: $lastId);
```

---

## ۵. Grammar — Compile به SQL

### ۵٫۱ ترتیب Compile (MySQL)
```
compileComponents(builder):
  sql = ''
  if builder.ctes        → sql += compileCtes()
  sql += compileSelect()          # SELECT [DISTINCT] columns
  sql += compileFrom()            # FROM table [AS alias]
  sql += compileJoins()
  sql += compileWheres()          # WHERE … (با grouping و boolean)
  sql += compileGroups()          # GROUP BY
  sql += compileHavings()
  sql += compileWindows()         # OVER (…)
  sql += compileOrders()
  sql += compileLimitOffset()     # LIMIT ? OFFSET ?
  sql += compileLock()            # FOR UPDATE / LOCK IN SHARE MODE
  return trim(sql)
```

### ۵٫۲ Identifier Quoting (امنیت)
```php
// MySQL
protected function wrap(string $value): string
{
    if (\str_contains($value, '(') || \str_contains($value, ' as ')) {
        return $value;   // 🔒 فقط برای selectRaw/whereRaw — ورودی کاربر ممنوع
    }
    return \implode('.', \array_map(
        static fn (string $part): string => $part === '*' ? '*' : '`' . \str_replace('`', '``', $part) . '`',
        \explode('.', $value),
    ));
}

// PostgreSQL → "quote"، SQLite → "quote"
```
🔒 **قانون حیاتی:** `wrap()` فقط روی نام‌هایی که از **کد** می‌آیند اعمال می‌شود.
اگر نام ستون از ورودی کاربر باشد (مثل sort field) → باید **allowlist** شود:
```php
$allowedSort = ['id','name','created_at','price'];
$sort = \in_array($request->input('sort'), $allowedSort, true) ? $request->input('sort') : 'id';
$query->orderBy($sort, $request->input('dir') === 'desc' ? 'desc' : 'asc');
```

### ۵٫۳ تفاوت درایورها
| قابلیت | MySQL | PostgreSQL | SQLite |
|---|---|---|---|
| Quote | `` ` `` | `"` | `"` |
| LIMIT/OFFSET | `LIMIT ? OFFSET ?` | یکسان | یکسان |
| Upsert | `ON DUPLICATE KEY UPDATE` | `ON CONFLICT (…) DO UPDATE` | `ON CONFLICT (…) DO UPDATE` |
| InsertOrIgnore | `INSERT IGNORE` | `ON CONFLICT DO NOTHING` | `INSERT OR IGNORE` |
| Lock | `FOR UPDATE` | `FOR UPDATE` | (no-op) |
| Boolean | `tinyint(1)` | `boolean` | `integer` |
| JSON | `JSON_EXTRACT(col,'$.k')` | `col->>'k'` | `json_extract(col,'$.k')` |
| ILIKE | `LIKE … COLLATE` | `ILIKE` | `LIKE` (case-insensitive پیش‌فرض) |
| Random | `RAND()` | `RANDOM()` | `RANDOM()` |
| FullText | `MATCH … AGAINST` | `to_tsvector` | `MATCH` (FTS5) |
| Window | ✅ 8.0+ | ✅ | ✅ 3.25+ |
| CTE | ✅ 8.0+ | ✅ | ✅ 3.8.3+ |
| SKIP LOCKED | ✅ 8.0+ | ✅ 9.5+ | ❌ |

```php
// مثال: compileUpsert
public function compileUpsert(QueryBuilder $q): string
{
    return match ($this->driver) {
        'mysql' => \sprintf('insert into %s (%s) values %s on duplicate key update %s',
            $this->wrapTable($q->from()), $this->columnize($q->insertColumns()),
            $this->compileInsertRows($q), $this->compileUpdateColumns($q->upsertUpdate())),
        'pgsql', 'sqlite' => \sprintf('insert into %s (%s) values %s on conflict (%s) do update set %s',
            $this->wrapTable($q->from()), $this->columnize($q->insertColumns()),
            $this->compileInsertRows($q), $this->columnize($q->upsertUniqueBy()),
            $this->compileUpdateColumns($q->upsertUpdate())),
    };
}
```

---

## ۶. Prepared Statement و Binding

```php
public function select(string $sql, array $bindings = []): array
{
    return $this->runWithReconnect($sql, $bindings, function (\PDO $pdo, string $sql, array $bindings): array {
        $start = \microtime(true);
        $stmt = $pdo->prepare($sql);                    // 🔒 همیشه prepare

        $i = 1;
        foreach ($bindings as $key => $value) {
            $param = \is_int($key) ? $i++ : $key;
            $type = match (true) {
                \is_int($value)  => \PDO::PARAM_INT,
                \is_bool($value) => \PDO::PARAM_BOOL,
                $value === null  => \PDO::PARAM_NULL,
                $value instanceof \DateTimeInterface => \PDO::PARAM_STR,
                default          => \PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s.u') : $value, $type);
        }

        $stmt->execute();
        $result = $stmt->fetchAll();
        $this->logQuery($sql, $bindings, (\microtime(true) - $start) * 1000);
        return $result;
    });
}
```

🔒 **`ATTR_EMULATE_PREPARES = false`** تا prepared واقعی در سمت سرور باشد (امنیت).
🔒 Bindingهای `null` با `PARAM_NULL` (نه رشتهٔ `'null'`).
🔒 `DateTimeInterface` به `Y-m-d H:i:s.u` تبدیل می‌شود (UTC).

### ۶٫۱ Binding نامی vs موقعیتی
```php
// موقعیتی
DB::select('SELECT * FROM users WHERE id = ? AND status = ?', [1, 'active']);

// نامی (در whereRaw)
DB::table('users')->whereRaw('created_at > :date', ['date' => $d])->get();
```
🔒 mixing مجاز نیست → `MixedBindingException`.

---

## ۷. Transaction Manager

```php
final class TransactionManager
{
    private int $level = 0;

    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed
    {
        $this->begin($isolation);
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            if ($this->isRetryable($e) && $attempts > 1) {
                return $this->transaction($callback, $attempts - 1, $isolation);   // retry
            }
            throw $e;
        }
    }

    public function begin(?string $isolation = null): void
    {
        if ($this->level === 0) {
            if ($isolation !== null) { $this->connection->setIsolationLevel($isolation); }
            $this->connection->pdo()->beginTransaction();
            $this->events->dispatch(new TransactionBeginning());
        } else {
            $this->connection->pdo()->exec('SAVEPOINT trans' . ($this->level + 1));   // nested
        }
        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level === 1) {
            $this->connection->pdo()->commit();
            $this->events->dispatch(new TransactionCommitted());
        } else {
            $this->connection->pdo()->exec('RELEASE SAVEPOINT trans' . $this->level);
        }
        $this->level--;
    }

    public function rollBack(?int $toLevel = null): void
    {
        $toLevel ??= $this->level - 1;
        if ($toLevel === 0) {
            if ($this->connection->pdo()->inTransaction()) { $this->connection->pdo()->rollBack(); }
            $this->events->dispatch(new TransactionRolledBack());
        } else {
            $this->connection->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . ($toLevel + 1));
        }
        $this->level = $toLevel;
    }

    private function isRetryable(\Throwable $e): bool
    {
        return $e instanceof DeadlockException
            || ($e instanceof QueryException && \in_array($e->sqlState, ['40001', '40P01'], true));
    }
}
```
🔒 SQLite savepoint: `SAVEPOINT trans2` / `RELEASE trans2` / `ROLLBACK TO trans2` (بدون `TO` در RELEASE).
🔒 Deadlock retry با backoff: 50ms, 150ms, 450ms + jitter.

---

## ۸. Query Log و N+1 Detection

```php
final class QueryLog
{
    /** @var list<QueryRecord> */
    private array $queries = [];

    public function record(string $sql, array $bindings, float $timeMs, string $connection): void
    {
        if (!$this->enabled) { return; }

        $record = new QueryRecord($sql, LogMasker::mask($bindings), $timeMs, $connection,
                                  $this->backtrace());
        $this->queries[] = $record;

        if ($timeMs > $this->slowThreshold) {
            $this->logger->warning('database.slow_query', $record->toArray());
        }
        if (\count($this->queries) > $this->maxEntries) {
            \array_shift($this->queries);
        }
        $this->detectNPlusOne($record);
    }

    private function detectNPlusOne(QueryRecord $r): void
    {
        $normalized = $this->normalize($r->sql);     // ? به‌جای مقادیر
        $count = ($this->seen[$normalized] ??= 0) + 1;
        $this->seen[$normalized] = $count;

        if ($count === $this->n1Threshold) {         // پیش‌فرض ۵
            $this->logger->warning('database.n_plus_one', [
                'sql' => $r->sql, 'count' => $count, 'trace' => $r->trace,
                'hint' => 'از eager loading (with()) استفاده کنید.',
            ]);
        }
    }
}
```
🔒 فقط در `dev`/`testing` فعال (سربار).
🔒 `bindings` با `LogMasker` mask می‌شوند.

---

## ۹. Read/Write Splitting

```php
public function readConnection(): \PDO
{
    if ($this->sticky && $this->recordsModified) {
        return $this->writeConnection();     // پس از write، از master بخوان
    }
    return $this->readPdo ??= $this->createPdo($this->pickReadHost());
}

private function pickReadHost(): string
{
    $hosts = $this->config['read']['host'] ?? [];
    if ($hosts === []) { return $this->config['host']; }
    return $hosts[\random_int(0, \count($hosts) - 1)];    // round-robin/random
}
```
🔒 Insert/Update/Delete/Select-forUpdate همیشه از `writeConnection`.
🔒 `sticky` برای جلوگیری از خواندن دادهٔ قدیمی از replica.

---

## ۱۰. امنیت

| تهدید | کنترل |
|---|---|
| SQL Injection | Prepared Statement ۱۰۰٪؛ `EMULATE_PREPARES=false` |
| Injection از `whereRaw`/`orderByRaw` | فقط با binding؛ allowlist برای sort field |
| Identifier Injection | `wrap()` با escape `` ` `` و ممنوعیت ورودی کاربر |
| Mass Data Leak | `limit` پیش‌فرض در `get()` (اختیاری)، Slow query alert |
| Deadlock Storm | retry محدود + backoff |
| نشت credential | فقط از config/ENV؛ mask در لاگ |
| Second-Order Injection | داده از DB هم قبل از `whereRaw` escape شود |

```php
private function assertNoUserInputInRaw(string $sql, string $context): void
{
    // 🔒 اگر raw با متغیر request ساخته شده، باید binding داشته باشد
    if (\preg_match('/[\$](?:_GET|_POST|_REQUEST|_COOKIE)/', $sql) === 1) {
        throw new UnsafeQueryException(messageId: 'database.unsafe_raw', context: ['context' => $context]);
    }
}
```

---

## ۱۱. کارایی

| عملیات | هدف |
|---|---|
| ساخت Connection (اولین) | ≤ ۱۵ms (MySQL local) |
| `table()->where()->get()` با ۱۰۰ رکورد | ≤ ۵ms |
| Compile SQL (builder با ۸ جزء) | ≤ ۳۰µs |
| Prepared statement reuse | ≥ ۲۰٪ سریع‌تر از prepare هر بار |
| Chunk ۱۰۰۰ رکورد (۵۰۰‌تایی) | حافظه ≤ ۵MB |
| Cursor روی ۱۰۰٬۰۰۰ رکورد | حافظه ≤ ۲MB |
| Query Log overhead (فعال) | ≤ ۵٪ |

---

## ۱۲. تست
| ID | سناریو |
|---|---|
| T-01 | اتصال Lazy (PDO تا اولین کوئری ساخته نمی‌شود) |
| T-02 | Reconnect پس از «gone away» |
| T-03 | همهٔ Builder clauseها → SQL درست (MySQL/PG/SQLite) |
| T-04 | Prepared binding با null/bool/int/string/DateTime |
| T-05 | Nested transaction با Savepoint |
| T-06 | Deadlock → retry (با Mock) |
| T-07 | Chunk با توقف در callback |
| T-08 | Cursor/Lazy حافظه ثابت نگه می‌دارد |
| T-09 | Upsert در هر ۳ درایور |
| T-10 | SQL Injection Fuzzing (۲۰۰ payload) → همه fail |
| T-11 | `whereRaw` با binding کاربر → امن |
| T-12 | Read/Write splitting + sticky |
| T-13 | Query Log + Slow detection |
| T-14 | N+1 detection |
| T-15 | Pagination (Page/Cursor/Keyset) |

---

## ۱۳. معیار پذیرش 🧪
- AC-DB-01: همهٔ تست‌های §12 (روی SQLite in-memory + MySQL در CI).
- AC-DB-02: ۰ SQL Injection در Fuzzing.
- AC-DB-03: کارایی §11.
- AC-DB-04: Grammar سه درایور SQL معادل تولید می‌کنند (تست تطبیقی).
- AC-DB-05: `EMULATE_PREPARES=false` در همهٔ اتصالات.

---

## ۱۴. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Laravel Query Builder | Fluent API | Grammar جدا، Prepared اجباری، N+1 detection داخلی |
| Doctrine DBAL | Connection/Grammar | سبک‌تر، بدون Metadata سنگین |
| Medoo | سادگی | Builder قدرتمندتر |
| Atlas/MySQL docs | Grammar | — |

---

## ۱۵. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.database.v1
engine:
  id: tavaan.engine.database
  namespace: Tavaan\Engine\Database
  path: engines/database
  priority: P0
  phase: M3
  depends_on: [config, event, log]
  extensions:
    required: [pdo]
    optional: [{name: pdo_mysql, fallback: null}, {name: pdo_pgsql, fallback: null}, {name: pdo_sqlite, fallback: null}]
  facade: {name: DB, accessor: db}
  tables: []
  events_emitted: [database.query, database.slow_query, database.transaction.begin,
                   database.transaction.commit, database.transaction.rollback, database.reconnected, database.n_plus_one]
  config_file: config/database.php
  public_api:
    - class: QueryBuilder
      methods: [table, select, selectRaw, from, join, leftJoin, rightJoin, crossJoin,
                where, orWhere, whereIn, whereNull, whereBetween, whereExists, whereRaw,
                groupBy, having, orderBy, limit, offset, forUpdate, sharedLock, union,
                withCte, toSql, getBindings, get, cursor, first, find, value, pluck,
                count, sum, avg, min, max, exists, insert, insertGetId, insertOrIgnore,
                upsert, update, increment, decrement, delete, truncate, paginate, chunk, lazy]
    - class: Connection
      methods: [select, selectOne, cursor, insert, update, delete, statement,
                affectingStatement, lastInsertId, transaction, beginTransaction, commit,
                rollBack, transactionLevel, pdo, queryLog]
  loc_estimate: 3200
  acceptance:
    - {id: AC-DB-02, test: 'engines/database/tests/Security/SqlInjectionTest.php'}
    - {id: AC-DB-04, test: 'engines/database/tests/Unit/GrammarParityTest.php'}
```
