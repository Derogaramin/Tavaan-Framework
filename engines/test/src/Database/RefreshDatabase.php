<?php

declare(strict_types=1);

namespace Tavaan\Testing\Database;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Migration\Exceptions\MigrationLockedException;
use Tavaan\Engine\Database\Migration\MigrateResult;
use Tavaan\Engine\Database\Migration\Migrator;
use Tavaan\Testing\AssertionFailed;

/**
 * تَوان | Tavaan — Testing
 * RefreshDatabase — دیتابیس SQLite in-memory + migrate (DOC-084E §6 — AC-TEST-03).
 *
 * ```php
 * final class OrderTest extends TestCase
 * {
 *     use MakesHttpRequests;
 *     use RefreshDatabase;
 *
 *     protected function setUp(): void
 *     {
 *         $this->setUpDatabase();
 *     }
 * }
 * ```
 *
 * هر تست یک اپلیکیشن/اتصال تازه می‌گیرد → in-memory DB ایزوله و بدون I/O
 * واقعی (CON-11). اتصال پیش‌فرض به `sqlite_memory` (config/database.php)
 * تغییر می‌کند و همهٔ migrationهای پیش‌فرض اجرا می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
trait RefreshDatabase
{
    /**
     * اتصال پیش‌فرض را in-memory کرده و migrationها را اجرا می‌کند.
     */
    protected function setUpDatabase(): void
    {
        $app = $this->testingApp();
        $app->make('config')->set('database.default', 'sqlite_memory');

        $this->migrate();
    }

    /**
     * اجرای migrationها (مسیرهای پیش‌فرض: database/ + engines/*‌/migrations + …).
     *
     * در اجرای موازی (`--parallel`) ممکن است قفل فایل migration لحظه‌ای در دست
     * فرزند دیگری باشد → تلاش مجدد کوتاه (قطعی: حداکثر ۱۰ بار / ۲۰ms).
     *
     * @param list<string> $paths مسیرهای اضافی/جایگزین
     */
    protected function migrate(array $paths = []): MigrateResult
    {
        $migrator = $this->testingApp()->make(Migrator::class);
        if (!$migrator instanceof Migrator) {
            throw AssertionFailed::for('RefreshDatabase: Migrator is not bound in the test application.');
        }
        $migrator->setOutput(null);

        for ($attempt = 0;; $attempt++) {
            try {
                return $migrator->run($paths);
            } catch (MigrationLockedException $e) {
                if ($attempt >= 10) {
                    throw $e;
                }
                \usleep(20000);
            }
        }
    }

    /** اتصال SQLite in-memory جاری (برای ساخت جدول دستی/کوئری در تست). */
    protected function databaseConnection(): Connection
    {
        $manager = $this->testingApp()->make(ConnectionManager::class);

        return $manager->connection();
    }

    private function testingApp(): Application
    {
        if (\method_exists($this, 'app')) {
            $app = $this->app();
            if ($app instanceof Application) {
                return $app;
            }
        }
        if (isset($this->app) && $this->app instanceof Application) {
            return $this->app;
        }

        throw AssertionFailed::for(
            'RefreshDatabase needs a test application — use MakesHttpRequests together, '
            . 'or set $this->app = tavaan_test_app() in setUp().',
        );
    }
}
