<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:middleware` — ساخت middleware (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeMiddlewareCommand extends GenerateCommand
{
    protected string $signature = 'make:middleware {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت middleware';

    protected function generatorKey(): string
    {
        return 'middleware';
    }
}
