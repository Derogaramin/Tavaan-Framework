<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:policy` — ساخت Policy (RBAC) (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakePolicyCommand extends GenerateCommand
{
    protected string $signature = 'make:policy {name : نام کلاس} {--model= : مدل مرتبط} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت Policy (RBAC)';

    protected function generatorKey(): string
    {
        return 'policy';
    }
}
