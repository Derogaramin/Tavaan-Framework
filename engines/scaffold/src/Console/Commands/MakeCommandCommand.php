<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:command` — ساخت دستور CLI (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeCommandCommand extends GenerateCommand
{
    protected string $signature = 'make:command {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت دستور CLI';

    protected function generatorKey(): string
    {
        return 'command';
    }
}
