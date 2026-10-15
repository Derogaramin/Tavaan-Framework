# DOC-112 — اهداف بنچمارک و کارایی (Benchmark Targets) 🧪

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-112 |
| مرتبط | DOC-001 (KPI)، DOC-110 (تست) |
| ابزار | `tvn bench:all` + موتور Benchmark داخلی |

---

## ۱. محیط مرجع (Reference Environment)

🔒 همهٔ اعداد نسبت به این محیط سنجیده می‌شوند:

| قلم | مشخصات |
|---|---|
| CPU | 4 vCPU @ 3.0 GHz (x86-64) |
| RAM | 8 GB |
| PHP | 8.2.x, opcache enabled, JIT off |
| DB | MySQL 8.0 local (same host) |
| Cache | Redis 7 local |
| OS | Ubuntu 22.04 |
| Web | PHP built-in / Swoole 5 |

---

## ۲. اهداف بوت و درخواست (Kernel)

| ID | سنجه | هدف | روش |
|---|---|---|---|
| PERF-BOOT-01 | زمان بوت (warm opcache) | ≤ ۸ ms | `microtime` در `bin/tvn about` |
| PERF-BOOT-02 | زمان بوت (cold) | ≤ ۲۵ ms | بدون opcache |
| PERF-BOOT-03 | سربار Hello World نسبت به PHP خام | ≤ ۳ ms | k6 |
| PERF-BOOT-04 | حافظهٔ پایهٔ درخواست | ≤ ۴ MB | `memory_get_peak_usage` |
| PERF-BOOT-05 | RPS (Swoole, 1 vCPU, hello) | ≥ ۳۰۰۰ | wrk |
| PERF-BOOT-06 | p99 latency (hello, cached) | ≤ ۱۰ ms | k6 |

---

## ۳. اهداف موتورها (per-Engine)

### ۳٫۱ Container
| سنجه | هدف |
|---|---|
| `make()` singleton (cached) | ≤ ۱ µs |
| `make()` با ۵ وابستگی (reflection cached) | ≤ ۲۰ µs |
| `make()` اولین بار (full reflection) | ≤ ۳۰۰ µs |
| ۱۰۰۰ make متوالی | ≤ ۱ ms |

### ۳٫۲ Router
| سنجه | هدف |
|---|---|
| Route match با ۱۰۰۰ روت (static) | ≤ ۵ µs |
| Route match با ۱۰۰۰ روت (parametric) | ≤ ۱۵ µs |
| ساخت Trie از ۱۰۰۰ روت | ≤ ۱۵ ms |
| بارگذاری route cache | ≤ ۱٫۵ ms |
| UrlGenerator (cached name) | ≤ ۳ µs |

### ۳٫۳ Database / ORM
| سنجه | هدف |
|---|---|
| Compile SQL (builder ۸ جزء) | ≤ ۳۰ µs |
| Select ۱۰۰۰ رکورد + hydrate | ≤ ۲۵ ms |
| Eager load رفع N+1 (۱۰۰۰ رکورد، ۲ رابطه) | ≤ ۵۰ ms |
| Insert تک‌رکوردی | ≤ ۳ ms |
| Transaction با ۱۰ عملیات | ≤ ۱۵ ms |
| Cursor روی ۱۰۰٬۰۰۰ رکورد | حافظه ≤ ۲ MB |

### ۳٫۴ View
| سنجه | هدف |
|---|---|
| کامپایل template ۱۰۰ خطی | ≤ ۳ ms |
| رندر template کامپایل‌شده | ≤ ۰٫۵ ms |
| `Escaper::html` برای ۱ KB | ≤ ۵ µs |

### ۳٫۵ Cache
| سنجه | هدف |
|---|---|
| `get` از array | ≤ ۱ µs |
| `get` از redis (local) | ≤ ۰٫۵ ms |
| `get` از file | ≤ ۰٫۳ ms |
| Lock acquire/release | ≤ ۰٫۳ ms |

### ۳٫۶ Event
| سنجه | هدف |
|---|---|
| dispatch بدون listener | ≤ ۱ µs |
| dispatch با ۵ listener همزمان | ≤ ۵۰ µs |
| ۱۰٬۰۰۰ dispatch | ≤ ۵۰ ms |
| Outbox write (در تراکنش) | ≤ ۰٫۳ ms |
| Relay batch 100 | ≤ ۳۰۰ ms |

### ۳٫۷ Validation
| سنجه | هدف |
|---|---|
| Validate یک DTO با ۱۰ فیلد | ≤ ۰٫۵ ms |
| `unique` (با ایندکس) | ≤ ۳ ms |

### ۳٫۸ Crypt / Hash
| سنجه | هدف |
|---|---|
| encrypt/decrypt ۱ KB (AES-GCM) | ≤ ۲۰ µs |
| HMAC SHA-256 | ≤ ۵ µs |
| Argon2id hash | ≤ ۱۰۰ ms (طبیعی — memory-hard) |

