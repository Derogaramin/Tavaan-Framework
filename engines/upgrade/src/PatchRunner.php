<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;

/**
 * تَوان | Tavaan — Upgrade
 * PatchRunner — کشف و اجرای DataPatchهای یک نسخه (idempotent با repository).
 *
 * قرارداد فایل: `upgrades/<version>/patch_<seq>_<name>.php` → `return new class extends DataPatch {…}`.
 * ترتیب اجرا: sort نام فایل (پیشوند شماره‌ای = ترتیب قطعی).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class PatchRunner
{
    public function __construct(
        private readonly string $upgradesPath,
        private readonly UpgradeRepository $repository,
        private readonly ?string $envFile = null,
    ) {
    }

    /**
     * فهرست patchهای یک نسخه (مرتب).
     *
     * @return list<array{file: string, name: string, applied: bool}>
     */
    public function discover(string $version): array
    {
        $dir = $this->versionPath($version);
        $patches = [];
        foreach (\glob($dir . '/patch_*.php') ?: [] as $file) {
            $name = \basename($file);
            $patches[] = [
                'file' => $file,
                'name' => $name,
                'applied' => $this->repository->patchApplied($version, $name),
            ];
        }
        \usort($patches, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

        return $patches;
    }

    /**
     * اجرای patchهای pending یک نسخه.
     *
     * @return list<string> نام patchهای اجراشده در این فراخوانی
     *
     * @throws UpgradeException
     */
    public function run(string $version, int $batch): array
    {
        $executed = [];

        foreach ($this->discover($version) as $entry) {
            if ($entry['applied']) {
                continue;   // 🔒 idempotency سطح repository
            }

            $patch = require $entry['file'];
            if (!$patch instanceof DataPatch) {
                throw UpgradeException::patchFailed($version, $entry['name'], 'فایل باید DataPatch برگرداند');
            }
            if ($patch->version !== '' && $patch->version !== $version) {
                throw UpgradeException::patchFailed(
                    $version,
                    $entry['name'],
                    \sprintf('version داخل patch (%s) با دایرکتوری (%s) نمی‌خواند', $patch->version, $version),
                );
            }

            try {
                $patch->up();
            } catch (\Throwable $e) {
                throw UpgradeException::patchFailed($version, $entry['name'], $e->getMessage(), $e);
            }

            $this->repository->logPatch($version, $entry['name'], $batch);
            $executed[] = $entry['name'];
        }

        // config migration همین نسخه (upgrades/<v>/config.php)
        $configFile = $this->versionPath($version) . '/config.php';
        if (\is_file($configFile) && !$this->repository->patchApplied($version, 'config.php')) {
            $changes = require $configFile;
            if (\is_array($changes) && $changes !== []) {
                $envFile = $this->envFile ?? \dirname($this->upgradesPath) . '/.env';
                $migrator = new ConfigMigrator($envFile);
                $migrator->migrate($changes);
            }
            $this->repository->logPatch($version, 'config.php', $batch);
            $executed[] = 'config.php';
        }

        return $executed;
    }

    /**
     * down همهٔ patchهای یک نسخه (ترتیب معکوس) — برای rollback نرم.
     */
    public function rollbackVersion(string $version): void
    {
        $patches = \array_reverse($this->discover($version));
        foreach ($patches as $entry) {
            if (!$entry['applied'] || \str_ends_with($entry['name'], 'config.php')) {
                continue;
            }
            $patch = require $entry['file'];
            if ($patch instanceof DataPatch) {
                $patch->down();
            }
        }
    }

    public function versionPath(string $version): string
    {
        return $this->upgradesPath . '/' . $version;
    }
}
