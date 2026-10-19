<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * WidgetGenerator — `make:widget RecentOrdersWidget [--module=shop]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class WidgetGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Widget')) {
            $class .= 'Widget';
        }

        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/View/Widgets/' . $class . '.php') => $this->render('widget.stub', [
                'namespace' => $context['namespace'] . '\\View\\Widgets',
                'class' => $class,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}
