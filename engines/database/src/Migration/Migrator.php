<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration;

use Tavaan\Core\Contracts\Database\MigrationInterface;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\Migration\Exceptions\MigrationException;
use Tavaan\Engine\Database\Migration\Exceptions\MigrationLockedException;
use Tavaan\Engine\Database\Schema\SchemaBuilder;

/**
 * تَوان | Tavaan — Migration
 * Migrator — اجرای نسخه‌دار migrationها با Lock، Batch، Rollback و Dry-Run (DOC-049).
 *
 * 🔒 Lock از اجرای همزمان جلوگیری می‌کند (T-03).
 * 🔒 ترتیب اجرا = نام فایل (timestamp_نام)؛ rollback = ترتیب معکوس.
 * 🔒 migrate:fresh در production بدون --force رد می‌شود (T-06).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class Migrator
{
    private readonly MigrationRepository $repository;

    private ?FileMigrationLock $lock = null;

    /** @var \Closure(string): void|null */
    private $output = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schema,
        private readonly string $basePath,
        private readonly string $environment = 'production',
    ) {
        $this->repository = new MigrationRepository($connection);
    }

    /** @param \Closure(string): void $output */
    public function setOutput(?\Closure $output): void
    {
        $this->output = $output;
    }

    public function repository(): MigrationRepository
    {
        return $this->repository;
    }

    // ─── مسیرها ───────────────────────────────────────────────────────────

    /** @var list<string> مسیرهای افزوده (ماژول/تست) */
    private array $extraPaths = [];

    /** افزودن مسیر migration (ماژول‌ها/کیت‌ها در boot اضافه می‌کنند). */
    public function addPath(string $path): void
    {
        if (!\in_array($path, $this->extraPaths, true)) {
            $this->extraPaths[] = $path;
        }
    }

    /**
     * مسیرهای پیش‌فرض migration (هسته + موتورها + ماژول‌ها + مسیرهای افزوده).
     *
     * @return list<string>
     */
    public function defaultPaths(): array
    {
        $paths = [];

        if (\is_dir($this->basePath . '/database/migrations')) {
            $paths[] = $this->basePath . '/database/migrations';
        }
        foreach (\glob($this->basePath . '/engines/*/migrations', GLOB_ONLYDIR) ?: [] as $dir) {
            $paths[] = $dir;
        }
        foreach (\glob($this->basePath . '/modules/*/migrations', GLOB_ONLYDIR) ?: [] as $dir) {
            $paths[] = $dir;
        }
        foreach ($this->extraPaths as $path) {
            $paths[] = $path;
        }

        return \array_values(\array_unique($paths));
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, string> نام migration → مسیر فایل
     */
    public function getMigrationFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            foreach (\glob($path . '/*.php') ?: [] as $file) {
                $name = \basename($file, '.php');
                $files[$name] = $file;
            }
        }
        \ksort($files);   // 🔒 ترتیب زمانی قطعی

        return $files;
    }

    // ─── عملیات ───────────────────────────────────────────────────────────

    /**
     * اجرای pendingها.
     *
     * @param list<string>         $paths
     * @param array<string, mixed> $options dry_run|module|force
     */
    public function run(array $paths = [], array $options = []): MigrateResult
    {
        $paths = $paths === [] ? $this->defaultPaths() : $paths;
        $moduleFilter = isset($options['module']) ? (string) $options['module'] : null;

        $this->acquireLock();
        try {
            $this->repository->createRepository();

            $ran = $this->repository->getRan();
            $files = $this->getMigrationFiles($paths);

            $pending = \array_values(\array_diff(\array_keys($files), $ran));
            \sort($pending);

            if ($moduleFilter !== null) {
                $pending = \array_values(\array_filter(
                    $pending,
                    fn (string $name): bool => $this->moduleOf($files[$name]) === $moduleFilter,
                ));
            }

            if ($pending === []) {
                $this->write('هیچ migration بازی باقی نمانده است.');

                return MigrateResult::nothing();
            }

            if ((bool) ($options['dry_run'] ?? false)) {
                foreach ($pending as $name) {
                    $this->write('[dry-run] ' . $name);
                }

                return MigrateResult::dryRun($pending);
            }

            $batch = $this->repository->getNextBatch();
            foreach ($pending as $name) {
                $migration = $this->resolve($files[$name]);
                $this->runMethod($migration, 'up', $name);
                $this->repository->log($name, $batch, $this->moduleOf($files[$name]));
                $this->write('✓ ' . $name);
            }

            return MigrateResult::success($pending, $batch);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * بازگرداندن آخرین batch.
     *
     * @param list<string> $paths مسیرهای جست‌وجوی فایل migration (پیش‌فرض: defaultPaths —
     *                            برای migrationهای اجراشده با `--path` سفارشی، همان مسیر
     *                            باید اینجا هم داده شود وگرنه فایل یافت نمی‌شود)
     */
    public function rollback(int $steps = 1, array $paths = []): MigrateResult
    {
        $this->acquireLock();
        try {
            $this->repository->createRepository();

            $lastBatch = $this->repository->getLastBatch($steps);
            if ($lastBatch === []) {
                $this->write('چیزی برای rollback نیست.');

                return MigrateResult::nothing();
            }

            $files = $this->getMigrationFiles($paths === [] ? $this->defaultPaths() : $paths);
            $rolledBack = [];

            foreach ($lastBatch as $name) {   // 🔒 ترتیب معکوس (getLastBatch با order desc)
                if (!isset($files[$name])) {
                    throw new MigrationException(
                        'migration.file_missing',
                        ['migration' => $name],
                        'فایل migration برای rollback یافت نشد.',
                    );
                }

                $migration = $this->resolve($files[$name]);
                $this->runMethod($migration, 'down', $name);
                $this->repository->delete($name);
                $rolledBack[] = $name;
                $this->write('↩ ' . $name);
            }

            return MigrateResult::success($rolledBack, 0);
        } finally {
            $this->releaseLock();
        }
    }

    public function refresh(int $steps = 0): MigrateResult
    {
        $this->rollback($steps > 0 ? $steps : \PHP_INT_MAX);

        return $this->run();
    }

    /**
     * 🔒 drop همهٔ جداول + migrate از صفر — در production فقط با force.
     */
    public function fresh(bool $force = false): MigrateResult
    {
        if ($this->environment === 'production' && !$force) {
            throw new MigrationException(
                'migration.fresh_forbidden',
                ['environment' => $this->environment],
                'migrate:fresh در production بدون --force ممنوع است.',
            );
        }

        $this->acquireLock();
        try {
            $this->schema->dropAllTables();
            $this->write('همهٔ جداول حذف شدند.');
        } finally {
            $this->releaseLock();
        }

        return $this->run();
    }

    /**
     * @param list<string> $paths مسیرهای بررسی (خالی = پیش‌فرض)
     *
     * @return list<array<string, mixed>> وضعیت (pending/ran) همهٔ migrationها
     */
    public function status(array $paths = []): array
    {
        $this->repository->createRepository();

        $ran = [];
        foreach ($this->repository->all() as $row) {
            $ran[(string) $row['migration']] = $row;
        }

        $files = $this->getMigrationFiles($paths === [] ? $this->defaultPaths() : $paths);
        $status = [];
        foreach ($files as $name => $path) {
            $status[] = [
                'migration' => $name,
                'ran'       => isset($ran[$name]),
                'batch'     => isset($ran[$name]) ? (int) $ran[$name]['batch'] : null,
                'module'    => $this->moduleOf($path),
            ];
        }

        return $status;
    }

    public function install(): void
    {
        $this->repository->createRepository();
        $this->write('جدول tvn_migrations آماده است.');
    }

    // ─── درونی ────────────────────────────────────────────────────────────

    private function resolve(string $file): MigrationInterface
    {
        $result = require $file;

        if ($result instanceof MigrationInterface) {
            return $result;
        }

        if (\is_string($result) && \class_exists($result)) {
            $instance = new $result();
            if ($instance instanceof MigrationInterface) {
                return $instance;
            }
        }

        throw new MigrationException(
            'migration.invalid_file',
            ['file' => \basename($file)],
            'فایل migration باید نمونهٔ Migration برگرداند (return new class extends Migration).',
        );
    }

    private function runMethod(MigrationInterface $migration, string $method, string $name): void
    {
        if ($migration instanceof Migration) {
            $migration->module = $this->moduleOf($name);
        }

        try {
            $migration->{$method}();
        } catch (\Throwable $e) {
            throw new MigrationException(
                'migration.failed',
                ['migration' => $name, 'method' => $method, 'error' => $e->getMessage()],
                'اجرای migration با خطا مواجه شد.',
                $e,
            );
        }
    }

    /** ماژول/موتور مالک از مسیر فایل. */
    private function moduleOf(string $path): ?string
    {
        if (\preg_match('#/modules/([A-Za-z0-9_-]+)/migrations#', $path, $m) === 1) {
            return $m[1];
        }
        if (\preg_match('#/engines/([A-Za-z0-9_-]+)/migrations#', $path, $m) === 1) {
            return 'engine:' . $m[1];
        }

        return null;
    }

    private function acquireLock(): void
    {
        $lockPath = $this->basePath . '/storage/framework/migration.lock';
        $lock = new FileMigrationLock($lockPath, seconds: 300, ownerId: \getmypid() . ':' . \spl_object_id($this));

        if (!$lock->acquire()) {
            throw new MigrationLockedException();
        }

        $this->lock = $lock;
    }

    private function releaseLock(): void
    {
        $this->lock?->release();
        $this->lock = null;
    }

    private function write(string $line): void
    {
        if ($this->output !== null) {
            ($this->output)($line);
        }
    }
}
