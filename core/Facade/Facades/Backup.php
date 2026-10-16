<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پشتیبان‌گیری (Backup).
 *
 * @method static \Tavaan\Engine\Backup\Manifest run(?string $type = 'full', ?array $files = null)
 * @method static \Tavaan\Engine\Backup\Manifest database()
 * @method static \Tavaan\Engine\Backup\Manifest files(array $paths)
 * @method static void restore(string $id, bool $force = false, ?array $onlyFiles = null)
 * @method static list<\Tavaan\Engine\Backup\Manifest> list()
 * @method static \Tavaan\Engine\Backup\Manifest|null find(string $id)
 * @method static bool verify(string $id)
 * @method static int prune(int $keepDays = 30)
 * @method static int applyRetention()
 * @mixin \Tavaan\Engine\Backup\BackupManager
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class Backup extends Facade
{
    protected static function accessor(): string
    {
        return 'backup';
    }
}
