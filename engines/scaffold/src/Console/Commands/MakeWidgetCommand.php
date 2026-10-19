<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Console\Commands;

use Tavaan\Engine\Scaffold\GenerateCommand;

/**
 * `make:widget` — ساخت ویجت Renderable (DOC-077 §2).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MakeWidgetCommand extends GenerateCommand
{
    protected string $signature = 'make:widget {name : نام کلاس} {--module= : ماژول هدف} {--force : بازنویسی با پشتیبان}';

    protected string $description = 'ساخت ویجت Renderable';

    protected function generatorKey(): string
    {
        return 'widget';
    }
}
