<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Contracts\Filesystem\FilesystemInterface;
use Tavaan\Engine\Backup\Exceptions\BackupException;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Backup
 * BackupManager — پشتیبان‌گیری رمزنگاری‌شده از DB و فایل‌ها (Facade: Backup؛ DOC-068).
 *
 * جریان (DOC-068 §3):
 * ```
 * DB Dump (خالص، chunked) → فایل‌ها → ZIP (ZipWriterPure) →
 * رمزنگاری (AES-256-GCM، کلید جدا) → SHA-256 → ذخیره روی disk → Manifest
 * ```
 *
 * 🔒 restore نیازمند `--force` + maintenance mode است (DOC-068 §8).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupManager
{
    private readonly BackupEncrypter $encrypter;

    private readonly DatabaseDumper $dumper;

    private readonly DatabaseRestorer $restorer;

    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?ContainerInterface $container = null,
        private readonly ?ConnectionInterface $db = null,
        private readonly ?FilesystemInterface $disk = null,
        ?EncrypterInterface $crypt = null,
    ) {
        $this->encrypter = new BackupEncrypter(
            (string) ($config?->get('backup.key', '') ?? ''),
            $crypt,
        );
        $this->dumper = new DatabaseDumper();
        $this->restorer = new DatabaseRestorer();
    }

    // ─── API (DOC-068 §2) ─────────────────────────────────────────────────

    /** پشتیبان کامل (DB + فایل‌ها). */
    public function run(?string $type = 'full', ?array $files = null): Manifest
    {
        $tmpDir = \sys_get_temp_dir() . '/tvn-backup-' . \bin2hex(\random_bytes(6));
        \mkdir($tmpDir, 0700, true);

        try {
            $id = 'bkp_' . (string) Ulid::generate();
            $createdAt = Clock::isoNow();

            $zip = new \Tavaan\Engine\Compat\ZipWriterPure();
            $withDatabase = \in_array($type, ['full', 'database'], true);
            $withFiles = \in_array($type, ['full', 'files'], true);

            // 1) DB Dump — 🔒 chunked با حافظهٔ ثابت (DOC-068 §4)
            $dumpPath = null;
            if ($withDatabase && $this->db !== null) {
                $dumpPath = $tmpDir . '/database.sql';
                $this->dumper->dumpToFile($this->db, $dumpPath);
                $zip->addFile($dumpPath, 'database.sql');
            }

            // 2) فایل‌ها
            $fileMap = [];
            if ($withFiles) {
                $paths = $files ?? (array) ($this->config?->get('backup.include_files', []) ?? []);
                $excludes = (array) ($this->config?->get('backup.exclude', []) ?? []);
                $fileMap = $this->collectFiles($zip, $paths, $excludes);
            }

            // manifest داخلی (با filemap برای restore)
            $inner = [
                'id' => $id,
                'version' => '1.0.0',
                'type' => $type,
                'created_at' => $createdAt,
                'contents' => ['database' => $dumpPath !== null, 'files' => \array_keys($fileMap)],
                'filemap' => $fileMap,
                'encrypted' => (bool) ($this->config?->get('backup.encrypt', true) ?? true),
                'tavaan_version' => (string) ($this->config?->get('app.version', '') ?? ''),
                'php_version' => \PHP_VERSION,
            ];
            $zip->addFromString('manifest.json', (string) \json_encode($inner, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            // 3) ZIP → 4) رمزنگاری → 5) hash
            $zipBytes = $zip->output();
            $encrypted = (bool) ($this->config?->get('backup.encrypt', true) ?? true);
            $blob = $encrypted ? $this->encrypter->encrypt($zipBytes) : $zipBytes;
            $hash = 'sha256:' . \hash('sha256', $blob);

            // 6) ذخیره روی disk
            $manifest = new Manifest(
                id: $id,
                type: $type,
                createdAt: $createdAt,
                size: \strlen($blob),
                hash: $hash,
                contents: ['database' => $dumpPath !== null, 'files' => \array_keys($fileMap)],
                encrypted: $encrypted,
                tavaanVersion: (string) ($inner['tavaan_version']),
            );

            $disk = $this->diskOrFail();
            if (!$disk->put($manifest->archiveName(), $blob)) {
                throw BackupException::storeFailed('archive write');
            }
            $disk->put($manifest->manifestName(), $manifest->toJson());

            // 7) verify پس از ساخت (DOC-068 §7: verify_after)
            if ((bool) ($this->config?->get('backup.verify_after', true) ?? true)) {
                $this->verify($id);
            }

            return $manifest;
        } finally {
            $this->rrmdir($tmpDir);
        }
    }

    /** فقط دیتابیس. */
    public function database(): Manifest
    {
        return $this->run('database');
    }

    /**
     * فقط فایل‌ها.
     *
     * @param list<string> $paths
     */
    public function files(array $paths): Manifest
    {
        return $this->run('files', $paths);
    }

    /** @return list<Manifest> */
    public function list(): array
    {
        $disk = $this->diskOrFail();
        $manifests = [];

        foreach ($disk->allFiles() as $file) {
            if (!\str_ends_with($file, '.json')) {
                continue;
            }
            $content = $disk->get($file);
            if ($content === null) {
                continue;
            }
            $manifest = Manifest::fromJson($content);
            if ($manifest !== null) {
                $manifests[] = $manifest;
            }
        }

        \usort($manifests, static fn (Manifest $a, Manifest $b): int => \strcmp((string) $b->createdAt, (string) $a->createdAt));

        return $manifests;
    }

    public function find(string $id): ?Manifest
    {
        $content = $this->diskOrFail()->get($id . '.json');

        return $content === null ? null : Manifest::fromJson($content);
    }

    /**
     * بررسی یکپارچگی: hash + decrypt test (DOC-068 §2).
     *
     * @throws BackupException
     */
    public function verify(string $id): bool
    {
        $manifest = $this->find($id) ?? throw BackupException::notFound($id);
        $disk = $this->diskOrFail();

        $blob = $disk->get($manifest->archiveName());
        if ($blob === null) {
            throw BackupException::notFound($id . ' (archive)');
        }

        // hash
        $expected = $manifest->hash;
        $actual = 'sha256:' . \hash('sha256', $blob);
        if ($expected !== '' && !\hash_equals($expected, $actual)) {
            throw BackupException::verifyFailed($id, 'hash mismatch — فایل دستکاری شده است');
        }

        // decrypt test
        if ($manifest->encrypted) {
            try {
                $this->encrypter->decrypt($blob);
            } catch (\Throwable $e) {
                throw BackupException::decryptFailed($id, $e->getMessage());
            }
        }

        return true;
    }

    /**
     * بازیابی — 🔒 مخرب؛ نیازمند force + maintenance mode (DOC-068 §8).
     *
     * @param list<string>|null $onlyFiles فقط این مسیرهای ذخیره‌شده (null = همه)
     */
    public function restore(string $id, bool $force = false, ?array $onlyFiles = null): void
    {
        if (!$force) {
            throw BackupException::forceRequired();
        }

        $manifest = $this->find($id) ?? throw BackupException::notFound($id);
        $this->verify($id);

        $disk = $this->diskOrFail();
        $blob = (string) $disk->get($manifest->archiveName());
        $zipBytes = $manifest->encrypted ? $this->encrypter->decrypt($blob) : $blob;

        $maintenance = $this->maintenanceFile();
        $this->enableMaintenance($maintenance);

        try {
            $zip = ZipReader::openString($zipBytes);

            try {
                $innerRaw = $zip->extract('manifest.json');
                $inner = \is_string($innerRaw) ? (\json_decode($innerRaw, true) ?: []) : [];
                $fileMap = (array) ($inner['filemap'] ?? []);

                // 1) دیتابیس
                if ($zip->has('database.sql') && $this->db !== null) {
                    $sql = (string) $zip->extract('database.sql');
                    $this->restorer->restoreFromString($this->db, $sql);
                }

                // 2) فایل‌ها → مسیر اصلی
                foreach ($zip->entryNames() as $entry) {
                    if (!\str_starts_with($entry, 'files/')) {
                        continue;
                    }
                    $original = (string) ($fileMap[$entry] ?? '');
                    if ($original === '' || $onlyFiles !== null && !\in_array($entry, $onlyFiles, true)) {
                        continue;
                    }

                    $this->restoreFile($original, (string) $zip->extract($entry));
                }
            } finally {
                $zip->close();
            }

            // 3) پاک‌سازی کش
            $this->clearCaches();
        } finally {
            $this->disableMaintenance($maintenance);
        }
    }

    /**
     * هرس پشتیبان‌های قدیمی (DOC-068 §7: retention).
     *
     * @return int تعداد حذف‌شده
     */
    public function prune(int $keepDays = 30): int
    {
        $cutoff = Clock::now()->sub(new \DateInterval('P' . \max(0, $keepDays) . 'D'));
        $disk = $this->diskOrFail();
        $deleted = 0;

        foreach ($this->list() as $manifest) {
            $createdAt = $manifest->createdAt !== null ? new \DateTimeImmutable($manifest->createdAt) : null;
            if ($createdAt === null || $createdAt >= $cutoff) {
                continue;
            }

            $disk->delete([$manifest->archiveName(), $manifest->manifestName()]);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * اعمال سیاست retention (daily/weekly/monthly — DOC-068 §7).
     *
     * @return int تعداد حذف‌شده
     */
    public function applyRetention(): int
    {
        $retention = (array) ($this->config?->get('backup.retention', []) ?? []);
        $keepDaily = (int) ($retention['daily'] ?? 7);
        $keepWeekly = (int) ($retention['weekly'] ?? 4);
        $keepMonthly = (int) ($retention['monthly'] ?? 12);

        $manifests = $this->list();   // جدیدترین ابتدا
        $keep = [];

        $daily = $weekly = $monthly = 0;
        foreach ($manifests as $manifest) {
            if ($manifest->createdAt === null) {
                continue;
            }
            $created = new \DateTimeImmutable($manifest->createdAt);
            $ageDays = (int) Clock::now()->diff($created)->format('%r%a');

            $shouldKeep = false;
            if ($ageDays <= 7 && $daily < $keepDaily) {
                $daily++;
                $shouldKeep = true;
            } elseif ($ageDays <= 28 && $weekly < $keepWeekly) {
                $weekly++;
                $shouldKeep = true;
            } elseif ($monthly < $keepMonthly) {
                $monthly++;
                $shouldKeep = true;
            }

            if ($shouldKeep) {
                $keep[$manifest->id] = true;
            }
        }

        $disk = $this->diskOrFail();
        $deleted = 0;
        foreach ($manifests as $manifest) {
            if (isset($keep[$manifest->id])) {
                continue;
            }
            $disk->delete([$manifest->archiveName(), $manifest->manifestName()]);
            $deleted++;
        }

        return $deleted;
    }

    public function encrypter(): BackupEncrypter
    {
        return $this->encrypter;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function diskOrFail(): FilesystemInterface
    {
        if ($this->disk !== null) {
            return $this->disk;
        }

        if ($this->container !== null && $this->container->bound('filesystem')) {
            $manager = $this->container->make('filesystem');
            if ($manager instanceof \Tavaan\Engine\Filesystem\FilesystemManager) {
                return $manager->disk((string) ($this->config?->get('backup.disk', 'backups') ?? 'backups'));
            }
        }

        throw BackupException::storeFailed('filesystem disk unavailable');
    }

    /**
     * جمع‌آوری فایل‌ها درون zip — 🔒 بدون دنبال‌کردن symlink.
     *
     * @param list<string> $paths    مسیرهای base-relative یا مطلق
     * @param list<string> $excludes
     *
     * @return array<string, string> zipName → مسیر اصلی
     */
    private function collectFiles(\Tavaan\Engine\Compat\ZipWriterPure $zip, array $paths, array $excludes): array
    {
        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 3);
        $map = [];

        foreach ($paths as $path) {
            $path = (string) $path;
            $absolute = $path !== '' && $path[0] === '/' ? $path : $base . '/' . $path;
            $absolute = $this->safeAbsolute($absolute, $base);

            if (\is_file($absolute)) {
                $name = 'files/' . $this->relativeName($absolute, $base);
                if ($this->isExcluded($absolute, $base, $excludes)) {
                    continue;
                }
                $zip->addFile($absolute, $name);
                $map[$name] = $absolute;
                continue;
            }

            if (!\is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile() || $file->isLink()) {
                    continue;   // 🔒 symlink هرگز دنبال نمی‌شود
                }

                $filePath = $file->getPathname();
                if ($this->isExcluded($filePath, $base, $excludes)) {
                    continue;
                }

                $name = 'files/' . $this->relativeName($filePath, $base);
                $zip->addFile($filePath, $name);
                $map[$name] = $filePath;
            }
        }

        return $map;
    }

    /** نام نسبی برای zip — مسیرهای خارج از base با leading-slash کامل نگاشت می‌شوند. */
    private function relativeName(string $absolute, string $base): string
    {
        $normalized = $this->normalize($absolute);
        $normalizedBase = $this->normalize($base);

        if (\str_starts_with($normalized, $normalizedBase . '/')) {
            return \ltrim(\substr($normalized, \strlen($normalizedBase)), '/');
        }

        return \ltrim($normalized, '/');   // خارج از base — مسیر کامل (بدون / اول)
    }

    /** 🔒 مسیر باید داخل base بماند (traversal رد می‌شود). */
    private function safeAbsolute(string $absolute, string $base): string
    {
        $normalized = $this->normalize($absolute);
        $normalizedBase = $this->normalize($base);

        if (!\str_starts_with($normalized, $normalizedBase . '/') && $normalized !== $normalizedBase
            && !\str_starts_with($absolute, '/')) {
            throw BackupException::unsafePath($absolute);
        }

        // مسیر مطلق خارج از base فقط وقتی مجاز است که `..` نداشته باشد
        if (\str_contains($absolute, '..')) {
            throw BackupException::unsafePath($absolute);
        }

        return $normalized;
    }

    private function normalize(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $segments = [];
        foreach (\explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                \array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return (\str_starts_with($path, '/') ? '/' : '') . \implode('/', $segments);
    }

    /** @param list<string> $excludes */
    private function isExcluded(string $absolute, string $base, array $excludes): bool
    {
        $relative = \ltrim(\str_replace('\\', '/', \substr($absolute, \strlen($base))), '/');

        foreach ($excludes as $exclude) {
            $exclude = \trim((string) $exclude, '/');
            if ($exclude === '') {
                continue;
            }
            if (\str_starts_with($relative, $exclude . '/') || $relative === $exclude) {
                return true;
            }
        }

        return false;
    }

    /** 🔒 نوشتن فایل بازیابی‌شده با بررسی traversal. */
    private function restoreFile(string $target, string $content): void
    {
        if (\str_contains($target, '..') || \str_contains($target, "\0")) {
            throw BackupException::unsafePath($target);
        }

        $dir = \dirname($target);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        $tmp = $target . '.restore-tmp';
        \file_put_contents($tmp, $content, \LOCK_EX);
        \rename($tmp, $target);   // 🔒 اتمیک
    }

    private function maintenanceFile(): string
    {
        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 3);

        return $base . '/storage/framework/down';
    }

    private function enableMaintenance(string $file): void
    {
        $dir = \dirname($file);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }
        \file_put_contents($file, (string) \json_encode([
            'message' => 'بازیابی پشتیبان در جریان است…',
            'time' => Clock::isoNow(),
            'reason' => 'backup-restore',
        ]));
    }

    private function disableMaintenance(string $file): void
    {
        if (\is_file($file)) {
            @\unlink($file);
        }
    }

    private function clearCaches(): void
    {
        try {
            if ($this->container !== null && $this->container->bound('cache')) {
                $cache = $this->container->make('cache');
                if (\method_exists($cache, 'flush')) {
                    $cache->flush();
                }
            }
        } catch (\Throwable) {
            // پاک‌سازی کش حیاتی نیست
        }
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
