# DOC-061 — موتور Queue | صف و Worker

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-061 |
| Engine ID | `tavaan.engine.queue` |
| Namespace | `Tavaan\Engine\Queue` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `database`, `cache`, `event`, `log` |
| Facade | `Queue` → `queue` |
| LOC | ۲۲۰۰ |

## ۱. هدف
صف کار با درایورهای Database/Redis/File/Sync/Null، Worker مقاوم، Retry/Backoff،
Batch/Chain، Delayed Jobs، Rate Limiting، Failed Jobs، DLQ و Dashboard.

## ۲. درایورها
| درایور | مقیاس | تأخیر | کاربرد |
|---|---|---|---|
| `sync` | — | ۰ | تست، dev |
| `database` | متوسط | ~ms | پیش‌فرض بدون Redis |
| `redis` | بالا | ~ms | تولید |
| `file` | کم | ~ms | ساده |
| `null` | — | — | خاموش |

## ۳. API
```php
// Push
Queue::push(new SendInvoiceEmailJob($orderId));
Queue::push(SendEmailJob::class, ['to'=>$email]);
Queue::later(now()->addMinutes(10), new CleanupJob());
Queue::pushOn('high', new UrgentJob());

// Job
final class SendInvoiceEmailJob implements ShouldQueue
{
    use Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 10;
    public string $queue = 'mail';

    public function __construct(private readonly string $orderId) {}
    public function handle(MailerInterface $mailer): void { $mailer->send(...); }
    public function failed(\Throwable $e): void { Log::error('job.failed', [...]); }
    public function retryUntil(): \DateTime { return now()->addHours(1); }
}

// Batch
$batch = Queue::batch([new Job1(), new Job2()])
    ->name('import')
    ->allowFailures()
    ->finally(fn (Batch $b) => ...)
    ->dispatch();

// Chain
Queue::chain([new Step1(), new Step2(), new Step3()])->dispatch();

// Rate Limited
Queue::push((new ApiCallJob())->withRateLimit('api', 100, 60));

// Worker
Queue::worker(['maxJobs'=>1000, 'maxTime'=>3600, 'sleep'=>3]);
```

## ۴. Worker
```php
final class Worker
{
    public function run(WorkerOptions $options): never
    {
        $this->listenForSignals();          // SIGTERM/SIGINT → graceful

        while (true) {
            if ($this->shouldQuit($options)) { $this->stop(0); }

            $job = $this->getNextJob($options);
            if ($job === null) {
                $this->sleep($options->sleep);
                continue;
            }

            $this->processJob($job, $options);

            if ($options->maxMemory && \memory_get_usage(true) > $options->maxMemory) {
                $this->stop(0);              // 🔒 recycle
            }
        }
    }

    private function processJob(Job $job, WorkerOptions $options): void
    {
        try {
            $this->raiseBeforeEvent($job);
            $job->fire();                     // با timeout (pcntl_alarm یا register_tick)
            $job->delete();
            $this->raiseProcessedEvent($job);
        } catch (MaxAttemptsExceededException $e) {
            $this->failJob($job, $e);         // → failed_jobs + DLQ
        } catch (\Throwable $e) {
            $this->handleFailure($job, $e, $options);
        }
    }

    private function handleFailure(Job $job, \Throwable $e, WorkerOptions $o): void
    {
        if ($job->attempts() < $job->maxTries()) {
            $delay = $this->backoff($job->attempts(), $job->backoff());
            $job->release($delay);            // برگشت به صف با تأخیر
        } else {
            $this->failJob($job, $e);
        }
    }

    private function backoff(int $attempt, int|array $backoff): int
    {
        if (\is_array($backoff)) { return $backoff[$attempt - 1] ?? \end($backoff); }
        return $backoff * (2 ** ($attempt - 1));   // نمایی
    }
}
```

### ۴٫۱ Graceful Shutdown
```php
private function listenForSignals(): void
{
    if (!\function_exists('pcntl_signal')) { return; }
    \pcntl_signal(SIGTERM, fn () => $this->shouldQuitFlag = true);
    \pcntl_signal(SIGINT,  fn () => $this->shouldQuitFlag = true);
    // 🔒 Job جاری تمام می‌شود، سپس خروج
}
```

### ۴٫۲ Timeout
```php
// با pcntl_alarm (POSIX)
\pcntl_alarm($job->timeout());
\pcntl_signal(SIGALRM, function () { throw new JobTimedOutException(); });
// بدون pcntl: بررسی زمان در نقاط yield یا register_tick_function
```

## ۵. جداول
```sql
CREATE TABLE tvn_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(64) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_queue_avail (queue, available_at)
) ENGINE=InnoDB;

CREATE TABLE tvn_failed_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(26) NOT NULL UNIQUE,
  connection VARCHAR(64), queue VARCHAR(64),
  payload LONGTEXT, exception LONGTEXT,
  failed_at DATETIME(3) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE tvn_job_batches (
  id CHAR(26) PRIMARY KEY, name VARCHAR(190),
  total_jobs INT, pending_jobs INT, failed_jobs INT,
  failed_job_ids LONGTEXT, options MEDIUMTEXT,
  cancelled_at INT, created_at INT, finished_at INT
) ENGINE=InnoDB;
```

