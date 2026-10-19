<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:engine` — ساخت اسکلت موتور جدید (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeEngineCommand extends GenerateCommand
{
    protected string $signature = 'make:engine {name : slug موتور (kebab)} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت اسکلت موتور جدید';

    protected function generatorKey(): string
    {
        return 'engine';
    }
}
