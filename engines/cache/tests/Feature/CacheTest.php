<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Tests\Feature;

use Tavaan\Engine\Cache\CacheManager;
use Tavaan\Engine\Cache\CacheRepository;
use Tavaan\Engine\Cache\Stores\ArrayStore;
use Tavaan\Engine\Cache\Stores\DatabaseStore;
use Tavaan\Engine\Cache\Stores\FileStore;
use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Cache — DOC-060 §10 (T-01..T-09).
 */
final class CacheTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-cache-test-' . \getmypid();
        if (!\is_dir($this->tmp)) {
            \mkdir($this->tmp, 0775, true);
        }
        Clock::setTestNow(null);
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
        $this->rrmdir($this->tmp);
    }

    private function config(array $overrides = []): Repository
    {
        return new Repository(\array_replace_recursive([
            'cache' => [
                'default' => 'file',
                'stores' => [
                    'array' => ['driver' => 'array'],
                    'file' => ['driver' => 'file', 'path' => $this->tmp . '/data'],
                    'redis' => ['driver' => 'redis', 'host' => '127.0.0.1'],
                    'apcu' => ['driver' => 'apcu'],
                    'memcached' => ['driver' => 'memcached', 'servers' => []],
                ],
                'tags' => ['enabled' => true, 'prefix' => 'tvn:tag:'],
                'lock' => ['timeout' => 5, 'retry' => 20],
                'stampede_protection' => ['enabled' => true, 'lock_ttl' => 10, 'serve_stale' => true, 'stale_grace' => 60, 'max_wait_retries' => 2],
                'max_key_length' => 512,
                'max_value_size' => 1048576,
            ],
        ], $overrides));
    }

    // ─── T-01: درایورها ───────────────────────────────────────────────────

    public function testArrayStoreBasics(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));
        $this->assertStoreContract($repo);
    }

    public function testFileStoreBasics(): void
    {
        $repo = new CacheRepository(new FileStore($this->tmp . '/data'), $this->config()->get('cache'));
        $this->assertStoreContract($repo);
    }

    public function testDatabaseStoreBasics(): void
    {
        $repo = new CacheRepository($this->databaseStore(), $this->config()->get('cache'));
        $this->assertStoreContract($repo);
    }

    private function assertStoreContract(CacheRepository $repo): void
    {
        $this->assertTrue($repo->put('k1', ['a' => 1, 'fa' => 'سلام'], 60));
        $this->assertSame(['a' => 1, 'fa' => 'سلام'], $repo->get('k1'));
        $this->assertTrue($repo->has('k1'));
        $this->assertFalse($repo->missing('k1'));
        $this->assertTrue($repo->missing('nope'));

        $this->assertTrue($repo->forever('k2', 'always'));
        $this->assertSame('always', $repo->get('k2'));

        $this->assertTrue($repo->putMany(['m1' => 1, 'm2' => 2], 60));
        $this->assertSame(['m1' => 1, 'ghost' => null], $repo->many(['m1', 'ghost']));

        $this->assertSame(1, $repo->increment('counter'));
        $this->assertSame(6, $repo->increment('counter', 5));
        $this->assertSame(4, $repo->decrement('counter', 2));

        $this->assertSame(4, $repo->pull('counter'));
        $this->assertNull($repo->get('counter'));

        $this->assertTrue($repo->forget('k1'));
        $this->assertNull($repo->get('k1'));

        $repo->put('flush-me', 1, 60);
        $this->assertTrue($repo->flush());
        $this->assertNull($repo->get('flush-me'));
    }

    private function databaseStore(): DatabaseStore
    {
        $dbConfig = new Repository(['database' => [
            'default' => 'cache_test',
            'connections' => ['cache_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
        ]]);
        $manager = new ConnectionManager($dbConfig);
        (new SchemaBuilder($manager))->create('cache', function (Blueprint $t): void {
            $t->string('cache_key', 190)->unique();
            $t->text('cache_value');
            $t->unsignedBigInteger('expires_at')->index();
        });

        return new DatabaseStore($manager->connection(), 'cache');
    }

    // ─── T-02: TTL با Clock Fake ──────────────────────────────────────────

    public function testTtlExpirationWithFakeClock(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));

        Clock::setTestNow('2026-01-01 12:00:00');
        $repo->put('expire-me', 'value', 60);
        $this->assertSame('value', $repo->get('expire-me'));

        Clock::setTestNow('2026-01-01 12:00:59');
        $this->assertSame('value', $repo->get('expire-me'));

        Clock::setTestNow('2026-01-01 12:01:01');
        $this->assertNull($repo->get('expire-me'));
        $this->assertFalse($repo->has('expire-me'));
    }

    public function testFileStoreExpiration(): void
    {
        $repo = new CacheRepository(new FileStore($this->tmp . '/ttl'), $this->config()->get('cache'));

        Clock::setTestNow('2026-01-01 12:00:00');
        $repo->put('k', 'v', 30);

        Clock::setTestNow('2026-01-01 12:01:00');
        $this->assertNull($repo->get('k'));
    }

    // ─── T-03: Tag flush ──────────────────────────────────────────────────

    public function testTagFlushOnlyAffectsTag(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));

        $repo->tags(['shop', 'product'])->put('p:1', 'product-1', 3600);
        $repo->tags(['shop'])->put('cart:1', 'cart-1', 3600);
        $repo->put('untagged', 'stays', 3600);

        $repo->tags(['shop'])->flush();

        // تگ shop → identifier جدید → آیتم‌هایش ناپدید
        $this->assertNull($repo->tags(['shop', 'product'])->get('p:1'));
        $this->assertNull($repo->tags(['shop'])->get('cart:1'));

        // بدون تگ دست‌نخورده
        $this->assertSame('stays', $repo->get('untagged'));
    }

    public function testTagRemember(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));

        $calls = 0;
        $value = $repo->tags(['t'])->remember('k', 60, function () use (&$calls): string {
            $calls++;

            return 'computed';
        });
        $this->assertSame('computed', $value);
        $this->assertSame(1, $calls);

        $value2 = $repo->tags(['t'])->remember('k', 60, function () use (&$calls): string {
            $calls++;

            return 'recomputed';
        });
        $this->assertSame('computed', $value2);
        $this->assertSame(1, $calls);
    }

    // ─── T-04: Lock ───────────────────────────────────────────────────────

    public function testLockOnlyOneAcquires(): void
    {
        $store = new ArrayStore();
        $repoA = new CacheRepository($store, $this->config()->get('cache'));
        $repoB = new CacheRepository($store, $this->config()->get('cache'));

        $lockA = $repoA->lock('process:1', 30);
        $lockB = $repoB->lock('process:1', 30);

        $this->assertTrue($lockA->acquire());
        $this->assertFalse($lockB->acquire(), 'second locker must fail (non-blocking)');

        // release توسط غیرمالک → قفل باقی می‌ماند
        $this->assertTrue($lockB->release());
        $lockB2 = $repoB->lock('process:1', 30);
        $this->assertFalse($lockB2->acquire(), 'release by non-owner must not free the lock');

        // release توسط مالک
        $lockA->release();
        $lockC = $repoA->lock('process:1', 30);
        $this->assertTrue($lockC->acquire(), 'after owner release, lock must be acquirable');
        $lockC->release();
    }

    public function testLockBlockWithCallback(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));

        $result = $repo->lock('cb-lock', 10)->block(1, fn (): string => 'done');
        $this->assertSame('done', $result);

        // قفل پس از block آزاد شده
        $this->assertTrue($repo->lock('cb-lock', 10)->acquire());
    }

    public function testLockTimeoutThrows(): void
    {
        $store = new ArrayStore();
        $repoA = new CacheRepository($store, $this->config()->get('cache'));
        $repoB = new CacheRepository($store, $this->config()->get('cache'));

        $repoA->lock('busy', 30)->acquire();

        $this->expectException(\Tavaan\Engine\Cache\Exceptions\LockTimeoutException::class);
        $repoB->lock('busy', 30)->block(0, fn (): string => 'never');
    }

    // ─── T-05: Stampede ───────────────────────────────────────────────────

    public function testStampedeProtectionSingleRebuild(): void
    {
        $store = new ArrayStore();
        $config = (array) $this->config()->get('cache');
        $repoA = new CacheRepository($store, $config);
        $repoB = new CacheRepository($store, $config);

        // فرایند A قفل بازسازی را نگه داشته
        $guard = $repoA->lock('lock:stampede:hot', 30);
        $this->assertTrue($guard->acquire());

        // فرایند B: بدون stale → retry محدود → یک بازسازی تسلیمی (بدون کش)
        $calls = 0;
        $value = $repoB->remember('hot', 60, function () use (&$calls): string {
            $calls++;

            return 'rebuilt-by-B';
        });
        $this->assertSame('rebuilt-by-B', $value);
        $this->assertSame(1, $calls, 'surrender path must call callback exactly once');

        $guard->release();
    }

    public function testStampedeServesStale(): void
    {
        $store = new ArrayStore();
        $config = (array) $this->config()->get('cache');
        $repoA = new CacheRepository($store, $config);
        $repoB = new CacheRepository($store, $config);

        // مقدار اولیه با stale copy
        Clock::setTestNow('2026-01-01 12:00:00');
        $repoA->put('item', 'fresh-v1', 60);   // stale copy با grace ۶۰ ثانیه

        // انقضای مقدار اصلی ولی stale زنده
        Clock::setTestNow('2026-01-01 12:01:10');
        $this->assertNull($store->get('item'));
        $this->assertSame('fresh-v1', $store->get('stale:item'));

        // فرایند دیگر قفل را نگه داشته
        $guard = $repoA->lock('lock:stampede:item', 30);
        $this->assertTrue($guard->acquire());

        $calls = 0;
        $value = $repoB->remember('item', 60, function () use (&$calls): string {
            $calls++;

            return 'rebuilt';
        });
        $this->assertSame('fresh-v1', $value, 'must serve stale while locked');
        $this->assertSame(0, $calls);

        $guard->release();
    }

    // ─── T-06/T-07 ────────────────────────────────────────────────────────

    public function testRememberHitMiss(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));

        $calls = 0;
        $repo->remember('r', 60, function () use (&$calls): int {
            $calls++;

            return 42;
        });
        $repo->remember('r', 60, function () use (&$calls): int {
            $calls++;

            return 43;
        });

        $this->assertSame(1, $calls);
        $this->assertSame(42, $repo->get('r'));
    }

    public function testAddOnlyIfMissing(): void
    {
        $repo = new CacheRepository(new FileStore($this->tmp . '/add'), $this->config()->get('cache'));

        $this->assertTrue($repo->add('once', 'first', 60));
        $this->assertFalse($repo->add('once', 'second', 60));
        $this->assertSame('first', $repo->get('once'));
    }

    public function testDatabaseAddAtomicOnUnique(): void
    {
        $repo = new CacheRepository($this->databaseStore(), $this->config()->get('cache'));

        $this->assertTrue($repo->add('uniq', 1, 60));
        $this->assertFalse($repo->add('uniq', 2, 60));
        $this->assertSame(1, $repo->get('uniq'));

        // منقضی → add مجدد موفق
        Clock::setTestNow(new \DateTimeImmutable('+120 seconds'));
        $this->assertTrue($repo->add('uniq', 3, 60));
        Clock::setTestNow(null);
    }

    // ─── T-08: Object Injection ───────────────────────────────────────────

    public function testSerializedObjectInjectionImpossible(): void
    {
        $repo = new CacheRepository(new FileStore($this->tmp . '/inject'), $this->config()->get('cache'));

        $payload = 'O:8:"EvilCls":1:{s:3:"cmd";s:2:"rm";}';
        $repo->put('inj', $payload, 60);

        $value = $repo->get('inj');
        // فقط به‌عنوان رشته برمی‌گردد — هرگز unserialize نمی‌شود
        $this->assertSame($payload, $value);
        $this->assertTrue(\is_string($value));
    }

    public function testTamperedCacheFileReadAsMiss(): void
    {
        $dir = $this->tmp . '/tamper';
        $store = new FileStore($dir);
        $repo = new CacheRepository($store, $this->config()->get('cache'));

        $repo->put('t', 'v', 60);

        // خرابکاری در فایل‌های کش → get باید null برگرداند (نه crash)
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && \str_ends_with($file->getFilename(), '.cache')) {
                \file_put_contents($file->getPathname(), '<?php phpinfo(); // not json');
            }
        }

        $this->assertNull($repo->get('t'));
    }

    // ─── T-09: Fallback ───────────────────────────────────────────────────

    public function testMissingExtensionsFallbackToFile(): void
    {
        $manager = new CacheManager($this->config());

        // redis/apcu/memcached بدون extension → fallback به file (بدون crash)
        foreach (['redis', 'apcu', 'memcached'] as $name) {
            if (\extension_loaded($name)) {
                continue;   // در این محیط نصب نیست؛ اگر بود skip
            }
            $repo = $manager->store($name);
            $this->assertInstanceOf(CacheRepository::class, $repo);
            $this->assertInstanceOf(FileStore::class, $repo->getStore());
            $repo->put('fb-' . $name, 'ok', 60);
            $this->assertSame('ok', $repo->get('fb-' . $name));
        }

        $this->assertNotEmpty(\array_intersect(['redis→file', 'apcu→file', 'memcached→file'], $manager->fallbacks()));
    }

    public function testManagerDefaultDriverAndFacadeApi(): void
    {
        $manager = new CacheManager($this->config());

        $this->assertSame('file', $manager->getDefaultDriver());
        $manager->put('via-manager', ['x' => 1], 60);
        $this->assertSame(['x' => 1], $manager->get('via-manager'));
        $this->assertTrue($manager->has('via-manager'));
        $manager->forget('via-manager');

        $store = $manager->store('array');
        $store->put('in-array', 1, 60);
        $this->assertSame(1, $store->get('in-array'));
        $this->assertNull($manager->get('in-array'), 'stores must be isolated');
    }

    public function testKeyTooLongRejected(): void
    {
        $repo = new CacheRepository(new ArrayStore(), $this->config()->get('cache'));

        $this->expectException(\InvalidArgumentException::class);
        $repo->put(\str_repeat('k', 600), 'v');
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @\rmdir($file->getPathname()) : @\unlink($file->getPathname());
        }
        @\rmdir($dir);
    }
}
