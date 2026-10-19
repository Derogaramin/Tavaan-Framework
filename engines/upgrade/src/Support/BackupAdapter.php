<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Support;

use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;

/**
 * تَوان | Tavaan — Upgrade
 * BackupAdapter — پل BackupRunnerInterface به موتور Backup (DOC-068).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class BackupAdapter implements BackupRunnerInterface
{
    public function __construct(private readonly BackupManager $backups)
    {
    }

    public function run(): string
    {
        try {
            $manifest = $this->backups->run('full');
        } catch (\Throwable $e) {
            throw UpgradeException::backupFailed($e->getMessage());
        }

        return $manifest->id;
    }

    public function restore(string $id): void
    {
        $this->backups->restore($id, force: true);
    }
}
