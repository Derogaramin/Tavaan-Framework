<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Event\Outbox\DatabaseOutbox;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست Transactional Outbox — ADR-0009 / DOC-033 §5 (AC-EVT-05/07).
 */
final class OutboxTest extends TestCase
{
    private Application $app;

    private DatabaseOutbox $outbox;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);

        $migration = require \dirname(__DIR__, 4) . '/engines/event/migrations/2026_01_01_000000_create_outbox_tables.php';
        $migration->up();

        $this->outbox = new DatabaseOutbox($this->app->make(ConnectionInterface::class));
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    public function testStoreAndFetchPending(): void
    {
        $id = $this->outbox->store('shop.order.created', ['order_id' => 12, 'total' => 5000]);
        $this->assertSame(26, \strlen($id));
        $this->assertSame(1, $this->outbox->pendingCount());

        $batch = $this->outbox->fetchPending(10, 'worker-1');
        $this->assertCount(1, $batch);
        $this->assertSame('shop.order.created', $batch[0]['event_name']);
        $this->assertSame('processing', $batch[0]['status']);
        $this->assertSame('worker-1', $batch[0]['owner']);

        $decoded = \json_decode((string) $batch[0]['payload'], true);
        $this->assertSame(12, $decoded['order_id']);

        $this->outbox->markDone((int) $batch[0]['id']);
        $this->assertSame(0, $this->outbox->pendingCount());
    }

    public function testRollbackStoresNothing(): void
    {
        // AC-EVT-05: rollback → ۰ رویداد منتشر
        $db = $this->app->make(ConnectionInterface::class);

        try {
            $db->transaction(function () use ($db): void {
                $this->outbox->store('shop.order.created', ['order_id' => 1]);
                $db->statement('SELECT 1');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // انتظار
        }

        $this->assertSame(0, $this->outbox->pendingCount());
        $this->assertSame([], $this->outbox->fetchPending());
    }

    public function testCommitPersistsEvent(): void
    {
        $db = $this->app->make(ConnectionInterface::class);

        $db->transaction(function (): void {
            $this->outbox->store('shop.order.paid', ['order_id' => 2]);
        });

        $this->assertSame(1, $this->outbox->pendingCount());
    }

    public function testExclusiveClaimBetweenWorkers(): void
    {
        // AC-EVT-07: دو worker موازی → هر رویداد فقط یک‌بار برداشته می‌شود
        $this->outbox->store('e1', ['n' => 1]);
        $this->outbox->store('e2', ['n' => 2]);

        $w1 = $this->outbox->fetchPending(10, 'worker-A');
        $w2 = $this->outbox->fetchPending(10, 'worker-B');

        $this->assertCount(2, $w1);
        $this->assertCount(0, $w2, 'second worker must not claim processing rows');
    }

    public function testFailureBackoffAndMaxAttempts(): void
    {
        $this->outbox->store('flaky', []);

        [$row] = $this->outbox->fetchPending(10, 'w1');
        $this->outbox->markFailed((int) $row['id'], maxAttempts: 3);

        // backoff → فعلاً available نیست
        $this->assertSame([], $this->outbox->fetchPending(10, 'w2'));

        // پس از گذشت backoff → دوباره pending
        Clock::setTestNow(new \DateTimeImmutable('+5 seconds'));
        $again = $this->outbox->fetchPending(10, 'w2');
        $this->assertCount(1, $again);
        $this->assertSame(1, (int) $again[0]['attempts']);

        // دو شکست دیگر → failed دائمی
        $this->outbox->markFailed((int) $again[0]['id'], maxAttempts: 3);
        Clock::setTestNow(new \DateTimeImmutable('+30 seconds'));
        [$row3] = $this->outbox->fetchPending(10, 'w3');
        $this->outbox->markFailed((int) $row3['id'], maxAttempts: 3);

        Clock::setTestNow(new \DateTimeImmutable('+120 seconds'));
        $this->assertSame([], $this->outbox->fetchPending(10, 'w4'));

        $db = $this->app->make(ConnectionInterface::class);
        $failed = $db->table('tvn_outbox')->where('status', 'failed')->count();
        $this->assertSame(1, $failed);
        Clock::setTestNow(null);
    }

    public function testReleaseStaleClaims(): void
    {
        $this->outbox->store('stuck', []);
        $this->outbox->fetchPending(10, 'crashed-worker');

        // worker مرده — claim آزاد نمی‌شود تا releaseStale
        $this->assertSame([], $this->outbox->fetchPending(10, 'new-worker'));

        Clock::setTestNow(new \DateTimeImmutable('+301 seconds'));
        $released = $this->outbox->releaseStale(300);
        $this->assertSame(1, $released);

        $batch = $this->outbox->fetchPending(10, 'new-worker');
        $this->assertCount(1, $batch);
        Clock::setTestNow(null);
    }

    public function testRelayDispatchesToListeners(): void
    {
        $hits = [];
        $dispatcher = $this->app->make(DispatcherInterface::class);
        $dispatcher->listen('shop.order.created', function (string $event, array $payload) use (&$hits): void {
            $hits[] = $payload;
        });

        $this->outbox->store('shop.order.created', ['order_id' => 77]);

        // relay دستی (مانند OutboxRelayCommand)
        $batch = $this->outbox->fetchPending(50);
        foreach ($batch as $row) {
            $payload = \json_decode((string) $row['payload'], true) ?? [];
            $dispatcher->dispatch((string) $row['event_name'], $payload);
            $this->outbox->markDone((int) $row['id']);
        }

        $this->assertCount(1, $hits);
        $this->assertSame(77, $hits[0]['order_id']);
        $this->assertSame(0, $this->outbox->pendingCount());
    }
}
