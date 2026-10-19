<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:resource` — ساخت Resource لایهٔ API (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeResourceCommand extends GenerateCommand
{
    protected string $signature = 'make:resource {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت Resource لایهٔ API';

    protected function generatorKey(): string
    {
        return 'resource';
    }
}
