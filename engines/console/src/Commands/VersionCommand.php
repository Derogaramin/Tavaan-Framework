<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * نمایش نسخهٔ فریمورک و PHP.
 *
 * @api 1.0
 */
final class VersionCommand extends Command
{
    protected string $signature = 'version';

    protected string $description = 'نمایش نسخهٔ تَوان';

    public function handle(): int
    {
        $this->line('Tavaan ' . (\defined('TVN_VERSION') ? TVN_VERSION : 'dev') . ' (PHP ' . PHP_VERSION . ')');

        return self::SUCCESS;
    }
}
