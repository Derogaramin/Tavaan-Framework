# DOC-060 — موتور Cache | حافظهٔ نهان

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-060 |
| Engine ID | `tavaan.engine.cache` |
| Namespace | `Tavaan\Engine\Cache` |
| اولویت | **P0** — فاز M4 |
| وابستگی | `config`, `event`؛ نرم: `database` |
| Facade | `Cache` → `cache` |
| افزونه | `redis`/`apcu`/`memcached` (اختیاری + fallback به file) |
| LOC | ۱۴۰۰ |

---

## ۱. هدف
حافظهٔ نهان یکپارچه با **۶ درایور**، Tag، Lock اتمیک، Stampede Protection و `remember`.

## ۲. درایورها
| درایور | سرعت | اشتراکی | Fallback | کاربرد |
|---|---|---|---|---|
| `array` | fastest | ❌ | — | یک درخواست، تست |
| `file` | کند | ✅ | — | پیش‌فرض بدون Redis |
| `redis` | سریع | ✅ | → file | تولید |
| `memcached` | سریع | ✅ | → file | تولید |
| `apcu` | سریع | ❌ (per-node) | → file | opcache-like |
| `database` | متوسط | ✅ | → file | بدون Redis |

## ۳. API
```php
Cache::get('key', $default);
Cache::put('key', $value, ttl: 3600);         // ttl ثانیه یا DateInterval
Cache::has('key'); Cache::missing('key');
Cache::forget('key'); Cache::flush();
Cache::forever('key', $value);
Cache::remember('key', ttl: 3600, fn () => $expensive());
Cache::rememberForever('key', fn () => /*…*/);
Cache::add('key', $value, ttl: 60);           // فقط اگر نبود
Cache::increment('counter'); Cache::decrement('counter');
Cache::pull('key');                            // get + forget

// Tag
Cache::tags(['shop','product'])->remember('p:1', 3600, fn () => /*…*/);
Cache::tags(['shop'])->flush();                // فقط تگ‌های shop

// Store خاص
Cache::store('redis')->get('key');
Cache::lock('order:12', seconds: 10)->get(fn () => /*…*/);   // atomic lock

// Multiple
Cache::many(['k1','k2']);
Cache::putMany(['k1'=>'v1','k2'=>'v2'], 60);
```

## ۴. Lock (جلوگیری از Race)
```php
$lock = Cache::lock('process:invoice:'.$id, seconds: 30);
if ($lock->acquire()) {
    try { /* critical section */ }
    finally { $lock->release(); }
}

// با block و timeout
Cache::lock('x', 10)->block(5, function () { /* … */ });

// Owner-based (برای آزادسازی فقط توسط مالک)
$lock = Cache::lock('x', 10, owner: 'worker-1');
$lock->release();     // فقط اگر owner مطابقت کند
```
🔒 Redis: `SET key val NX PX ttl` + Lua برای release اتمیک.
🔒 File: `flock` با `LOCK_EX | LOCK_NB`.
🔒 Database: `INSERT … ON DUPLICATE` یا `SELECT FOR UPDATE`.

## ۵. Stampede Protection (Cache Dogpile)
```php
public function remember(string $key, int $ttl, callable $callback): mixed
{
    $value = $this->get($key);
    if ($value !== null) { return $value; }

    if (!$this->config->get('cache.stampede_protection.enabled', true)) {
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    // فقط یک فرایند بازسازی می‌کند؛ بقیه یا منتظر می‌مانند یا stale می‌گیرند
    $lock = $this->lock($this->lockKey($key), $this->config->get('cache.stampede_protection.lock_ttl', 10));
    if ($lock->acquire()) {
        try {
            // double-check
            if (($value = $this->get($key)) !== null) { return $value; }
            $value = $callback();
            $this->put($key, $value, $ttl);
            return $value;
        } finally {
            $lock->release();
        }
    }

    // فرایند دوم: serve stale اگر موجود، وگرنه کمی صبر و دوباره تلاش
    if ($this->config->get('cache.stampede_protection.serve_stale', true)) {
        $stale = $this->get($this->staleKey($key));
        if ($stale !== null) { return $stale; }
    }
    \usleep(50_000);
    return $this->remember($key, $ttl, $callback);   // retry محدود
}
```

