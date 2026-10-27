<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Feature;

require_once __DIR__ . '/../Fixtures/PlainFailJob.php';
require_once __DIR__ . '/../Fixtures/RecordJob.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Engine\Console\Input\ArgvInput;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Engine\Queue\Tests\Fixtures\PlainFailJob;
use Tavaan\Engine\Queue\Tests\Fixtures\RecordJob;
use Tavaan\Engine\Queue\Worker;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست فرمان‌های console صف به‌صورت in-process روی SQLite in-memory —
 * پوشش QueueWork/Failed/Retry/Flush/PruneFailed/Restart/Monitor
 * (اسپرینت پوشش گام ۲ — DOC-122 TECH-COV-01).
 *
 * queue:listen عمداً skip مستند دارد: subprocess آن `bin/tvn` واقعی ریپو را
 * با DB واقعی بالا می‌آورد که در محیط in-memory تست امن نیست.
 */
final class QueueCommandsTest extends TestCase
{
    private ?Application $appInstance = null;

    protected function setUp(): void
    {
        RecordJob::reset();
        PlainFailJob::reset();
        Clock::setTestNow(null);
        Model::flushTestState();

        $config = $this->app()->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('queue.default', 'database');
        $config->set('queue.connections.database', ['driver' => 'database', 'table' => 'tvn_jobs', 'queue' => 'default', 'retry_after' => 90]);
        $config->set('queue.failed', ['driver' => 'database', 'table' => 'tvn_failed_jobs', 'dlq' => '']);
        $config->set('queue.batching', ['driver' => 'database', 'table' => 'tvn_job_batches']);

        $migration = require \dirname(__DIR__, 2) . '/migrations/2026_05_01_000001_create_queue_tables.php';
        $migration->up();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    private function app(): Application
    {
        return $this->appInstance ??= ApplicationBuilder::create((string) TVN_BASE_PATH)->forConsole()->boot();
    }

    /** @return array{code: int, out: string} */
    private function run(string ...$argv): array
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->app()->make(KernelInterface::class);

        $stream = \fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $code = $kernel->handle(new ArgvInput($argv), new Output($stream));
        \rewind($stream);
        $out = (string) \stream_get_contents($stream);
        \fclose($stream);

        return ['code' => $code, 'out' => $out];
    }

    private function queue(): QueueManager
    {
        return $this->app()->make(QueueManager::class);
    }

    // ─── queue:work ─────────────────────────────────────────────────────────
    // 🔒 --memory=2048 در همهٔ فراخوانی‌ها: در اجرای سوئیت کامل، حافظهٔ
    // فرایند از پیشینهٔ ~600MB عبور می‌کند و worker با پیش‌فرض --memory=128
    // پس از نخستین job بازیافت می‌شد (خروج زودهنگام). سقف حافظهٔ worker یک
    // گزینهٔ اجرایی است، نه رفتار قابل تست — پس صریح بالا برده می‌شود.

    public function testQueueWorkOnceProcessesPushedJob(): void
    {
        $this->queue()->push(new RecordJob('cmd-once'));

        $result = $this->run('queue:work', '--once', '--sleep=0', '--memory=2048');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('worker صف آغاز شد', $result['out']);
        self::assertSame(['cmd-once'], RecordJob::$trace);
    }

    public function testQueueWorkStopWhenEmptyDrainsQueue(): void
    {
        $this->queue()->push(new RecordJob('drain-1'));
        $this->queue()->push(new RecordJob('drain-2'));

        $result = $this->run('queue:work', '--stop-when-empty', '--sleep=0', '--memory=2048');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertSame(['drain-1', 'drain-2'], RecordJob::$trace);
        self::assertSame(0, $this->queue()->size('default'));
    }

    public function testQueueWorkMaxJobsStopsEarly(): void
    {
        $this->queue()->push(new RecordJob('max-1'));
        $this->queue()->push(new RecordJob('max-2'));
        $this->queue()->push(new RecordJob('max-3'));

        $result = $this->run('queue:work', '--max-jobs=2', '--sleep=0', '--stop-when-empty', '--memory=2048');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertCount(2, RecordJob::$trace);
        self::assertSame(1, $this->queue()->size('default'));
    }

    // ─── failed/flush/prune ─────────────────────────────────────────────────

    public function testQueueFailedEmptyMessage(): void
    {
        $result = $this->run('queue:failed');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('هیچ job شکست‌خورده‌ای ثبت نشده است', $result['out']);
    }

