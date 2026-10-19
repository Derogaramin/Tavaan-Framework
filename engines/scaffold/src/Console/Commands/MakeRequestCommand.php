<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:request` — ساخت FormRequest اعتبارسنجی (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeRequestCommand extends GenerateCommand
{
    protected string $signature = 'make:request {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت FormRequest اعتبارسنجی';

    protected function generatorKey(): string
    {
        return 'request';
    }
}
