<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Tests\Feature;

use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Exceptions\QueryException;
use Tavaan\Engine\Database\Testing\FakeConnection;
use Tavaan\Engine\Database\Testing\FlakyDeadlockConnection;
use Tavaan\Engine\Database\Testing\RecordingDispatcher;
use Tavaan\Engine\Database\Testing\RecordingLogger;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست اجرای واقعی Connection روی SQLite in-memory — DOC-047 §12 (T-01..T-15).
 */
final class ConnectionTest extends TestCase
{
    private RecordingDispatcher $events;

    private RecordingLogger $logger;

    private ConnectionManager $manager;

    private Connection $db;

    protected function setUp(): void
    {
        $this->events = new RecordingDispatcher();
        $this->logger = new RecordingLogger();

        $config = new Repository([
            'database' => [
                'default' => 'test',
                'connections' => [
                    'test' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                        'options' => [
                            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        ],
                    ],
                ],
                'query_log' => ['enabled' => true, 'slow_threshold' => 0.0001, 'max_entries' => 100],
                'n_plus_one_detection' => true,
                'n_plus_one_threshold' => 5,
                'pagination' => ['max_per_page' => 100],
            ],
        ]);

        $this->manager = new ConnectionManager($config, $this->events, $this->logger);
        $this->db = $this->manager->connection();

