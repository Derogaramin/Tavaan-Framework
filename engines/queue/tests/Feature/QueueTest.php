<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Feature;

require_once __DIR__ . '/../Fixtures/BatchCountJob.php';
require_once __DIR__ . '/../Fixtures/BatchFinally.php';
require_once __DIR__ . '/../Fixtures/ChainStepJob.php';
require_once __DIR__ . '/../Fixtures/FailingJob.php';
require_once __DIR__ . '/../Fixtures/FakeRedisClient.php';
require_once __DIR__ . '/../Fixtures/ModelRefJob.php';
require_once __DIR__ . '/../Fixtures/QueueUser.php';
require_once __DIR__ . '/../Fixtures/QueuedSampleEvent.php';
require_once __DIR__ . '/../Fixtures/RecordJob.php';
require_once __DIR__ . '/../Fixtures/TimeoutJob.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Queue\Events\JobProcessed;
use Tavaan\Engine\Queue\Exceptions\UnsafeJobException;
use Tavaan\Engine\Queue\Jobs\CallQueuedHandler;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Engine\Queue\Queues\RedisQueue;
use Tavaan\Engine\Queue\Tests\Fixtures\BatchCountJob;
use Tavaan\Engine\Queue\Tests\Fixtures\BatchFinally;
use Tavaan\Engine\Queue\Tests\Fixtures\ChainStepJob;
use Tavaan\Engine\Queue\Tests\Fixtures\FailingJob;
use Tavaan\Engine\Queue\Tests\Fixtures\FakeRedisClient;
use Tavaan\Engine\Queue\Tests\Fixtures\ModelRefJob;
use Tavaan\Engine\Queue\Tests\Fixtures\QueueUser;
use Tavaan\Engine\Queue\Tests\Fixtures\QueuedSampleEvent;
use Tavaan\Engine\Queue\Tests\Fixtures\RecordJob;
use Tavaan\Engine\Queue\Tests\Fixtures\TimeoutJob;
use Tavaan\Engine\Queue\Worker;
use Tavaan\Engine\Queue\WorkerOptions;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Queue — DOC-061 §12 (T-01..T-12) + §13 (AC-QUEUE).
 */
final class QueueTest extends TestCase
{
    private Application $app;

    private string $tmp;

    protected function setUp(): void
    {
        RecordJob::reset();
        FailingJob::reset();
        ChainStepJob::reset();
        BatchCountJob::reset();
        BatchFinally::reset();
        ModelRefJob::reset();
        TimeoutJob::reset();
        Clock::setTestNow(null);

        $this->tmp = \sys_get_temp_dir() . '/tvn-queue-test-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp, 0775, true);

        $this->app = \tavaan_test_app();

        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('queue.default', 'database');
        $config->set('queue.connections.database', ['driver' => 'database', 'table' => 'tvn_jobs', 'queue' => 'default', 'retry_after' => 90]);
        $config->set('queue.connections.file', ['driver' => 'file', 'path' => $this->tmp . '/queue', 'queue' => 'default']);
        $config->set('queue.connections.sync', ['driver' => 'sync']);
        $config->set('queue.connections.null', ['driver' => 'null']);
        $config->set('queue.failed', ['driver' => 'database', 'table' => 'tvn_failed_jobs', 'dlq' => '']);
        $config->set('queue.batching', ['driver' => 'database', 'table' => 'tvn_job_batches']);

        Model::flushTestState();

        // جداول صف از مسیر migration واقعی موتور
        $migration = require \dirname(__DIR__, 2) . '/migrations/2026_05_01_000001_create_queue_tables.php';
        $migration->up();

        // جدول مدل fixture (تست SerializesModels)
        $this->app->make('schema')->create('queue_users', function ($t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps(3);
        });
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
        $this->rrmdir($this->tmp);
    }

    private function queue(): QueueManager
    {
        return $this->app->make(QueueManager::class);
    }

    private function worker(): Worker
    {
        return $this->app->make(Worker::class);
    }

    /** @param array<string, mixed> $options */
    private function options(array $options = []): WorkerOptions
    {
        return WorkerOptions::fromArray(\array_replace(['sleep' => 0], $options));
    }

    // ─── T-01: push/pop در درایورها ──────────────────────────────────────

