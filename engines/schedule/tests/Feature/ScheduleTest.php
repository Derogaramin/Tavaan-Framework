<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule\Tests\Feature;

require_once __DIR__ . '/../Fixtures/ScheduledSampleJob.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Engine\Queue\Worker;
use Tavaan\Engine\Queue\WorkerOptions;
use Tavaan\Engine\Schedule\CronExpression;
use Tavaan\Engine\Schedule\Exceptions\InvalidCronException;
use Tavaan\Engine\Schedule\ScheduleManager;
use Tavaan\Engine\Schedule\Tests\Fixtures\ScheduledSampleJob;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Schedule — DOC-062 §7 (T-01..T-07) + §8 (AC-SCH-08).
 */
final class ScheduleTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        ScheduledSampleJob::reset();
        Clock::setTestNow(null);

        $this->app = \tavaan_test_app();

        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('schedule.timezone', 'UTC');
        $config->set('queue.default', 'database');
        $config->set('queue.connections.database', ['driver' => 'database', 'table' => 'tvn_jobs', 'queue' => 'schedule', 'retry_after' => 90]);
        $config->set('queue.failed', ['driver' => 'database', 'table' => 'tvn_failed_jobs', 'dlq' => '']);
        $config->set('queue.batching', ['driver' => 'database', 'table' => 'tvn_job_batches']);

        Model::flushTestState();

        $migration = require \dirname(__DIR__, 3) . '/queue/migrations/2026_05_01_000001_create_queue_tables.php';
        $migration->up();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
        $down = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/down' : '';
        if ($down !== '' && \is_file($down)) {
            \unlink($down);
        }
    }

    private function manager(): ScheduleManager
    {
        return $this->app->make(ScheduleManager::class);
    }

    private function at(string $when, string $tz = 'UTC'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($when, new \DateTimeZone($tz));
    }

    // ─── T-01: Cron parser ────────────────────────────────────────────────

    public function testCronParserBasicExpressions(): void
    {
        $every5 = new CronExpression('*/5 * * * *');
        $this->assertTrue($every5->isDue($this->at('2026-06-01 10:05')));
        $this->assertTrue($every5->isDue($this->at('2026-06-01 10:00')));
        $this->assertFalse($every5->isDue($this->at('2026-06-01 10:03')));

        $daily3 = new CronExpression('0 3 * * *');
        $this->assertTrue($daily3->isDue($this->at('2026-06-01 03:00')));
        $this->assertFalse($daily3->isDue($this->at('2026-06-01 03:01')));

        $monthly = new CronExpression('0 0 1 * *');
        $this->assertTrue($monthly->isDue($this->at('2026-06-01 00:00')));
        $this->assertFalse($monthly->isDue($this->at('2026-06-02 00:00')));
    }

    /** AC-SCH-08: بردارهای مرجع (lists/ranges/steps/DOM-DOW OR/dow=7). */
    public function testCronReferenceVectors(): void
    {
        $cases = [
            // [expression, datetime, expected]
            ['*/5 * * * *', '2026-06-01 10:07', false],
            ['30 8,20 * * *', '2026-06-01 08:30', true],
            ['30 8,20 * * *', '2026-06-01 20:30', true],
            ['30 8,20 * * *', '2026-06-01 12:30', false],
            ['0 9-17 * * 1-5', '2026-06-03 12:00', true],    // چهارشنبه
            ['0 9-17 * * 1-5', '2026-06-07 12:00', false],   // یکشنبه
            ['0 9-17 * * 1-5', '2026-06-03 18:00', false],   // خارج ساعت
            ['0 0 13 * 5', '2026-06-13 00:00', true],        // سیزدهم (شنبه) → DOM (OR کلاسیک)
            ['0 0 13 * 5', '2026-06-05 00:00', true],        // جمعه → DOW (OR کلاسیک)
            ['0 0 13 * 5', '2026-06-06 00:00', false],       // هیچ‌کدام
            ['1-30/5 * * * *', '2026-06-01 10:01', true],
            ['1-30/5 * * * *', '2026-06-01 10:06', true],
            ['1-30/5 * * * *', '2026-06-01 10:02', false],
            ['1-30/5 * * * *', '2026-06-01 10:31', false],   // خارج بازه
            ['0 0 * * 0', '2026-06-07 00:00', true],         // یکشنبه = 0
            ['0 0 * * 7', '2026-06-07 00:00', true],         // یکشنبه = 7
            ['0 0 * * 7', '2026-06-01 00:00', false],        // دوشنبه
            ['15 10 * * *', '2026-06-01 10:15', true],
            ['15 10 * * *', '2026-06-01 10:16', false],
            ['0 12 * 6 *', '2026-06-15 12:00', true],        // ماه ژوئن
            ['0 12 * 6 *', '2026-07-15 12:00', false],
        ];

        foreach ($cases as [$expr, $when, $expected]) {
            $cron = new CronExpression($expr);
            $actual = $cron->isDue($this->at($when));
            $this->assertSame(
                $expected,
                $actual,
                \sprintf('cron [%s] در %s باید %s باشد', $expr, $when, $expected ? 'due' : 'not-due'),
            );
        }
    }

    public function testInvalidCronRejected(): void
    {
        try {
            new CronExpression('* * *');
            $this->fail('cron چهار فیلدی باید رد شود');
        } catch (InvalidCronException $e) {
            $this->assertSame('schedule.invalid_cron', $e->messageId);
        }

        try {
            new CronExpression('a b c d e');
            $this->fail('فیلد غیرعدد باید رد شود');
        } catch (InvalidCronException $e) {
            $this->assertSame('schedule.invalid_cron', $e->messageId);
        }
    }

    // ─── T-02: isDue در مرزها ─────────────────────────────────────────────

    public function testIsDueAtMinuteBoundaries(): void
    {
        $cron = new CronExpression('0 0 * * *');
        $this->assertTrue($cron->isDue($this->at('2026-06-01 00:00')));
        $this->assertFalse($cron->isDue($this->at('2026-05-31 23:59')));
        $this->assertFalse($cron->isDue($this->at('2026-06-01 00:01')));

        // ثانیه‌ها نادیده گرفته می‌شوند (granularity = دقیقه)
        $this->assertTrue($cron->isDue($this->at('2026-06-01 00:00:59')));
    }

    // ─── T-03: Fluent builders ───────────────────────────────────────────

    public function testFluentFrequencyBuilders(): void
    {
        $manager = $this->manager();

        $this->assertSame('0 3 * * *', $manager->command('a')->daily()->at('03:00')->expression());
        $this->assertSame('*/5 * * * *', $manager->command('b')->everyFiveMinutes()->expression());
        $this->assertSame('15 * * * *', $manager->command('c')->hourlyAt(15)->expression());
        $this->assertSame('0 17 * * 5', $manager->command('d')->weeklyOn(5, '17:00')->expression());
        $this->assertSame('30 6 15 * *', $manager->command('e')->monthlyOn(15, '06:30')->expression());
        $this->assertSame('0 0 1 1,4,7,10 *', $manager->command('f')->quarterly()->expression());
        $this->assertSame('0 0 1 1 *', $manager->command('g')->yearly()->expression());
        $this->assertSame('*/10 * * * *', $manager->command('h')->cron('*/10 * * * *')->expression());
        $this->assertSame('0 0 * * 0', $manager->command('i')->weekly()->expression());
        $this->assertSame('0,30 * * * *', $manager->command('j')->everyThirtyMinutes()->expression());
    }

    // ─── T-07: nextRunDates ──────────────────────────────────────────────

    public function testNextRunDatesPreview(): void
    {
        $runs = (new CronExpression('0 * * * *'))->nextRunDates(3, $this->at('2026-06-01 10:30'));

        $this->assertCount(3, $runs);
        $this->assertSame('2026-06-01 11:00', $runs[0]->format('Y-m-d H:i'));
        $this->assertSame('2026-06-01 12:00', $runs[1]->format('Y-m-d H:i'));
        $this->assertSame('2026-06-01 13:00', $runs[2]->format('Y-m-d H:i'));

        $daily = (new CronExpression('30 3 * * *'))->nextRunDates(1, $this->at('2026-06-01 10:00'));
        $this->assertSame('2026-06-02 03:30', $daily[0]->format('Y-m-d H:i'));
    }

    // ─── اجرای رویدادها ──────────────────────────────────────────────────

    public function testCallEventRunsWhenDue(): void
    {
        $ran = [];
        $manager = $this->manager();
        $manager->call(static function () use (&$ran): void {
            $ran[] = 'tick';
        })->everyMinute();

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertSame(['tick'], $ran);
        $this->assertCount(1, $results);
        $this->assertSame('finished', $results[0]['status']);
        $this->assertSame(0, $results[0]['exit']);
    }

    public function testNotDueEventIsSkipped(): void
    {
        $ran = [];
        $manager = $this->manager();
        $manager->call(static function () use (&$ran): void {
            $ran[] = 'x';
        })->dailyAt('03:00');

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertSame([], $ran);
        $this->assertCount(0, $results);
    }

    public function testCommandEventRunsInProcess(): void
    {
        $manager = $this->manager();
        $manager->command('version')->everyMinute();

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertCount(1, $results);
        $this->assertSame('finished', $results[0]['status']);
        $this->assertSame(0, $results[0]['exit']);
    }

    public function testExecEventCapturesOutput(): void
    {
        $manager = $this->manager();
        $manager->exec('echo tvn-ok')->everyMinute();

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertCount(1, $results);
        $this->assertSame('finished', $results[0]['status']);
        $this->assertStringContainsString('tvn-ok', $results[0]['output']);
    }

    public function testJobEventIsPushedToQueue(): void
    {
        $manager = $this->manager();
        $manager->job(new ScheduledSampleJob('s1'))->everyMinute();

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertCount(1, $results);
        $this->assertSame('queued', $results[0]['status']);

        /** @var QueueManager $queue */
        $queue = $this->app->make(QueueManager::class);
        $this->assertSame(1, $queue->size('schedule'));

        // worker آن را اجرا می‌کند
        /** @var Worker $worker */
        $worker = $this->app->make(Worker::class);
        $worker->runNextJob(WorkerOptions::fromArray(['sleep' => 0, 'queue' => 'schedule']));
        $this->assertSame(['s1'], ScheduledSampleJob::$trace);
    }

    // ─── T-04: Mutex (withoutOverlapping) ────────────────────────────────

    public function testMutexPreventsOverlappingRun(): void
    {
        /** @var CacheRepository $cache */
        $cache = $this->app->make(CacheRepository::class);

        $manager = $this->manager();
        $event = $manager->command('version')->everyMinute()->withoutOverlapping();

        // شبیه‌سازی نمونهٔ در حال اجرا: mutex از قبل گرفته شده (add اتمیک)
        $this->assertTrue($cache->add($event->mutexName(), true, 60));

        $results = $manager->run($this->at('2026-06-01 12:00'));
        $this->assertCount(1, $results);
        $this->assertSame('skipped-overlapping', $results[0]['status']);

        // پس از آزادسازی، اجرا می‌شود و mutex را آزاد می‌کند
        $cache->forget($event->mutexName());
        $results = $manager->run($this->at('2026-06-01 12:01'));
        $this->assertSame('finished', $results[0]['status']);
        $this->assertFalse($cache->has($event->mutexName()));
    }

    // ─── T-05: onOneServer ───────────────────────────────────────────────

    public function testOnOneServerOnlyFirstServerRuns(): void
    {
        $now = $this->at('2026-06-01 12:00');

        // سرور ۱
        $server1 = $this->manager();
        $server1->command('version')->everyMinute()->onOneServer();
        $results1 = $server1->run($now);
        $this->assertSame('finished', $results1[0]['status']);

        // سرور ۲ — همان cache اشتراکی، همان دقیقه
        $server2 = new ScheduleManager(
            $this->app,
            $this->app->make('config'),
            $this->app->make(CacheRepository::class),
        );
        $server2->command('version')->everyMinute()->onOneServer();
        $results2 = $server2->run($now);
        $this->assertSame('skipped-other-server', $results2[0]['status']);
    }

    // ─── T-06: Timezone ──────────────────────────────────────────────────

    public function testEventTimezone(): void
    {
        $manager = $this->manager();

        $ran = [];
        $manager->call(static function () use (&$ran): void {
            $ran[] = 'tehran-8am';
        })->cron('0 8 * * *')->timezone('Asia/Tehran');

        // ۰۴:۳۰ UTC = ۰۸:۰۰ تهران → سررسید
        $manager->run($this->at('2026-06-01 04:30'));
        $this->assertSame(['tehran-8am'], $ran);

        // ۰۸:۰۰ UTC = ۱۱:۳۰ تهران → سررسید نیست
        $ran = [];
        $manager->run($this->at('2026-06-02 08:00'));
        $this->assertSame([], $ran);
    }

    public function testManagerDefaultTimezone(): void
    {
        // timezone پیش‌فرض manager روی UTC ست شده (setUp)
        $manager = $this->manager();

        $ran = [];
        $manager->call(static function () use (&$ran): void {
            $ran[] = 'noon';
        })->dailyAt('12:00');

        // با پیش‌فرض UTC: ساعت ۱۲:۰۰ UTC اجرا می‌شود
        $manager->run($this->at('2026-06-01 12:00', 'UTC'));
        $this->assertSame(['noon'], $ran);

        // پیش‌فرض manager به تهران تغییر می‌کند
        $this->app->make('config')->set('schedule.timezone', 'Asia/Tehran');

        // ۱۲:۰۰ UTC = ۱۵:۳۰ تهران → دیگر سررسید نیست
        $ran = [];
        $manager->run($this->at('2026-06-02 12:00', 'UTC'));
        $this->assertSame([], $ran);

        // ۰۸:۳۰ UTC = ۱۲:۰۰ تهران → سررسید
        $manager->run($this->at('2026-06-02 08:30', 'UTC'));
        $this->assertSame(['noon'], $ran);
    }

    // ─── فیلترها ─────────────────────────────────────────────────────────

    public function testWhenFilterSkips(): void
    {
        $manager = $this->manager();
        $manager->command('version')->everyMinute()->when(static fn (): bool => false);

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertCount(1, $results);
        $this->assertSame('skipped-filter', $results[0]['status']);
    }

    public function testBetweenTimeWindow(): void
    {
        $manager = $this->manager();
        $manager->command('version')->everyMinute()->between('09:00', '17:00');

        // داخل بازه → اجرا
        $results = $manager->run($this->at('2026-06-01 12:00'));
        $this->assertCount(1, $results);
        $this->assertSame('finished', $results[0]['status']);

        // خارج بازه → اصلاً سررسید نیست
        $results = $manager->run($this->at('2026-06-01 20:00'));
        $this->assertCount(0, $results);
    }

    public function testBetweenOvernightWindow(): void
    {
        $manager = $this->manager();
        $manager->command('version')->everyMinute()->between('22:00', '02:00');

        $this->assertCount(1, $manager->run($this->at('2026-06-01 23:00')));
        $this->assertCount(1, $manager->run($this->at('2026-06-01 01:00')));
        $this->assertCount(0, $manager->run($this->at('2026-06-01 12:00')));
    }

    // ─── Maintenance mode ────────────────────────────────────────────────

    public function testMaintenanceModeSkipsUnlessFlagged(): void
    {
        $down = TVN_BASE_PATH . '/storage/framework/down';
        \file_put_contents($down, (string) \json_encode(['message' => 'test']));

        try {
            $manager = $this->manager();
            $manager->command('version')->everyMinute();
            $manager->command('about')->everyMinute()->evenInMaintenanceMode();

            $results = $manager->run($this->at('2026-06-01 12:00'));

            $this->assertCount(2, $results);
            $this->assertSame('skipped-maintenance', $results[0]['status']);
            $this->assertSame('finished', $results[1]['status']);
        } finally {
            \unlink($down);
        }
    }

    // ─── callbackها ──────────────────────────────────────────────────────

    public function testSuccessAndFailureCallbacks(): void
    {
        $ok = false;
        $bad = false;

        $manager = $this->manager();
        $manager->call(static fn (): int => 1)->everyMinute()->onSuccess(static function () use (&$ok): void {
            $ok = true;
        });
        $manager->call(static function (): void {
            throw new \RuntimeException('boom');
        })->everyMinute()->onFailure(static function () use (&$bad): void {
            $bad = true;
        });

        $results = $manager->run($this->at('2026-06-01 12:00'));

        $this->assertTrue($ok);
        $this->assertTrue($bad);
        $this->assertSame('finished', $results[0]['status']);
        $this->assertSame('failed', $results[1]['status']);
    }

    public function testOutputWrittenToFile(): void
    {
        $path = \sys_get_temp_dir() . '/tvn-schedule-out-' . \getmypid() . '.txt';
        @\unlink($path);

        $manager = $this->manager();
        $manager->exec('echo scheduled-line')->everyMinute()->output($path);
        $manager->run($this->at('2026-06-01 12:00'));

        $this->assertStringContainsString('scheduled-line', (string) \file_get_contents($path));
        \unlink($path);
    }
}
