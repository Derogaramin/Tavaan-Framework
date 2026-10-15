# DOC-062 — موتور Schedule | زمان‌بندی

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-062 |
| Engine ID | `tavaan.engine.schedule` |
| Namespace | `Tavaan\Engine\Schedule` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `console`, `cache`, `queue` |
| Facade | `Schedule` |
| LOC | ۷۰۰ |

## ۱. هدف
زمان‌بندی کارها با **Cron Expression Parser خودنوشته**، Overlap Prevention، RunInBackground
و بدون نیاز به crontab پیچیده (فقط یک entry در cron).

## ۲. API
```php
// routes/console.php یا ScheduleServiceProvider
Schedule::command('orders:cleanup')->daily()->at('03:00');
Schedule::job(new SendDigestJob())->hourly();
Schedule::call(fn () => /*…*/)->everyFiveMinutes();
Schedule::command('reports:generate')->weeklyOn(5, '17:00');   // جمعه ۱۷:۰۰
Schedule::command('cache:warm')->cron('*/10 * * * *');
Schedule::command('backup:run')->daily()->withoutOverlapping();
Schedule::command('x')->everyMinute()->onOneServer();          // فقط یک سرور
Schedule::command('y')->timezone('Asia/Tehran')->dailyAt('08:00');
```

### ۲٫۱ Fluent Schedule
`->everyMinute/everyFiveMinutes/hourly/hourlyAt/daily/dailyAt/weekly/monthly/quarterly/yearly`,
`->cron(expr)`, `->at('HH:MM')`, `->timezone(tz)`, `->between(start,end)`, `->when(fn)`,
`->withoutOverlapping()`, `->onOneServer()`, `->runInBackground()`, `->evenInMaintenanceMode()`,
`->onSuccess(fn)`, `->onFailure(fn)`, `->output(path)`.

## ۳. Cron Expression Parser (خودنوشته)
```php
final class CronExpression
{
    /** پنج فیلد: minute hour dayOfMonth month dayOfWeek */
    public function __construct(private readonly string $expression)
    {
        $this->parts = \preg_split('/\s+/', \trim($expression)) ?: [];
        if (\count($this->parts) !== 5) {
            throw new InvalidCronException(messageId: 'schedule.invalid_cron', context: ['expr' => $expression]);
        }
    }

    /** آیا در زمان $time باید اجرا شود؟ */
    public function isDue(\DateTimeImmutable $time): bool
    {
        return $this->matches($this->parts[0], (int) $time->format('i'))
            && $this->matches($this->parts[1], (int) $time->format('G'))
            && $this->matches($this->parts[2], (int) $time->format('j'))
            && $this->matches($this->parts[3], (int) $time->format('n'))
            && $this->matches($this->parts[4], (int) $time->format('w'));
    }

    private function matches(string $field, int $value): bool
    {
        if ($field === '*') { return true; }
        foreach (\explode(',', $field) as $part) {
            // step: */5 یا 1-30/5
            if (\str_contains($part, '/')) {
                [$range, $step] = \explode('/', $part);
                $step = (int) $step;
                $bounds = $this->parseRange($range, $value);
                if ($bounds !== null && ($value - $bounds[0]) % $step === 0 && $value >= $bounds[0] && $value <= $bounds[1]) {
                    return true;
                }
                continue;
            }
            // range: 1-5
            if (\str_contains($part, '-')) {
                [$min, $max] = \explode('-', $part);
                if ($value >= (int) $min && $value <= (int) $max) { return true; }
                continue;
            }
            // single
            if ((int) $part === $value) { return true; }
        }
        return false;
    }

    /** زمان‌های بعدی اجرا (برای preview) */
    public function nextRunDates(int $count, \DateTimeImmutable $from): array { /* … */ }
}
```

## ۴. اجرای زمان‌بندی (`tvn schedule:run`)
```php
// cron: * * * * * php /path/bin/tvn schedule:run >> /dev/null 2>&1
public function run(): void
{
    $now = Clock::now($this->timezone);
    foreach ($this->events() as $event) {
        if (!$event->isDue($now)) { continue; }
        if (!$event->filtersPass()) { continue; }

        if ($event->onOneServer && !$this->acquireServerLock($event, $now)) { continue; }
        if ($event->withoutOverlapping && !$this->acquireMutex($event)) { continue; }

        $this->dispatch($event);     // inline یا background یا queue
    }
}
```

### ۴٫۱ Overlap Prevention (Mutex)
```php
private function acquireMutex(Event $event): bool
{
    $key = 'schedule:mutex:' . \sha1($event->expression . $event->command);
    return $this->cache->add($key, true, ttl: $event->expiresAt ?? 1440);   // 🔒 atomic add
}
```

### ۴٫۲ OnOneServer (چند سرور)
```php
private function acquireServerLock(Event $event, \DateTimeImmutable $now): bool
{
    $key = 'schedule:lock:' . \sha1($event->command) . ':' . $now->format('YmdHi');
    return $this->cache->add($key, \gethostname(), ttl: 3600);   // فقط اولین سرور
}
```

## ۵. پیکربندی
```php
// config/schedule.php
return [
    'timezone' => env_string('TVN_SCHEDULE_TZ', 'Asia/Tehran'),
    'cache_store' => env_string('TVN_SCHEDULE_CACHE', null),   // برای mutex
    'default_queue' => 'schedule',
    'run_in_maintenance' => false,
];
```

## ۶. امنیت
| تهدید | کنترل |
|---|---|
| اجرای همزمان (overlap) | Mutex |
| اجرای چندباره در چند سرور | onOneServer lock |
| Command injection در closure | فقط از کد ثبت‌شده |

## ۷. تست
| T-01 | Cron parser: `*/5 * * * *`, `0 3 * * *`, `0 0 1 * *` |
| T-02 | `isDue` در مرزها |
| T-03 | Fluent: `daily()->at('03:00')` |
| T-04 | Mutex: دو اجرای همزمان → یکی |
| T-05 | onOneServer: دو سرور → یکی |
| T-06 | timezone |
| T-07 | nextRunDates |

## ۸. معیار پذیرش 🧪
- AC-SCH-01..07. AC-SCH-08: Cron parser با `cron-expression` مرجع مطابقت دارد (۱۰۰۰ نمونه).

## ۹. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Scheduler | fluent، mutex | خودنوشته cron parser، بدون `dragonmantank/cron-expression` |
| cronitor | — | — |

## ۱۰. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.schedule.v1
engine:
  id: tavaan.engine.schedule
  namespace: Tavaan\Engine\Schedule
  path: engines/schedule
  priority: P1
  phase: M5
  depends_on: [console, cache, queue]
  config_file: config/schedule.php
  public_api:
    - class: ScheduleManager
      methods: [command, job, call, exec, events]
    - class: CronExpression
      methods: [isDue, nextRunDates]
  loc_estimate: 700
```
