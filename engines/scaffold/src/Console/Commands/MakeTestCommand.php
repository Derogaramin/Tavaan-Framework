<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:test` — ساخت تست (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeTestCommand extends GenerateCommand
{
    protected string $signature = 'make:test {name : نام کلاس} {--unit : تست واحد (پیش‌فرض Feature)} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت تست';

    protected function generatorKey(): string
    {
        return 'test';
    }
}
