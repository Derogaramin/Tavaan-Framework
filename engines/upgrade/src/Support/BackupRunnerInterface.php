<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Support;

/**
 * تَوان | Tavaan — Upgrade
 * BackupRunnerInterface — انتزاع backup/restore برای Upgrader (تست‌پذیر با Mock).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
interface BackupRunnerInterface
{
    /**
     * ساخت پشتیبان کامل → شناسهٔ backup.
     *
     * @throws \Tavaan\Engine\Upgrade\Exceptions\UpgradeException در صورت شکست
     */
    public function run(): string;

    /** بازگردانی یک backup (force — تأیید مخرب بودن). */
    public function restore(string $id): void;
}
