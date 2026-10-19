<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:migration` — ساخت migration (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeMigrationCommand extends GenerateCommand
{
    protected string $signature = 'make:migration {name : نام snake_case} {--table= : نام جدول} {--fields= : ستون‌ها (name,price:integer)} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت migration';

    protected function generatorKey(): string
    {
        return 'migration';
    }
}