### ۳٫۹ Queue
| سنجه | هدف |
|---|---|
| push | ≤ ۱ ms |
| pop (با lock) | ≤ ۱ ms |
| throughput (db driver) | ≥ ۵۰۰۰ job/min |

---

## ۴. اهداف سطح اپلیکیشن (Application-Level)

| ID | سنجه | هدف | سناریو |
|---|---|---|---|
| APP-01 | p95 latency — CRUD ساده (cached) | ≤ ۵۰ ms | فهرست محصول |
| APP-02 | p99 latency — ثبت سفارش | ≤ ۳۰۰ ms | checkout |
| APP-03 | Throughput — فروشگاه (۱۰۰ کاربر همزمان) | ≥ ۵۰۰ RPS | load test |
| APP-04 | جست‌وجوی فارسی (۱۰۰٬۰۰۰ محصول) | ≤ ۲۰ ms | search |
| APP-05 | گزارش ۱۰٬۰۰۰ ردیف | ≤ ۲ s | report |
| APP-06 | بارگذاری صفحهٔ داشبورد (۸ widget) | ≤ ۲۰۰ ms | HMVC |
| APP-07 | حافظه در worker پس از ۱M درخواست | رشد ≤ ۵٪ | leak test |

---

## ۵. روش اندازه‌گیری

### ۵٫۱ موتور Benchmark داخلی
```php
final class Benchmark
{
    public function measure(callable $fn, int $iterations = 1000, int $warmup = 100): BenchResult
    {
        for ($i = 0; $i < $warmup; $i++) { $fn(); }     // گرم‌کردن opcache

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = \hrtime(true);
            $fn();
            $times[] = (\hrtime(true) - $start) / 1e6;   // ms
        }
        \sort($times);
        return new BenchResult(
            min: $times[0],
            avg: \array_sum($times) / \count($times),
            p50: $this->percentile($times, 50),
            p95: $this->percentile($times, 95),
            p99: $this->percentile($times, 99),
            max: \end($times),
            memoryKb: \memory_get_peak_usage(true) / 1024,
        );
    }

    public function assertDuration(callable $fn, float $maxMicroseconds, int $iterations = 1000): void
    {
        $result = $this->measure($fn, $iterations);
        if ($result->p95 * 1000 > $maxMicroseconds) {
            self::fail(\sprintf('p95=%.1fµs > target=%.1fµs', $result->p95 * 1000, $maxMicroseconds));
        }
    }
}
```

### ۵٫۲ Load Test (k6)
```javascript
// bench/k6/hello.js
import http from 'k6/http';
import { check } from 'k6';
export const options = { vus: 100, duration: '30s' };
export default function () {
  const res = http.get('http://localhost:8080/hello');
  check(res, { 'status 200': (r) => r.status === 200 });
}
```

---

## ۶. Regression Detection 🔒

```bash
tvn bench:all --fail-on-regression
```
```php
// مقایسه با baseline ذخیره‌شده
final class RegressionGuard
{
    public function check(BenchResult $current, BenchResult $baseline, float $threshold = 0.05): bool
    {
        $regression = ($current->p95 - $baseline->p95) / $baseline->p95;
        if ($regression > $threshold) {
            throw new PerformanceRegressionException(
                messageId: 'bench.regression',
                context: ['metric' => $current->name, 'delta' => round($regression * 100, 2) . '%'],
            );
        }
        return true;
    }
}
```
🔒 baseline در `benchmarks/baseline.json` ذخیره و در CI مقایسه می‌شود.
🔒 regression > ۵٪ → CI fail (مگر با توجیه در PR).

---

## ۷. پروفایل (Profiling)

| ابزار | کاربرد |
|---|---|
| `Debug Bar` (DOC-067) | زمان هر فاز در dev |
| `tvn profile <url>` | trace یک درخواست |
| XHProf/Xdebug (اختیاری) | profiling عمیق |
| `observability` metrics | p95/p99 در production |

---

## ۸. چک‌لیست کارایی برای Review 🧪

- [ ] هیچ کوئری در حلقه نیست (N+1)؟
- [ ] Eager loading برای رابطه‌ها؟
- [ ] Cache برای دادهٔ پرتکرار؟
- [ ] Lazy loading برای سرویس‌های سنگین؟
- [ ] Pagination برای فهرست‌ها؟
- [ ] Streaming برای دادهٔ بزرگ (export)؟
- [ ] بدون محاسبهٔ تکراری در حلقه؟
- [ ] Index برای ستون‌های پرکوئری؟
- [ ] Benchmark برای مسیرهای داغ؟

---

## ۹. معیار پذیرش 🧪
- AC-PERF-01: همهٔ اهداف §2-4 در محیط مرجع پاس می‌شوند.
- AC-PERF-02: `bench:all --fail-on-regression` بدون regression.
- AC-PERF-03: baseline.json به‌روز و در CI مقایسه می‌شود.
- AC-PERF-04: هیچ memory leak در worker (رشد ≤ ۵٪ پس از ۱M درخواست).
