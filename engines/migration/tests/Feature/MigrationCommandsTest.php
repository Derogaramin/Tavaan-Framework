<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Engine\Console\Input\ArgvInput;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Testing\TestCase;

/**
 * تست فرمان‌های migrate به‌صورت in-process روی SQLite in-memory —
 * پوشش MigrateCommand/StatusCommand/InstallCommand/RollbackCommand
 * (بدون لمس DB واقعی ریپو).
 */
final class MigrationCommandsTest extends TestCase
{
    private ?Application $appInstance = null;

    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-mig-cmd-' . \getmypid() . '-' . \bin2hex(\random_bytes(4));
        \mkdir($this->tmp, 0775, true);

        \file_put_contents($this->tmp . '/2026_01_01_000001_create_qa_tmp_things.php', <<<'PHP'
<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('qa_tmp_things')) {
            Schema::create('qa_tmp_things', function (Blueprint $t): void {
                $t->id();
                $t->string('title', 100);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_tmp_things');
    }
};
PHP);

        $app = $this->app();
        $app->make('config')->set('database.default', 'sqlite_memory');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
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

    private function tableExists(string $table): bool
    {
        /** @var ConnectionManager $manager */
        $manager = $this->app()->make(ConnectionManager::class);
        $rows = $manager->connection()->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
            [$table],
        );

        return $rows !== [];
    }

    public function testDryRunDoesNotExecute(): void
    {
        $result = $this->run('migrate', '--dry-run', '--path=' . $this->tmp);

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('dry-run', $result['out']);
        self::assertFalse($this->tableExists('qa_tmp_things'));
    }

    public function testMigrateCustomPathExecutesAndRollbackWithPath(): void
    {
        $result = $this->run('migrate', '--path=' . $this->tmp);

        self::assertSame(0, $result['code'], $result['out']);
        self::assertStringContainsString('1 migration', $result['out']);
        self::assertTrue($this->tableExists('qa_tmp_things'));

        // اجرای مجدد → بدون migration در صف
        $again = $this->run('migrate', '--path=' . $this->tmp);
        self::assertSame(0, $again['code'], $again['out']);
        self::assertStringContainsString('0 migration', $again['out']);

        // رگرسیون TECH-TOOLING-01: rollback با --path همان مسیر سفارشی
        $rollback = $this->run('migrate:rollback', '--force', '--path=' . $this->tmp);
        self::assertSame(0, $rollback['code'], \substr($rollback['out'], -300));
        self::assertFalse($this->tableExists('qa_tmp_things'));
    }

    public function testFullMigrateThenRollbackRemovesTables(): void
    {
        $full = $this->run('migrate');
        self::assertSame(0, $full['code'], \substr($full['out'], -300));
        self::assertTrue($this->tableExists('users'));

        // rollback آخرین batch (مسیرهای پیش‌فرض — فایل‌ها موجودند)
        $rollback = $this->run('migrate:rollback', '--force');
        self::assertSame(0, $rollback['code'], \substr($rollback['out'], -300));
        self::assertFalse($this->tableExists('users'));
    }

    public function testStatusReflectsRepositoryBeforeAndAfterFullMigrate(): void
    {
        // پیش از اجرا — migrationهای هسته Pending
        $before = $this->run('migrate:status');
        self::assertSame(0, $before['code'], $before['out']);
        self::assertStringContainsString('create_users_table', $before['out']);
        self::assertStringContainsString('Pending', $before['out']);

        // اجرای کامل (همهٔ مسیرهای پیش‌فرض روی in-memory)
        $full = $this->run('migrate');
        self::assertSame(0, $full['code'], \substr($full['out'], -300));
        self::assertTrue($this->tableExists('users'));

        // پس از اجرا — Ran
        $after = $this->run('migrate:status');
        self::assertSame(0, $after['code'], $after['out']);
        self::assertStringContainsString('Ran', $after['out']);
    }

    public function testMigrateInstallIsIdempotent(): void
    {
        $first = $this->run('migrate:install');
        self::assertSame(0, $first['code'], $first['out']);

        $second = $this->run('migrate:install');
        self::assertSame(0, $second['code'], $second['out']);
        self::assertTrue($this->tableExists('tvn_migrations'));
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
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }
}
