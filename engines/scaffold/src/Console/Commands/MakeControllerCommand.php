<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:controller` — ساخت کنترلر (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeControllerCommand extends GenerateCommand
{
    protected string $signature = 'make:controller {name : نام کلاس (Shop/ProductController مجاز)} {--resource : اکشن‌های CRUD} {--api : پاسخ JSON} {--model= : نام مدل} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت کنترلر';

    protected function generatorKey(): string
    {
        return 'controller';
    }
}