    public function testDatabaseDriverPushPopFire(): void
    {
        $queue = $this->queue();
        $id = $queue->push(new RecordJob('db-1'));

        $this->assertNotNull($id);
        $this->assertSame(1, $queue->size('default'));

        $worker = $this->worker();
        $this->assertTrue($worker->runNextJob($this->options()));
        $this->assertSame(['db-1'], RecordJob::$trace);
        $this->assertSame(0, $queue->size('default'));
        $this->assertSame(1, $worker->processedJobs());
    }

    public function testPushClassStringWithConstructorData(): void
    {
        $this->queue()->push(RecordJob::class, ['tag' => 'from-data']);
        $this->worker()->runNextJob($this->options());

        $this->assertSame(['from-data'], RecordJob::$trace);
    }

    public function testPushOnSpecificQueueWithPriority(): void
    {
        $queue = $this->queue();
        $queue->pushOn('high', new RecordJob('h'));
        $queue->pushOn('low', new RecordJob('l'));

        $this->assertNull($queue->pop('default'));

        $worker = $this->worker();
        $options = $this->options(['queue' => 'high,default,low']);

        $worker->runNextJob($options);
        $this->assertSame(['h'], RecordJob::$trace);   // اولویت با high

        $worker->runNextJob($options);
        $this->assertSame(['h', 'l'], RecordJob::$trace);
    }

    public function testFileDriverPushPopAndRetry(): void
    {
        $connection = $this->queue()->connection('file');
        $connection->push(new FailingJob('f1'));

        $this->assertSame(1, $connection->size('default'));

        $worker = $this->worker();
        $options = $this->options(['connectionName' => 'file']);

        // تلاش ۱ → release با backoff[0]=1s
        $worker->runNextJob($options);
        $this->assertCount(1, FailingJob::$attempts);
        $this->assertSame(1, $connection->size('default'));   // برگشت به pending

        // تلاش ۲ → release با backoff[1]=2s
        Clock::setTestNow('+3 seconds');
        $worker->runNextJob($options);
        $this->assertCount(2, FailingJob::$attempts);

        // تلاش ۳ = maxTries → شکست نهایی
        Clock::setTestNow('+8 seconds');
        $worker->runNextJob($options);
        $this->assertCount(3, FailingJob::$attempts);
        $this->assertSame(0, $connection->size('default'));
        $this->assertSame(1, $this->queue()->failedProvider()->count());
        $this->assertCount(1, FailingJob::$failedCalls);      // hook کاربر
    }

    public function testSyncDriverRunsInline(): void
    {
        $connection = $this->queue()->connection('sync');
        $connection->push(new RecordJob('sync-1'));

        $this->assertSame(['sync-1'], RecordJob::$trace);     // بلافاصله اجرا شد
        $this->assertSame(0, $connection->size());
    }

    public function testSyncDriverFailureIsRecordedAndRethrown(): void
    {
        $connection = $this->queue()->connection('sync');

        try {
            $connection->push(new FailingJob('sync-fail'));
            $this->fail('sync باید استثنا را rethrow کند');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sync-fail', $e->getMessage());
        }

        $this->assertCount(1, FailingJob::$failedCalls);
        $this->assertSame(1, $this->queue()->failedProvider()->count());
    }

    public function testNullDriverDiscardsEverything(): void
    {
        $connection = $this->queue()->connection('null');

        $this->assertNull($connection->push(new RecordJob('x')));
        $this->assertSame(0, $connection->size());
        $this->assertNull($connection->pop());
        $this->assertSame([], RecordJob::$trace);
    }