        $this->db->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE,
            age INTEGER,
            balance NUMERIC,
            is_active INTEGER NOT NULL DEFAULT 1,
            settings TEXT,
            login_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT
        )');
        $this->db->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, views INTEGER NOT NULL DEFAULT 0)');
    }

    // ─── T-01: اتصال Lazy ─────────────────────────────────────────────────

    public function testLazyConnection(): void
    {
        $reflection = new \ReflectionProperty(Connection::class, 'pdo');
        $this->assertTrue($reflection->isPrivate(), 'pdo property must stay private');

        // ساخت ConnectionManager/Connection نباید اتصال برقرار کند
        $config = new Repository(['database' => [
            'default' => 'broken',
            'connections' => ['broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'nope', 'username' => 'x', 'password' => 'y']],
        ]]);
        $manager = new ConnectionManager($config);
        $connection = $manager->connection();   // ← بدون exception (lazy)
        $this->assertSame('mysql', $connection->driver());

        // اولین تماس با pdo() → ConnectionException
        $this->expectException(\Tavaan\Engine\Database\Exceptions\ConnectionException::class);
        $connection->pdo();
    }

    // ─── CRUD پایه ────────────────────────────────────────────────────────

    public function testInsertAndSelect(): void
    {
        $id = $this->db->table('users')->insertGetId([
            'name' => 'علی', 'email' => 'ali@example.com', 'age' => 30, 'balance' => 1500.5,
            'is_active' => true, 'created_at' => Clock::utcNow(),
        ]);
        $this->assertSame(1, (int) $id);

        $row = $this->db->table('users')->where('email', 'ali@example.com')->first();
        $this->assertNotNull($row);
        $this->assertSame('علی', $row['name']);
        $this->assertSame(30, (int) $row['age']);
    }

    public function testBindingTypes(): void
    {
        // T-04: null/bool/int/string/DateTime
        $this->db->table('users')->insert([
            ['name' => 'null-case', 'email' => null, 'age' => null, 'is_active' => false, 'created_at' => new \DateTimeImmutable('2026-01-01 10:20:30', new \DateTimeZone('UTC'))],
            ['name' => 'int-case', 'email' => 'i@x.io', 'age' => 42, 'is_active' => true, 'created_at' => '2026-01-02 00:00:00'],
        ]);

        $null = $this->db->table('users')->where('name', 'null-case')->first();
        $this->assertNull($null['email']);
        $this->assertNull($null['age']);
        $this->assertSame(0, (int) $null['is_active']);
        $this->assertTrue(\str_starts_with((string) $null['created_at'], '2026-01-01 10:20:30'));

        $int = $this->db->table('users')->where('age', '>', 40)->first();
        $this->assertSame('int-case', $int['name']);
    }

    public function testUpdateIncrementDelete(): void
    {
        $id = $this->db->table('users')->insertGetId(['name' => 'a', 'email' => 'a@x.io', 'login_count' => 5]);

        $affected = $this->db->table('users')->where('id', $id)->update(['name' => 'b']);
        $this->assertSame(1, $affected);

        $this->db->table('users')->where('id', $id)->increment('login_count', 3);
        $this->assertSame(8, (int) $this->db->table('users')->where('id', $id)->value('login_count'));

        $this->db->table('users')->where('id', $id)->decrement('login_count', 2);
        $this->assertSame(6, (int) $this->db->table('users')->where('id', $id)->value('login_count'));

        $deleted = $this->db->table('users')->where('id', $id)->delete();
        $this->assertSame(1, $deleted);
        $this->assertSame(0, $this->db->table('users')->where('id', $id)->count());
    }

    public function testAggregatesAndExists(): void
    {
        $this->db->table('posts')->insert([
            ['user_id' => 1, 'title' => 'p1', 'views' => 10],
            ['user_id' => 1, 'title' => 'p2', 'views' => 25],
            ['user_id' => 2, 'title' => 'p3', 'views' => 5],
        ]);

        $this->assertSame(3, $this->db->table('posts')->count());
        $this->assertSame(40, (int) $this->db->table('posts')->sum('views'));
        $this->assertSame(5, (int) $this->db->table('posts')->min('views'));
        $this->assertSame(25, (int) $this->db->table('posts')->max('views'));
        $this->assertTrue(\abs((float) $this->db->table('posts')->avg('views') - 13.333) < 0.01);
        $this->assertTrue($this->db->table('posts')->where('title', 'p1')->exists());
        $this->assertFalse($this->db->table('posts')->where('title', 'nope')->exists());
    }

    public function testGroupByHaving(): void
    {
        $this->db->table('posts')->insert([
            ['user_id' => 1, 'title' => 'a', 'views' => 10],
            ['user_id' => 1, 'title' => 'b', 'views' => 30],
            ['user_id' => 2, 'title' => 'c', 'views' => 1],
        ]);

        $rows = $this->db->table('posts')
            ->selectRaw('user_id, SUM(views) as total')
            ->groupBy('user_id')
            ->having('total', '>', 5)
            ->orderBy('total', 'desc')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(40, (int) $rows[0]['total']);
    }

    public function testUpsertAndInsertOrIgnore(): void
    {
        // T-09: upsert (روی SQLite واقعی؛ MySQL/PG در تست compile)
        $this->db->table('users')->insert(['name' => 'first', 'email' => 'u@x.io', 'age' => 20]);

        $affected = $this->db->table('users')->upsert(
            [['name' => 'updated', 'email' => 'u@x.io', 'age' => 21]],
            uniqueBy: ['email'],
            update: ['name', 'age'],
        );
        $this->assertGreaterThan(0, $affected);

        $row = $this->db->table('users')->where('email', 'u@x.io')->first();
        $this->assertSame('updated', $row['name']);
        $this->assertSame(1, $this->db->table('users')->where('email', 'u@x.io')->count());

        $ignored = $this->db->table('users')->insertOrIgnore([['name' => 'dupe', 'email' => 'u@x.io']]);
        $this->assertSame(0, $ignored);
    }

    // ─── T-05: تراکنش تودرتو با Savepoint ─────────────────────────────────

    public function testNestedTransactionSavepoints(): void
    {
        $this->db->transaction(function (): void {
            $this->db->table('users')->insert(['name' => 'level-1', 'email' => 'l1@x.io']);

            try {
                $this->db->transaction(function (): void {
                    $this->db->table('users')->insert(['name' => 'level-2', 'email' => 'l2@x.io']);
                    throw new \RuntimeException('rollback-inner');
                });
            } catch (\RuntimeException) {
                // انتظار
            }
        });

        $this->assertSame(1, $this->db->table('users')->where('email', 'l1@x.io')->count());
        $this->assertSame(0, $this->db->table('users')->where('email', 'l2@x.io')->count());
        $this->assertSame(0, $this->db->transactionLevel());
    }

    public function testTransactionRollbackOnException(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->db->table('users')->insert(['name' => 'gone', 'email' => 'gone@x.io']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // انتظار
        }

        $this->assertSame(0, $this->db->table('users')->count());
    }

    public function testTransactionEvents(): void
    {
        $this->db->transaction(static fn (): mixed => true);

        $this->assertTrue($this->events->hasRecorded('database.transaction.begin'));
        $this->assertTrue($this->events->hasRecorded('database.transaction.commit'));
    }

    // ─── T-06: Deadlock → retry ───────────────────────────────────────────

    public function testDeadlockRetry(): void
    {
        $flaky = new FlakyDeadlockConnection(failures: 2);
        $attempts = 0;

        $result = $flaky->transaction(function () use ($flaky, &$attempts): string {
            $attempts++;
            $flaky->statement('SELECT 1');   // دو بار اول → DeadlockException

            return 'ok';
        }, attempts: 3);

        $this->assertSame('ok', $result);
        $this->assertSame(3, $attempts);
        $this->assertSame(0, $flaky->transactionLevel());
    }

    // ─── T-07/T-08: Chunk / Lazy / Cursor ─────────────────────────────────

    public function testChunkWithStop(): void
    {
        $this->seedUsers(25);

        $seen = 0;
        $result = $this->db->table('users')->orderBy('id')->chunk(10, function (array $rows) use (&$seen): bool {
            $seen += \count($rows);

            return $seen < 20;   // توقف پس از دو دسته
        });

        $this->assertFalse($result);
        $this->assertSame(20, $seen);
    }

    public function testCursorAndLazy(): void
    {
        $this->seedUsers(50);

        $count = 0;
        foreach ($this->db->table('users')->orderBy('id')->cursor() as $row) {
            $this->assertArrayHasKey('name', $row);
            $count++;
        }
        $this->assertSame(50, $count);

        $lazyCount = 0;
        foreach ($this->db->table('users')->lazy(10) as $row) {
            $lazyCount++;
        }
        $this->assertSame(50, $lazyCount);

        // حافظهٔ cursor تقریباً ثابت می‌ماند
        $before = \memory_get_usage();
        $i = 0;
        foreach ($this->db->table('users')->cursor() as $row) {
            $i++;
        }
        $delta = \memory_get_usage() - $before;
        $this->assertTrue($delta < 2 * 1024 * 1024, 'cursor memory delta must stay small, got ' . $delta);
    }

    // ─── T-13/T-14: Query Log + Slow + N+1 ────────────────────────────────

    public function testQueryLogRecords(): void
    {
        $this->db->table('users')->insert(['name' => 'log', 'email' => 'log@x.io']);
        $log = $this->db->queryLog();

        $this->assertGreaterThan(0, \count($log));
        $last = $log[\count($log) - 1];
        $this->assertArrayHasKey('sql', $last);
        $this->assertArrayHasKey('time', $last);
        $this->assertSame('test', $last['connection']);
    }

    public function testSlowQueryLogged(): void
    {
        // slow_threshold = 0.0001ms → تقریباً هر کوئری «کند» است
        $this->db->table('users')->count();
        $this->assertTrue($this->logger->hasRecorded('database.slow_query'));
    }

    public function testNPlusOneDetection(): void
    {
        $this->seedUsers(6);

        for ($i = 1; $i <= 6; $i++) {
            $this->db->table('users')->where('id', $i)->first();
        }

        $this->assertTrue($this->events->hasRecorded('database.n_plus_one'));
    }

    public function testQueryLogMasksBindings(): void
    {
        // binding با کلید حساس (password) باید در لاگ ماسک شود
        $this->db->select('SELECT :password as pw', ['password' => 'super-secret-value-123']);

        $log = $this->db->queryLog();
        $last = $log[\count($log) - 1];

        $leaked = false;
        foreach ($last['bindings'] as $binding) {
            if (\is_string($binding) && \str_contains($binding, 'super-secret-value-123')) {
                $leaked = true;
            }
        }
        $this->assertFalse($leaked, 'sensitive bindings must be masked in query log');
    }

    // ─── T-15: Pagination ─────────────────────────────────────────────────

    public function testPagePagination(): void
    {
        $this->seedUsers(95);

        $paginator = $this->db->table('users')->orderBy('id')->paginate(perPage: 10, page: 3);

        $this->assertSame(95, $paginator->total());
        $this->assertSame(10, $paginator->lastPage());
        $this->assertSame(3, $paginator->currentPage());
        $this->assertCount(10, $paginator->items());
        $this->assertSame(21, $paginator->from());
        $this->assertSame(30, $paginator->to());
        $this->assertTrue($paginator->hasMorePages());

        $array = $paginator->toArray();
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('meta', $array);
        $this->assertArrayHasKey('links', $array);
        $this->assertSame(95, $array['meta']['total']);
    }

    public function testCursorPagination(): void
    {
        $this->seedUsers(25);

        $page1 = $this->db->table('users')->orderBy('id')->cursorPaginate(perPage: 10);
        $this->assertCount(10, $page1->items());
        $this->assertTrue($page1->hasMorePages());
        $this->assertNotNull($page1->nextCursor());

        $page2 = $this->db->table('users')->orderBy('id')->cursorPaginate(perPage: 10, cursor: $page1->nextCursor());
        $this->assertCount(10, $page2->items());

        // بدون تکرار/جاافتادن
        $ids1 = \array_column($page1->items(), 'id');
        $ids2 = \array_column($page2->items(), 'id');
        $this->assertSame([], \array_intersect($ids1, $ids2));

        $page3 = $this->db->table('users')->orderBy('id')->cursorPaginate(perPage: 10, cursor: $page2->nextCursor());
        $this->assertCount(5, $page3->items());
        $this->assertFalse($page3->hasMorePages());
    }

    public function testKeysetPagination(): void
    {
        $this->seedUsers(30);

        $page1 = $this->db->table('users')->keysetPaginate(perPage: 10);
        $this->assertCount(10, $page1->items());

        $lastId = (int) ($page1->items()[9]['id'] ?? 0);
        $page2 = $this->db->table('users')->keysetPaginate(perPage: 10, afterId: $lastId);
        $this->assertCount(10, $page2->items());
        $this->assertTrue((int) $page2->items()[0]['id'] > $lastId);
    }

    public function testMaxPerPageClamp(): void
    {
        $this->seedUsers(5);
        $paginator = $this->db->table('users')->paginate(perPage: 999999, page: -5);
        $this->assertSame(100, $paginator->perPage());
        $this->assertSame(1, $paginator->currentPage());
    }

    // ─── خطاها ────────────────────────────────────────────────────────────

    public function testQueryExceptionOnBadSql(): void
    {
        try {
            $this->db->select('SELECT * FROM nonexistent_table_xyz');
            self::fail('expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame('database.query_failed', $e->messageId);
            $this->assertStringContainsString('nonexistent_table_xyz', $e->sql);
        }
    }

    public function testMixedBindingsRejected(): void
    {
        $builder = $this->db->table('users')->whereRaw('name = :name', ['name' => 'x'])->where('age', 5);

        $this->expectException(\Tavaan\Engine\Database\Exceptions\MixedBindingException::class);
        $builder->getBindings();
    }

    // ─── Read/Write Splitting + sticky ────────────────────────────────────

    public function testReadWriteSplittingConfig(): void
    {
        $fake = new FakeConnection(driverName: 'mysql', config: [
            'read' => ['host' => ['replica1', 'replica2']],
            'write' => ['host' => 'master'],
            'sticky' => true,
        ]);

        $this->assertTrue($fake->hasReadReplica());

        $plain = new FakeConnection(driverName: 'mysql', config: []);
        $this->assertFalse($plain->hasReadReplica());
    }

    // ─── کمک‌ها ────────────────────────────────────────────────────────────

    private function seedUsers(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => 'user-' . $i, 'email' => 'user' . $i . '@x.io', 'age' => 20 + ($i % 40)];
        }
        foreach (\array_chunk($rows, 50) as $chunk) {
            $this->db->table('users')->insert($chunk);
        }
    }
}
