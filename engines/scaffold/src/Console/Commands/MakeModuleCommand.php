<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:module` — ساخت ماژول کامل (DOC-077 §6) (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeModuleCommand extends GenerateCommand
{
    protected string $signature = 'make:module {name : slug ماژول (kebab)} {--domain=hexagonal : hexagonal|layered} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت ماژول کامل (DOC-077 §6)';

    protected function generatorKey(): string
    {
        return 'module';
    }
}
