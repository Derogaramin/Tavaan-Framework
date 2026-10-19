<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:model` — ساخت مدل ORM (+migration/factory/seeder/controller) (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeModelCommand extends GenerateCommand
{
    protected string $signature = 'make:model {name : نام کلاس} {--migration : همراه migration} {--factory : همراه factory} {--seeder : همراه seeder} {--controller : همراه کنترلر CRUD} {--api : کنترلر API} {--fields= : فیلدها (name,price:integer)} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت مدل ORM (+migration/factory/seeder/controller)';

    protected function generatorKey(): string
    {
        return 'model';
    }
}