    public function testRedisDriverWithFakeClient(): void
    {
        $redis = new RedisQueue(
            'redis-test',
            ['queue' => 'default', 'retry_after' => 90, 'prefix' => 'tvn:'],
            $this->queue()->serializer(),
            $this->app,
            new FakeRedisClient(),
        );

        $redis->push(new RecordJob('redis-1'));
        $this->assertSame(1, $redis->size('default'));

        $job = $redis->pop('default');
        $this->assertNotNull($job);
        $this->assertSame(1, $job->attempts());

        $job->fire();
        $this->assertSame(['redis-1'], RecordJob::$trace);

        $job->delete();
        $this->assertSame(0, $redis->size('default'));

        // release → delayed → پس از سپری‌شدن تأخیر دوباره قابل pop (با attempts=2)
        $redis->push(new RecordJob('redis-2'));
        $job = $redis->pop('default');
        $this->assertNotNull($job);
        $job->release(30);
        $this->assertSame(1, $redis->size('default'));   // delayed شمرده می‌شود
        $this->assertNull($redis->pop('default'));       // هنوز سررسید نشده

        Clock::setTestNow('+31 seconds');
        $job = $redis->pop('default');
        $this->assertNotNull($job);
        $this->assertSame(2, $job->attempts());
        $job->delete();

        // later()
        Clock::setTestNow(null);
        $redis->later(60, new RecordJob('redis-later'));
        $this->assertNull($redis->pop('default'));
        Clock::setTestNow('+61 seconds');
        $job = $redis->pop('default');
        $this->assertNotNull($job);
        $job->fire();
        $this->assertContains('redis-later', RecordJob::$trace);
    }

    // ─── T-02/T-03: retry با backoff و شکست نهایی ────────────────────────

    public function testRetryWithListBackoffThenFailedJobs(): void
    {
        $queue = $this->queue();
        $queue->push(new FailingJob('retry-me'));
        $worker = $this->worker();
        $options = $this->options();

        // تلاش ۱ → release با تأخیر backoff[0]=1
        $worker->runNextJob($options);
        $row = $this->jobsRow();
        $this->assertNotNull($row);
        $this->assertNull($row['reserved_at']);                        // آزاد برای retry
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertGreaterThan(Clock::timestamp() - 1, (int) $row['available_at']);

        // تلاش ۲ → release با تأخیر backoff[1]=2
        Clock::setTestNow('+3 seconds');
        $worker->runNextJob($options);
        $this->assertCount(2, FailingJob::$attempts);

        // تلاش ۳ = maxTries → failed_jobs
        Clock::setTestNow('+8 seconds');
        $worker->runNextJob($options);
        $this->assertCount(3, FailingJob::$attempts);
        $this->assertSame(0, $queue->size('default'));

        $failed = $queue->failedProvider();
        $this->assertSame(1, $failed->count());

        $records = $failed->all();
        $this->assertStringContainsString('MaxAttemptsExceeded', (string) $records[0]['exception']);
        $this->assertSame('default', (string) $records[0]['queue']);

        // hook کاربر failed()
        $this->assertCount(1, FailingJob::$failedCalls);
        $this->assertSame(1, $worker->failedJobs());
    }

    public function testDeadLetterQueueReceivesFailedJob(): void
    {
        $this->app->make('config')->set('queue.failed.dlq', 'dead');

        $queue = $this->queue();
        $job = new FailingJob('dlq-me');
        $job->tries = 1;   // یک تلاش → شکست فوری
        $queue->push($job);

        $this->worker()->runNextJob($this->options());

        $this->assertSame(1, $queue->failedProvider()->count());
        $this->assertSame(1, $queue->size('dead'));   // DLQ
    }

    // ─── T-04: delayed job ───────────────────────────────────────────────

    public function testDelayedJobWithLater(): void
    {
        Clock::setTestNow('2026-06-01 12:00:00');
        $queue = $this->queue();

        $queue->later(new \DateTimeImmutable('2026-06-01 12:10:00'), new RecordJob('later-1'));

        $this->assertSame(1, $queue->size('default'));   // delayed هم شمرده می‌شود
        $this->assertNull($queue->pop('default'));       // هنوز سررسید نشده

        Clock::setTestNow('2026-06-01 12:10:01');

        // سررسید شد — worker همان pop اتمیک را انجام می‌دهد
        $this->assertTrue($this->worker()->runNextJob($this->options()));
        $this->assertSame(['later-1'], RecordJob::$trace);
    }

    // ─── T-05: Batch ─────────────────────────────────────────────────────

    public function testBatchRunsAllJobsAndFinallyCallback(): void
    {
        $queue = $this->queue();

        $batch = $queue->batch([new BatchCountJob('a'), new BatchCountJob('b'), new BatchCountJob('c')])
            ->name('import')
            ->finally(BatchFinally::class)
            ->dispatch();

        $this->assertSame(3, $batch->totalJobs());
        $this->assertSame(3, $batch->pendingJobs());

        $worker = $this->worker();
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($worker->runNextJob($this->options()));
        }

