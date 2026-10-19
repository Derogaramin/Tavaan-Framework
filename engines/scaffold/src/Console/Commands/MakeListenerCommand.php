<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:listener` — ساخت Listener رویداد (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeListenerCommand extends GenerateCommand
{
    protected string $signature = 'make:listener {name : نام کلاس} {--event= : رویداد هدف} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت Listener رویداد';

    protected function generatorKey(): string
    {
        return 'listener';
    }
}
