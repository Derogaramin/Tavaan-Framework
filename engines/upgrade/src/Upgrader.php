<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Migration\Migrator;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;
use Tavaan\Engine\Upgrade\Support\BackupRunnerInterface;

/**
 * تَوان | Tavaan — Upgrade
 * Upgrader — ارکستراتور جریان ارتقا (DOC-081E §2).
 *
 * ```
 * pre-check → backup (اجباری) → maintenance(down) → مسیر گام‌به‌گام →
 *   [schema migrations → data patches → config migrations → cache clear] × هر نسخه
 * → setVersion → verify(health) → up
 * در شکست: rollback خودکار از backup + up
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class Upgrader
{
    private readonly UpgradeRepository $repository;

    private readonly VersionResolver $versions;

    private readonly PatchRunner $patches;

    private readonly PreChecker $preChecker;

    /** @var \Closure(string): void|null */
    private ?\Closure $output = null;

    /**
     * @param array<string, mixed> $config کلیدهای upgrade.*
     */
    public function __construct(
        private readonly ContainerInterface $app,
        private readonly array $config,
        private readonly ?BackupRunnerInterface $backupRunner = null,
    ) {
        $connection = $app->make(ConnectionManager::class)->connection();
        $this->repository = new UpgradeRepository($connection, $app->make(SchemaBuilder::class));
        $this->versions = new VersionResolver($this->upgradesPath());
        // envFile از upgradesPath مشتق می‌شود (prod: base/.env؛ سفارشی/تست: کنار ریشهٔ upgrades)
        $this->patches = new PatchRunner($this->upgradesPath(), $this->repository);
        $this->preChecker = new PreChecker();
    }

    /** @param \Closure(string): void $output */
    public function setOutput(?\Closure $output): void
    {
        $this->output = $output;
    }

    /** دسترسی به repository (وضعیت/meta — برای تست و ابزارها). */
    public function repository(): UpgradeRepository
    {
        return $this->repository;
    }

    // ─── وضعیت ────────────────────────────────────────────────────────────

    /** نسخهٔ جاری نصب‌شده (meta → TVN_VERSION → config app.version). */
    public function currentVersion(): string
    {
        $stored = $this->repository->getMeta('current_version');
        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        $candidates = [];
        if (\defined('TVN_VERSION')) {
            $candidates[] = (string) TVN_VERSION;
        }
        if ($this->app->bound('config')) {
            $candidates[] = (string) $this->app->make('config')->get('app.version', '');
        }

        foreach ($candidates as $candidate) {
            if (VersionResolver::isSemver($candidate)) {
                return $candidate;
            }
        }

        return '0.0.0';
    }

    /**
     * گزارش وضعیت: نسخهٔ جاری، نسخه‌های موجود، مسیر تا هدف، آخرین backup.
     *
     * @return array<string, mixed>
     */
    public function status(?string $to = null): array
    {
        $current = $this->currentVersion();
        $available = $this->versions->availableVersions();

        $path = null;
        if ($to !== null) {
            try {
                $path = $this->versions->upgradePath($current, $to);
            } catch (UpgradeException $e) {
                $path = ['error' => $e->messageId];
            }
        }

        return [
            'current' => $current,
            'available' => $available,
            'next' => $this->versions->nextVersion($current),
            'path_to' => $to,
            'path' => $path,
            'last_backup' => $this->repository->getMeta('last_backup'),
            'previous_version' => $this->repository->getMeta('previous_version'),
        ];
    }

    /**
     * فهرست همهٔ patchها با وضعیت applied/pending.
     *
     * @return list<array{version: string, name: string, applied: bool}>
     */
    public function patches(): array
    {
        $all = [];
        foreach ($this->versions->availableVersions() as $version) {
            foreach ($this->patches->discover($version) as $entry) {
                $all[] = ['version' => $version, 'name' => $entry['name'], 'applied' => $entry['applied']];
            }
        }

        return $all;
    }

    /**
     * بررسی سازگاری بدون اجرا (upgrade:check).
     *
     * @return array{ok: bool, problems: list<string>, php: string, path: list<string>|null}
     */
    public function check(?string $to = null): array
    {
        $problems = $this->preChecker->check((array) ($this->config['precheck'] ?? []));

        $path = null;
        if ($to !== null) {
            try {
                $path = $this->versions->upgradePath($this->currentVersion(), $to);
            } catch (UpgradeException $e) {
                $problems[] = $e->messageId . ': ' . \json_encode($e->context, JSON_UNESCAPED_UNICODE);
            }
        }

        return ['ok' => $problems === [], 'problems' => $problems, 'php' => PHP_VERSION, 'path' => $path];
    }

    // ─── ارتقا ────────────────────────────────────────────────────────────

    /**
     * ارتقای کامل به نسخهٔ هدف.
     *
     * @param array<string, mixed> $options no_backup|skip_maintenance
     *
     * @throws UpgradeException
     */
    public function upgrade(string $to, array $options = []): string
    {
        // ۱) pre-check
        $problems = $this->preChecker->check((array) ($this->config['precheck'] ?? []));
        if ($problems !== []) {
            throw UpgradeException::precheckFailed($problems);
        }

        $from = $this->currentVersion();
        $path = $this->versions->upgradePath($from, $to);
        $this->write('مسیر ارتقا: ' . $from . ' → ' . \implode(' → ', $path));

        // ۲) backup اجباری 🔒 (§8)
        $backupId = null;
        if (!($options['no_backup'] ?? false)) {
            $runner = $this->backupRunner();
            $backupId = $runner->run();
            $this->repository->setMeta('last_backup', $backupId);
            $this->repository->setMeta('previous_version', $from);
            $this->write('backup ساخته شد: ' . $backupId);
        } elseif ((bool) ($this->config['require_backup'] ?? true)) {
            throw UpgradeException::backupFailed('require_backup فعال است اما --no-backup درخواست شده');
        }

        // ۳) maintenance mode
        $maintenance = !($options['skip_maintenance'] ?? false)
            && (bool) ($this->config['maintenance']['enabled'] ?? true);
        if ($maintenance) {
            $this->down();
        }

        try {
            $batch = $this->repository->nextBatch();

            // ۴-۵) هر گام نسخه
            foreach ($path as $version) {
                $this->write('── گام ' . $version);

                if ((bool) ($this->config['schema_migrations'] ?? true)) {
                    $this->runSchemaMigrations();
                }

                $executed = $this->patches->run($version, $batch);
                foreach ($executed as $patchName) {
                    $this->write('   ✓ patch: ' . $patchName);
                }

                $this->clearCaches();
            }

            // ثبت نسخهٔ نهایی
            $this->repository->setMeta('current_version', $to);

            // ۶) verify (health check)
            $this->verify();
        } catch (\Throwable $e) {
            // شکست میانی → rollback خودکار از backup
            $this->write('✗ ارتقا شکست خورد: ' . $e->getMessage() . ' — در حال rollback…');
            if ($backupId !== null) {
                try {
                    $this->backupRunner()->restore($backupId);
                    $this->repository->setMeta('current_version', $from);
                } catch (\Throwable $restoreError) {
                    $this->write('✗ rollback هم شکست خورد: ' . $restoreError->getMessage());
                }
            }
            if ($maintenance) {
                $this->up();
            }

            throw $e instanceof UpgradeException ? $e : UpgradeException::patchFailed($to, '-', $e->getMessage(), $e);
        }

        // ۷) up
        if ($maintenance) {
            $this->up();
        }
        $this->write('✓ ارتقا به ' . $to . ' کامل شد.');

        return $to;
    }

    /**
     * بازگشت به آخرین backup (DOC-081E §6).
     *
     * @throws UpgradeException بدون backup
     */
    public function rollback(): string
    {
        $backupId = $this->repository->getMeta('last_backup');
        if ($backupId === null || $backupId === '') {
            throw UpgradeException::noBackup();
        }

        $this->backupRunner()->restore($backupId);
        $this->clearCaches();

        $previous = $this->repository->getMeta('previous_version');
        if ($previous !== null && $previous !== '') {
            $this->repository->setMeta('current_version', $previous);
        }

        return $previous ?? '(نامعلوم)';
    }

    // ─── maintenance (§2 گام ۳) ───────────────────────────────────────────

    public function down(): void
    {
        $file = $this->downFile();
        $dir = \dirname($file);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }
        \file_put_contents($file, (string) \json_encode([
            'message' => (string) ($this->config['maintenance']['message'] ?? 'Upgrading…'),
            'since' => \date('c'),
            'by' => 'upgrade',
        ]));
    }

    public function up(): void
    {
        $file = $this->downFile();
        if (\is_file($file)) {
            \unlink($file);
        }
    }

    public function isDown(): bool
    {
        return \is_file($this->downFile());
    }

    // ─── internals ────────────────────────────────────────────────────────

    /** health check ساده پس از ارتقا: اتصال DB + خواندن نسخه. */
    private function verify(): void
    {
        try {
            /** @var Connection $connection */
            $connection = $this->app->make(ConnectionManager::class)->connection();
            $connection->selectOne('SELECT 1 AS ok');
        } catch (\Throwable $e) {
            throw UpgradeException::verifyFailed($e->getMessage());
        }

        if (!VersionResolver::isSemver((string) $this->repository->getMeta('current_version', ''))) {
            throw UpgradeException::verifyFailed('نسخهٔ ثبت‌شده معتبر نیست');
        }
    }

    private function runSchemaMigrations(): void
    {
        if (!$this->app->bound(Migrator::class)) {
            return;
        }
        $migrator = $this->app->make(Migrator::class);
        if (!$migrator instanceof Migrator) {
            return;
        }
        $migrator->setOutput(null);
        $result = $migrator->run();
        if ($result->migrations !== []) {
            $this->write('   ✓ schema: ' . \implode('، ', $result->migrations));
        }
    }

    /** پاک‌سازی cacheهای زمان اجرا (config/route/view/modules). */
    private function clearCaches(): void
    {
        $storage = $this->basePath() . '/storage';
        $patterns = [
            $storage . '/cache/config.php',
            $storage . '/cache/routes.php',
            $storage . '/cache/modules.php',
            $storage . '/cache/autoload.php',
        ];
        foreach ($patterns as $file) {
            if (\is_file($file)) {
                @\unlink($file);
            }
        }
        foreach (\glob($storage . '/framework/views/*.php') ?: [] as $compiled) {
            @\unlink($compiled);
        }
    }

    private function backupRunner(): BackupRunnerInterface
    {
        if ($this->backupRunner !== null) {
            return $this->backupRunner;
        }
        if ($this->app->bound(BackupRunnerInterface::class)) {
            $runner = $this->app->make(BackupRunnerInterface::class);
            if ($runner instanceof BackupRunnerInterface) {
                return $runner;
            }
        }

        throw UpgradeException::backupFailed('BackupRunner در دسترس نیست (موتور backup غیرفعال؟)');
    }

    private function upgradesPath(): string
    {
        $path = (string) ($this->config['path'] ?? '');

        return $path !== '' ? \rtrim($path, '/\\') : $this->basePath() . '/upgrades';
    }

    private function downFile(): string
    {
        $file = (string) ($this->config['down_file'] ?? '');

        return $file !== '' ? $file : $this->basePath() . '/storage/framework/down';
    }

    private function basePath(): string
    {
        return \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
    }

    private function write(string $line): void
    {
        if ($this->output !== null) {
            ($this->output)($line);
        }
    }
}
