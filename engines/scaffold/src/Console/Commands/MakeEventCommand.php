<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:event` — ساخت رویداد دامنه (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeEventCommand extends GenerateCommand
{
    protected string $signature = 'make:event {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت رویداد دامنه';

    protected function generatorKey(): string
    {
        return 'event';
    }
}
