<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\Migration\Exceptions\MigrationException;
use Tavaan\Engine\Database\Migration\Exceptions\MigrationLockedException;
use Tavaan\Engine\Database\Migration\FileMigrationLock;
use Tavaan\Engine\Database\Migration\Migrator;
use Tavaan\Testing\TestCase;

/**
 * تست Migrator — DOC-049 §8 (T-01..T-07).
 */
final class MigratorTest extends TestCase
{
    private Application $app;

    private Migrator $migrator;

    private string $fixtures;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);

        $this->fixtures = \dirname(__DIR__) . '/Fixtures';
        $this->migrator = $this->app->make(Migrator::class);
        // مسیر fixture برای rollback/status (اجراها با مسیر صریح انجام می‌شوند)
        $this->migrator->addPath($this->fixtures);

        @\unlink($this->app->basePath() . '/storage/framework/migration.lock');
    }

    protected function tearDown(): void
    {
        @\unlink($this->app->basePath() . '/storage/framework/migration.lock');
    }

    private function db(): Connection
    {
        $connection = $this->app->make('db')->connection();
        $this->assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    public function testMigrateRunsPendingAndLogs(): void
    {
        // T-01
        $result = $this->migrator->run([$this->fixtures]);

        $this->assertSame(3, $result->count());
        $this->assertSame(1, $result->batch);
        $this->assertTrue($this->app->make('schema')->hasTable('widgets'));
        $this->assertTrue($this->app->make('schema')->hasTable('widget_parts'));

        $ran = $this->migrator->repository()->getRan();
        $this->assertContains('2026_01_01_000001_create_widgets_table', $ran);
        $this->assertContains('2026_01_01_000003_backfill_widget_prices', $ran);

        // اجرای دوم → هیچ pending
        $again = $this->migrator->run([$this->fixtures]);
        $this->assertSame(0, $again->count());
    }

    public function testRollbackReverseOrder(): void
    {
        // T-02
        $this->migrator->run([$this->fixtures]);
        $result = $this->migrator->rollback(1);

        $this->assertSame(3, $result->count());
        // ترتیب معکوس: اول backfill (id=3)، بعد parts، بعد widgets
        $this->assertSame('2026_01_01_000003_backfill_widget_prices', $result->migrations[0]);
        $this->assertSame('2026_01_01_000001_create_widgets_table', $result->migrations[2]);

        $this->assertFalse($this->app->make('schema')->hasTable('widgets'));
        $this->assertFalse($this->app->make('schema')->hasTable('widget_parts'));
        $this->assertSame([], $this->migrator->repository()->getRan());
    }

    public function testLockPreventsConcurrent(): void
    {
        // T-03
        $lockPath = $this->app->basePath() . '/storage/framework/migration.lock';
        $holder = new FileMigrationLock($lockPath, seconds: 300, ownerId: 'other-process');
        $this->assertTrue($holder->acquire());

        try {
            $this->migrator->run([$this->fixtures]);
            self::fail('expected MigrationLockedException');
        } catch (MigrationLockedException $e) {
            $this->assertSame('migration.locked', $e->messageId);
        } finally {
            $holder->release();
        }

        // پس از آزادسازی، اجرا می‌شود
        $result = $this->migrator->run([$this->fixtures]);
        $this->assertSame(3, $result->count());
    }

    public function testDryRunChangesNothing(): void
    {
        // T-04
        $result = $this->migrator->run([$this->fixtures], ['dry_run' => true]);

        $this->assertTrue($result->dryRun);
        $this->assertSame(3, $result->count());
        $this->assertFalse($this->app->make('schema')->hasTable('widgets'));
        $this->assertSame([], $this->migrator->repository()->getRan());
    }

    public function testBatchNumbering(): void
    {
        // T-05
        $this->migrator->run([$this->fixtures]);
        $this->assertSame(1, $this->migrator->repository()->getLastBatchNumber());

        // migration جدید در fixture دوم → batch 2
        $extra = \sys_get_temp_dir() . '/tvn-mig-extra-' . \getmypid();
        if (!\is_dir($extra)) {
            \mkdir($extra, 0775, true);
        }
        \file_put_contents($extra . '/2026_01_02_000001_create_extras_table.php', <<<'PHP'
<?php
declare(strict_types=1);
use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;
return new class extends Migration {
    public function up(): void { Schema::create('extras', fn (Blueprint $t) => $t->id()); }
    public function down(): void { Schema::dropIfExists('extras'); }
};
PHP);

        $this->migrator->addPath($extra);   // برای lookup در rollback
        try {
            $result = $this->migrator->run([$this->fixtures, $extra]);
            $this->assertSame(2, $result->batch);
            $this->assertSame(2, $this->migrator->repository()->getLastBatchNumber());

            // rollback یک batch → فقط extras
            $rolled = $this->migrator->rollback(1);
            $this->assertSame(1, $rolled->count());
            $this->assertFalse($this->app->make('schema')->hasTable('extras'));
            $this->assertTrue($this->app->make('schema')->hasTable('widgets'));
        } finally {
            @\unlink($extra . '/2026_01_02_000001_create_extras_table.php');
            @\rmdir($extra);
        }
    }

    public function testFreshRefusedInProductionWithoutForce(): void
    {
        // T-06
        $prodMigrator = new Migrator($this->db(), $this->app->make('schema'), $this->app->basePath(), environment: 'production');

        try {
            $prodMigrator->fresh(force: false);
            self::fail('expected MigrationException');
        } catch (MigrationException $e) {
            $this->assertSame('migration.fresh_forbidden', $e->messageId);
        }

        // با force در محیط غیرproduction مجاز است
        $devMigrator = new Migrator($this->db(), $this->app->make('schema'), $this->app->basePath(), environment: 'testing');
        $devMigrator->addPath($this->fixtures);
        $this->migrator->run([$this->fixtures]);
        $result = $devMigrator->fresh(force: false);
        $this->assertGreaterThan(0, $result->count());
    }

    public function testDataMigrationIdempotent(): void
    {
        // T-07: مهاجرت داده‌ای قابل تکرار + chunked
        $this->migrator->run([$this->fixtures]);

        $this->db()->table('widgets')->insert([
            ['name' => 'w1', 'price' => 10],
            ['name' => 'w2', 'price' => 10],
        ]);

        // اجرای دستی مجدد backfill → قیمت‌ها تغییر نکنند (idempotent)
        $backfill = require $this->fixtures . '/2026_01_01_000003_backfill_widget_prices.php';
        $backfill->up();
        $backfill->up();   // دو بار

        $this->assertSame(10, (int) $this->db()->table('widgets')->where('name', 'w1')->value('price'));
        $this->assertSame(2, $this->db()->table('widgets')->count());
    }

    public function testStatus(): void
    {
        $status = $this->migrator->status([$this->fixtures]);
        $this->assertCount(3, $status);
        foreach ($status as $item) {
            $this->assertFalse($item['ran']);
        }

        $this->migrator->run([$this->fixtures]);
        $status = $this->migrator->status([$this->fixtures]);
        foreach ($status as $item) {
            $this->assertTrue($item['ran']);
            $this->assertSame(1, $item['batch']);
        }
    }
}