    public function testFailedJobListedThenFlushedByForce(): void
    {
        $this->queue()->push(new PlainFailJob('cmd-fail'));
        $worker = $this->run('queue:work', '--once', '--tries=1', '--sleep=0', '--memory=2048');
        self::assertSame(0, $worker['code'], $worker['out']);

        self::assertSame(1, $this->queue()->failedProvider()->count());

        $list = $this->run('queue:failed');
        self::assertSame(0, $list['code'], $list['out']);
        self::assertStringContainsString('PlainFailJob', $list['out']);

        $flush = $this->run('queue:flush', '--force');
        self::assertSame(0, $flush['code'], $flush['out']);
        self::assertStringContainsString('1 رکورد شکست حذف شد', $flush['out']);
        self::assertSame(0, $this->queue()->failedProvider()->count());
    }

    public function testQueuePruneFailedByHours(): void
    {
        $this->queue()->push(new PlainFailJob('prune-me'));
        $this->run('queue:work', '--once', '--tries=1', '--sleep=0', '--memory=2048');
        self::assertSame(1, $this->queue()->failedProvider()->count());

        // hours≥1 → رکورد تازه حذف نمی‌شود
        $keep = $this->run('queue:prune-failed', '--hours=24');
        self::assertSame(0, $keep['code'], $keep['out']);
        self::assertStringContainsString('0 رکورد', $keep['out']);
        self::assertSame(1, $this->queue()->failedProvider()->count());

        // clock یک روز جلوتر → رکورد قدیمی حذف می‌شود
        Clock::setTestNow(Clock::now()->modify('+25 hours'));
        $prune = $this->run('queue:prune-failed', '--hours=24');
        self::assertSame(0, $prune['code'], $prune['out']);
        self::assertStringContainsString('1 رکورد', $prune['out']);
        self::assertSame(0, $this->queue()->failedProvider()->count());
    }

    // ─── retry ──────────────────────────────────────────────────────────────

    public function testQueueRetryAllReturnsFailedJobToQueue(): void
    {
        $this->queue()->push(new PlainFailJob('retry-me'));
        $this->run('queue:work', '--once', '--tries=1', '--sleep=0', '--memory=2048');
        self::assertSame(1, $this->queue()->failedProvider()->count());

        $retry = $this->run('queue:retry', '--all');
        self::assertSame(0, $retry['code'], $retry['out']);
        self::assertStringContainsString('1 job شکست‌خورده به صف برگشت', $retry['out']);
        self::assertSame(0, $this->queue()->failedProvider()->count());
        self::assertSame(1, $this->queue()->size('default'));
    }

    public function testQueueRetryUnknownIdFails(): void
    {
        $result = $this->run('queue:retry', '999999');

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('یافت نشد', $result['out']);
    }

    // ─── restart / monitor ──────────────────────────────────────────────────

    public function testQueueRestartSetsSignalInCache(): void
    {
        $result = $this->run('queue:restart');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('سیگنال restart ارسال شد', $result['out']);

        /** @var CacheRepository $cache */
        $cache = $this->app()->make(CacheRepository::class);
        self::assertNotNull($cache->get(Worker::RESTART_CACHE_KEY));
    }

    public function testQueueMonitorBelowThresholdIsGreen(): void
    {
        $result = $this->run('queue:monitor', 'database:default', '--max=10');

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('✅', $result['out']);
    }

    public function testQueueMonitorAboveThresholdFails(): void
    {
        $this->queue()->push(new RecordJob('mon-1'));
        $this->queue()->push(new RecordJob('mon-2'));

        $result = $this->run('queue:monitor', 'database:default', '--max=1');

        self::assertSame(1, $result['code'], $result['out']);
        self::assertStringContainsString('🔴', $result['out']);
        self::assertStringContainsString('از آستانه عبور کردند', $result['out']);
    }

    public function testQueueMonitorUnknownConnectionShowsErrorRow(): void
    {
        $result = $this->run('queue:monitor', 'no-such-conn:default', '--max=5');

        self::assertSame(1, $result['code'], $result['out']);
        self::assertStringContainsString('خطا:', $result['out']);
    }

    // ─── queue:listen (skip مستند) ──────────────────────────────────────────

    public function testQueueListenIsDocumentedSkip(): void
    {
        // 🔒 skip مستند (DOC-122): queue:listen با proc_open یک `bin/tvn` واقعی
        // از ریپو (با .env و DB واقعی) بالا می‌آورد و تا max-time زنده می‌ماند —
        // در محیط in-memory تست نه امن است و نه قطعی. رفتار درون‌فرآیندی worker
        // در QueueTest و queue:work در همین فایل پوشیده شده است.
        $this->skip('queue:listen = subprocess با DB واقعی ریپو — در محیط in-memory تست امن/قطعی نیست');
    }
}
