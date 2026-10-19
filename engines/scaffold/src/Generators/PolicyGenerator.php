<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * PolicyGenerator — `make:policy ProductPolicy [--model=Product] [--module=shop]`.
 *
 * متدها مطابق نگاشت CRUD در `Gate::POLICY_METHODS` (DOC-057 §4).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class PolicyGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Policy')) {
            $class .= 'Policy';
        }

        $model = $cmd->opt('model');
        $model = \is_string($model) && $model !== ''
            ? $this->validateClassName($model)
            : (string) \preg_replace('/Policy$/', '', $class);

        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/Policies/' . $class . '.php') => $this->render('policy.stub', [
                'namespace' => $context['namespace'] . '\\Policies',
                'class' => $class,
                'model' => $model,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}