        $this->assertSame(3, BatchCountJob::$count);

        $fresh = $queue->findBatch($batch->id());
        $this->assertNotNull($fresh);
        $this->assertSame(0, $fresh->pendingJobs());
        $this->assertSame(0, $fresh->failedJobs());
        $this->assertTrue($fresh->finished());
        $this->assertFalse($fresh->cancelled());

        $this->assertCount(1, BatchFinally::$recorded);
        $this->assertStringContainsString('import', BatchFinally::$recorded[0]);
    }

    public function testBatchWithoutAllowFailuresIsCancelled(): void
    {
        $queue = $this->queue();

        $batch = $queue->batch([new BatchCountJob('ok'), new FailingJob('bad')])
            ->name('strict')
            ->dispatch();

        $worker = $this->worker();
        $worker->runNextJob($this->options());                 // job اول موفق
        $worker->runNextJob($this->options());                 // تلاش ۱ شکست → release
        Clock::setTestNow('+3 seconds');
        $worker->runNextJob($this->options());                 // تلاش ۲ شکست → release
        Clock::setTestNow('+8 seconds');
        $worker->runNextJob($this->options());                 // تلاش ۳ → شکست نهایی → cancel

        $fresh = $queue->findBatch($batch->id());
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->cancelled());
        $this->assertSame(1, $fresh->failedJobs());
    }

    // ─── T-06: Chain ─────────────────────────────────────────────────────

    public function testChainRunsInOrder(): void
    {
        $queue = $this->queue();
        $queue->chain([new ChainStepJob('a'), new ChainStepJob('b'), new ChainStepJob('c')])->dispatch();

        $this->assertSame(1, $queue->size('default'));   // فقط عضو اول در صف است

        $worker = $this->worker();
        $worker->runNextJob($this->options());
        $this->assertSame(['a'], ChainStepJob::$trace);

        $worker->runNextJob($this->options());
        $this->assertSame(['a', 'b'], ChainStepJob::$trace);

        $worker->runNextJob($this->options());
        $this->assertSame(['a', 'b', 'c'], ChainStepJob::$trace);
        $this->assertSame(0, $queue->size('default'));
    }

    public function testChainStopsOnFailure(): void
    {
        $queue = $this->queue();
        $queue->chain([new ChainStepJob('x'), new FailingJob('chain-fail'), new ChainStepJob('never')])->dispatch();

        $worker = $this->worker();
        $worker->runNextJob($this->options());           // x موفق → FailingJob push می‌شود
        $this->assertSame(['x'], ChainStepJob::$trace);

        $worker->runNextJob($this->options());           // تلاش ۱ شکست
        Clock::setTestNow('+3 seconds');
        $worker->runNextJob($this->options());           // تلاش ۲ شکست
        Clock::setTestNow('+8 seconds');
        $worker->runNextJob($this->options());           // تلاش ۳ → شکست نهایی

        $this->assertSame(['x'], ChainStepJob::$trace);  // 'never' هرگز اجرا نشد 🔒
        $this->assertSame(0, $queue->size('default'));
    }

    // ─── T-07: Graceful shutdown ─────────────────────────────────────────

    public function testGracefulStopFinishesCurrentJobThenQuits(): void
    {
        $queue = $this->queue();
        for ($i = 0; $i < 5; $i++) {
            $queue->push(new RecordJob('g' . $i));
        }

        $worker = $this->worker();

        // شبیه‌سازی SIGTERM: پس از اولین job، stop() (همان پرچم handler سیگنال)
        $this->app->make('events')->listen(JobProcessed::class, static function () use ($worker): void {
            $worker->stop(0, 'SIGTERM');
        });

        $status = $worker->run($this->options());

        $this->assertSame(0, $status);
        $this->assertSame(1, $worker->processedJobs());  // job جاری تمام شد
        $this->assertSame(4, $queue->size('default'));    // بقیه دست‌نخورده
    }

    public function testRestartSignalStopsWorkerWithCodeOne(): void
    {
        $queue = $this->queue();
        for ($i = 0; $i < 5; $i++) {
            $queue->push(new RecordJob('r' . $i));
        }

        /** @var CacheRepository $cache */
        $cache = $this->app->make(CacheRepository::class);
        $worker = $this->worker();

        // queue:restart هم‌زمان با پردازش
        $this->app->make('events')->listen(JobProcessed::class, static function () use ($cache): void {
            $cache->forever(Worker::RESTART_CACHE_KEY, \time() + 100);
        });

        $status = $worker->run($this->options());

        $this->assertSame(1, $status);                   // کد خروج restart
        $this->assertSame(1, $worker->processedJobs());
    }

    // ─── T-08: Timeout ───────────────────────────────────────────────────

    public function testJobTimeoutFailsWithJobTimedOutException(): void
    {
        if (!\function_exists('pcntl_alarm')) {
            $this->assertTrue(true);   // بدون pcntl — timeout cooperative است

            return;
        }

        $this->queue()->push(new TimeoutJob());
        $this->worker()->runNextJob($this->options());

        $this->assertFalse(TimeoutJob::$completed);       // اجرا قطع شد
        $failed = $this->queue()->failedProvider();
        $this->assertSame(1, $failed->count());
        $this->assertStringContainsString('JobTimedOut', (string) $failed->all(1)[0]['exception']);
    }

    // ─── T-09: Pop اتمیک (workerهای موازی) ──────────────────────────────

    public function testReservedJobsAreNotPoppedTwice(): void
    {
        $queue = $this->queue();
        $queue->push(new RecordJob('w1'));
        $queue->push(new RecordJob('w2'));

        // دو worker موازی: هر دو pop می‌کنند — jobها باید متفاوت باشند
        $jobA = $queue->pop('default');
        $jobB = $queue->pop('default');

        $this->assertNotNull($jobA);
        $this->assertNotNull($jobB);
        $this->assertTrue($jobA->getJobId() !== $jobB->getJobId());

        // pop سوم → هیچ (هر دو reserved هستند؛ SKIP LOCKED)
        $this->assertNull($queue->pop('default'));

        $jobA->delete();
        $jobB->delete();
        $this->assertSame(0, $queue->size('default'));
    }

    public function testStuckReservedJobIsRecoveredAfterRetryAfter(): void
    {
        Clock::setTestNow('2026-06-01 12:00:00');
        $queue = $this->queue();
        $queue->push(new RecordJob('stuck'));

        $job = $queue->pop('default');
        $this->assertNotNull($job);
        $this->assertNull($queue->pop('default'));       // reserved — قابل برداشت نیست

        // worker مرده: پس از retry_after(90s) job برمی‌گردد
        Clock::setTestNow('2026-06-01 12:02:00');       // +120s > 90s
        $recovered = $queue->pop('default');
        $this->assertNotNull($recovered);
        $this->assertSame(2, $recovered->attempts());    // تلاش قبلی هم شمرده می‌شود
    }

    // ─── T-10: سریال‌سازی مدل فقط با کلید ────────────────────────────────

    public function testModelIsSerializedByKeyOnly(): void
    {
        $user = new QueueUser(['name' => 'Ali Secreti', 'email' => 'ali@example.com']);
        $user->save();
        $id = $user->getKey();
        $this->assertNotNull($id);

        $queue = $this->queue();
        $queue->push(new ModelRefJob($user));

        // 🔒 payload فقط «کلاس + کلید» است — نه دادهٔ مدل
        $row = $this->jobsRow();
        $this->assertNotNull($row);
        $raw = (string) $row['payload'];
        $this->assertStringNotContainsString('Ali Secreti', $raw);
        $this->assertStringNotContainsString('ali@example.com', $raw);

        $decoded = \json_decode($raw, true);
        $this->assertSame(QueueUser::class, $decoded['data']['command']['user']['@model']);
        $this->assertSame((int) $id, (int) $decoded['data']['command']['user']['@key']);

        // هنگام اجرا مدل تازه از DB خوانده می‌شود
        $this->worker()->runNextJob($this->options());
        $this->assertSame(['user:' . $id . ':Ali Secreti'], ModelRefJob::$trace);
    }

    // ─── T-11: Allowlist کلاس job ────────────────────────────────────────

    public function testAllowlistRejectsUnsafeClasses(): void
    {
        $queue = $this->queue();

        // push شیء غیرمجاز
        try {
            $queue->push(new \stdClass());
            $this->fail('stdClass باید رد شود');
        } catch (UnsafeJobException $e) {
            $this->assertSame('queue.unsafe_job', $e->messageId);
        }

        // payload دستکاری‌شده با کلاس ناشناخته
        $evil = (string) \json_encode([
            'uuid' => 'X',
            'job' => CallQueuedHandler::class,
            'data' => ['commandName' => \stdClass::class, 'command' => []],
        ]);

        try {
            $queue->serializer()->decodePayload($evil);
            $this->fail('payload با stdClass باید رد شود');
        } catch (UnsafeJobException $e) {
            $this->assertSame('queue.unsafe_job', $e->messageId);
        }

        // کلاس allowlist‌شده مشکلی ندارد
        $this->assertTrue($queue->serializer()->isAllowed(RecordJob::class));
    }

    // ─── T-12: Memory recycle ────────────────────────────────────────────

    public function testWorkerRecyclesOnMemoryLimit(): void
    {
        $queue = $this->queue();
        for ($i = 0; $i < 3; $i++) {
            $queue->push(new RecordJob('m' . $i));
        }

        $worker = $this->worker();
        // آستانه ۱MB — فرآیند از قبل بزرگ‌تر است → recycle پس از اولین job
        $status = $worker->run($this->options(['memory' => 1]));

        $this->assertSame(0, $status);
        $this->assertSame(1, $worker->processedJobs());
        $this->assertSame(2, $queue->size('default'));
    }

    public function testMaxJobsAndStopWhenEmpty(): void
    {
        $queue = $this->queue();
        for ($i = 0; $i < 5; $i++) {
            $queue->push(new RecordJob('j' . $i));
        }

        $worker = $this->worker();
        $worker->run($this->options(['maxJobs' => 2]));
        $this->assertSame(2, $worker->processedJobs());
        $this->assertSame(3, $queue->size('default'));

        $worker2 = $this->worker();
        $worker2->run($this->options(['stopWhenEmpty' => true]));
        $this->assertSame(3, $worker2->processedJobs());
        $this->assertSame(0, $queue->size('default'));
    }

    // ─── یکپارچگی با Event (ShouldQueue roundtrip) ───────────────────────

    public function testQueuedEventRoundtripViaDispatchNow(): void
    {
        $heard = [];
        $events = $this->app->make('events');
        $events->listen(QueuedSampleEvent::class, static function (QueuedSampleEvent $event) use (&$heard): void {
            $heard[] = 'heard:' . $event->info;
        });

        $events->dispatch(new QueuedSampleEvent('order-1'));

        // همزمان اجرا نشد — به صف رفت
        $this->assertSame([], $heard);
        $this->assertSame(1, $this->queue()->size('default'));

        $this->worker()->runNextJob($this->options());

        $this->assertSame(['heard:order-1'], $heard);
        $this->assertSame(0, $this->queue()->size('default'));   // requeue نشد (dispatchNow)
    }

    // ─── Rate limiting ───────────────────────────────────────────────────

    public function testRateLimitedJobIsReleasedWhenOverQuota(): void
    {
        $queue = $this->queue();
        $queue->push((new RecordJob('rl'))->withRateLimit('test-api', 1, 60));
        $queue->push((new RecordJob('rl'))->withRateLimit('test-api', 1, 60));

        $worker = $this->worker();
        $worker->runNextJob($this->options());
        $this->assertSame(['rl'], RecordJob::$trace);            // اولی اجرا شد

        $worker->runNextJob($this->options());
        $this->assertSame(['rl'], RecordJob::$trace);            // دومی release شد (نه اجرا)
        $this->assertSame(1, $queue->size('default'));           // برگشت به صف
    }

    // ─── ابزار ───────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function jobsRow(): ?array
    {
        return $this->app->make('db')->connection()
            ->table('tvn_jobs')
            ->orderBy('id')
            ->first();
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            \is_dir($path) ? $this->rrmdir($path) : \unlink($path);
        }
        \rmdir($dir);
    }
}
