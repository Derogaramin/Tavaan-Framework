<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:seeder` — ساخت Seeder دادهٔ اولیه (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeSeederCommand extends GenerateCommand
{
    protected string $signature = 'make:seeder {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت Seeder دادهٔ اولیه';

    protected function generatorKey(): string
    {
        return 'seeder';
    }
}