## ۶. Tagging (الگوریتم)
```
Tag با نگاشت tag → set of keys نگهداری می‌شود.
- put('p:1', v, tags:['shop','product'])
    → store key 'p:1'
    → SADD tag:shop:keys 'p:1'  ; SADD tag:product:keys 'p:1'
- flush('shop')
    → همهٔ کلیدهای tag:shop:keys را forget
    → DEL tag:shop:keys
🔒 درایور file/database: نگاشت در فایل/index جدا.
🔒 درایور array/apcu: tag محدود به همان فرایند.
```

## ۷. پیکربندی
→ `config/cache.php` (در DOC-032 §6 و DOC-080 آمده). نکات:
```php
'stores' => [
    'redis' => ['driver'=>'redis','connection'=>'cache','prefix'=>'tvn:','serialize'=>'igbinary'],
],
'tags' => ['enabled'=>true],
'lock' => ['driver'=>'redis','timeout'=>5,'retry'=>100],
'stampede_protection' => ['enabled'=>true,'lock_ttl'=>10,'serve_stale'=>true],
```

## ۸. امنیت
| تهدید | کنترل |
|---|---|
| Cache Poisoning | کلید از ورودی کاربر → hash/allowlist |
| Serialized Object Injection | 🔒 `serialize` فقط با allowlist یا JSON |
| نشت داده بین tenant | prefix با `tenant_id` (DOC-028) |
| DoS با کلید بزرگ | `max_key_length`, `max_value_size` |
| Race | Lock اتمیک |

🔒 **هرگز** `unserialize` روی دادهٔ cache با `allowed_classes` باز. ترجیح: JSON/igbinary.

## ۹. کارایی
| عملیات | هدف |
|---|---|
| `get` از array | ≤ ۱µs |
| `get` از redis (local) | ≤ ۰٫۵ms |
| `get` از file | ≤ ۰٫۳ms |
| `remember` با stampede lock | ≤ ۱ms |
| `increment` اتمیک (redis) | ≤ ۰٫۳ms |

## ۱۰. تست
| ID | سناریو |
|---|---|
| T-01 | همهٔ ۶ درایور: get/put/has/forget/flush |
| T-02 | TTL انقضا (با Clock Fake) |
| T-03 | Tag flush فقط تگ مربوطه |
| T-04 | Lock: فقط یک فرایند acquire |
| T-05 | Stampede: ۱۰۰ همزمان → ۱ بازسازی |
| T-06 | `remember` cache hit/miss |
| T-07 | `add` فقط اگر نبود |
| T-08 | Serialized object injection → رد |
| T-09 | Redis نبود → fallback به file بدون crash |

## ۱۱. معیار پذیرش 🧪
- AC-CACHE-01..09 (§10).
- AC-CACHE-10: Lock در Redis واقعاً اتمیک (تست با ۲ فرایند).
- AC-CACHE-11: بدون Redis، همه‌چیز با file کار کند.

## ۱۲. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Laravel Cache | store/tag/lock/remember | Stampede داخلی، fallback خودکار |
| PSR-6/16 | مفاهیم | Interface داخلی |
| Symfony Cache | Tag | — |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.cache.v1
engine:
  id: tavaan.engine.cache
  namespace: Tavaan\Engine\Cache
  path: engines/cache
  priority: P0
  phase: M4
  depends_on: [config, event]
  soft_depends_on: [database]
  extensions:
    required: []
    optional: [{name: redis, fallback: file}, {name: apcu, fallback: file}, {name: memcached, fallback: file}]
  facade: {name: Cache, accessor: cache}
  tables: [cache, cache_locks]
  drivers: [array, file, redis, memcached, apcu, database]
  config_file: config/cache.php
  public_api:
    - class: CacheManager
      methods: [store, driver, extend, tags, lock, getDefaultDriver]
    - class: CacheRepository
      methods: [get, put, has, missing, forget, flush, forever, remember, rememberForever,
                add, increment, decrement, pull, many, putMany, lock]
  loc_estimate: 1400
  acceptance:
    - {id: AC-CACHE-05, test: 'engines/cache/tests/Feature/StampedeTest.php'}
```
