<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * CommandGenerator — `make:command OrdersCleanup [--module=shop]`.
 *
 * مسیر `Console/Commands/*Command.php` → کشف خودکار توسط ConsoleKernel.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class CommandGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Command')) {
            $class .= 'Command';
        }

        $context = $this->context($cmd);
        $commandName = Str::kebab((string) \preg_replace('/Command$/', '', $class));

        return [
            $this->target($context['src'] . '/Console/Commands/' . $class . '.php') => $this->render('command.stub', [
                'namespace' => $context['namespace'] . '\\Console\\Commands',
                'class' => $class,
                'module' => $context['module'] ?? '',
                'commandName' => $commandName,
                'description' => 'دستور ' . $class,
            ]),
        ];
    }
}
