<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:factory` — ساخت Factory تست (DOC-084E §6) (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeFactoryCommand extends GenerateCommand
{
    protected string $signature = 'make:factory {name : نام کلاس} {--model= : مدل مرتبط} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت Factory تست (DOC-084E §6)';

    protected function generatorKey(): string
    {
        return 'factory';
    }
}