## ۶. Pop اتمیک (Database Driver)
```php
public function pop(string $queue, int $timeout = 3): ?Job
{
    return $this->db->transaction(function () use ($queue): ?Job {
        $row = $this->db->table('tvn_jobs')
            ->where('queue', $queue)
            ->where('available_at', '<=', \time())
            ->whereNull('reserved_at')
            ->orderBy('id')
            ->lockForUpdateSkipLocked()          // 🔒 workerهای موازی
            ->first();

        if ($row === null) { return null; }

        $this->db->table('tvn_jobs')->where('id', $row['id'])
            ->update(['reserved_at' => \time(), 'attempts' => $row['attempts'] + 1]);

        return new DatabaseJob($this, $row);
    });
}
```

## ۷. Serialization امن
```php
public function serializeJob(object $job): string
{
    // 🔒 SerializesModels: فقط کلید مدل‌ها، نه کل آبجکت
    $prepared = $this->prepareModels($job);
    return \json_encode([
        'job' => $job::class,
        'data' => $prepared,
        'tries' => $job->tries ?? null,
        'timeout' => $job->timeout ?? null,
    ], JSON_THROW_ON_ERROR);
}

public function unserializeJob(string $payload): object
{
    $data = \json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    $class = $data['job'];
    // 🔒 Allowlist: فقط کلاس‌های ثبت‌شده
    if (!$this->isAllowedJobClass($class)) {
        throw new UnsafeJobException(messageId: 'queue.unsafe_job');
    }
    return $this->container->make($class, $data['data']);
}
```
🔒 **هرگز** `unserialize()` با `allowed_classes: true` باز.

## ۸. پیکربندی
```php
// config/queue.php
return [
    'default' => env_string('TVN_QUEUE_CONNECTION', 'database'),
    'connections' => [
        'sync'     => ['driver'=>'sync'],
        'database' => ['driver'=>'database','table'=>'tvn_jobs','connection'=>null,'queue'=>'default','retry_after'=>90],
        'redis'    => ['driver'=>'redis','connection'=>'default','queue'=>'default','retry_after'=>90,'block_for'=>null],
        'file'     => ['driver'=>'file','path'=>storage_path('queue')],
        'null'     => ['driver'=>'null'],
    ],
    'failed' => ['driver'=>'database','table'=>'tvn_failed_jobs'],
    'batching' => ['table'=>'tvn_job_batches'],
    'worker' => ['sleep'=>3,'max_tries'=>3,'max_time'=>0,'max_jobs'=>0,'memory'=>128],
];
```

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| Unsafe Deserialization | JSON + Allowlist کلاس |
| Payload حساس | mask در log؛ payload رمزنگاری (اختیاری) |
| Queue Injection | فقط از کد، نه ورودی کاربر |
| Worker Hijack | مجوز فایل queue |
| DoS با job زیاد | max_inflight، rate limit |

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| push | ≤ ۱ms (db) |
| pop (با lock) | ≤ ۱ms |
| throughput | ≥ ۵۰۰۰ job/min (db driver) |
| memory در worker | ≤ ۱۲۸MB (recycle) |

## ۱۱. دستورات CLI
`queue:work`, `queue:listen`, `queue:restart`, `queue:retry {id|--all}`,
`queue:flush`, `queue:failed`, `queue:prune-failed`, `queue:monitor`.

## ۱۲. تست
| T-01 | push/pop در هر ۵ درایور |
| T-02 | retry با backoff نمایی |
| T-03 | max tries → failed_jobs |
| T-04 | delayed job (later) |
| T-05 | Batch: همه/all/finally |
| T-06 | Chain: ترتیب + شکست |
| T-07 | Graceful shutdown با signal |
| T-08 | Timeout → JobTimedOutException |
| T-09 | Pop اتمیک با ۲ worker (SKIP LOCKED) |
| T-10 | Serialization: مدل فقط کلید |
| T-11 | Allowlist job class |
| T-12 | Memory recycle |

## ۱۳. معیار پذیرش 🧪
- AC-QUEUE-01..12. AC-QUEUE-13: ۲ worker موازی هیچ job را دوبار پردازش نمی‌کنند.

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.queue.v1
engine:
  id: tavaan.engine.queue
  namespace: Tavaan\Engine\Queue
  path: engines/queue
  priority: P1
  phase: M5
  depends_on: [database, cache, event, log]
  facade: {name: Queue, accessor: queue}
  tables: [tvn_jobs, tvn_failed_jobs, tvn_job_batches]
  drivers: [sync, database, redis, file, null]
  config_file: config/queue.php
  public_api:
    - class: QueueManager
      methods: [push, later, pushOn, batch, chain, size, connection]
    - class: Worker
      methods: [run, stop, daemon]
  loc_estimate: 2200
```
