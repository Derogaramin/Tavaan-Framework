<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:job` — ساخت Job صف‌روند (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeJobCommand extends GenerateCommand
{
    protected string $signature = 'make:job {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت Job صف‌روند';

    protected function generatorKey(): string
    {
        return 'job';
    }
}
