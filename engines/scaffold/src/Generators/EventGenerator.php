<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * EventGenerator — `make:event OrderWasPaid [--module=shop]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class EventGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/Events/' . $class . '.php') => $this->render('event.stub', [
                'namespace' => $context['namespace'] . '\\Events',
                'class' => $class,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}
