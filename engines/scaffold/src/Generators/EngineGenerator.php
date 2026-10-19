<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * EngineGenerator — `make:engine reporting`.
 *
 * اسکلت موتور: `engines/<slug>/{module.json, config/<slug>.php,
 * src/<Pascal>ServiceProvider.php, tests/Unit}` — manifest معتبر (فیلدهای
 * الزامی ManifestParser) تا موتور بلافاصله کشف شود.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class EngineGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $slug = $this->validateKebab($cmd->nameArgument(), 'engine');
        $pascal = Str::pascal(\str_replace('-', ' ', $slug));
        $root = 'engines/' . $slug;

        $vars = ['slug' => $slug, 'pascal' => $pascal, 'envPrefix' => \strtoupper(\str_replace('-', '_', $slug))];

        return [
            $this->target($root . '/module.json') => $this->render('engine-module.stub', $vars),
            $this->target($root . '/src/' . $pascal . 'ServiceProvider.php') => $this->render('engine-provider.stub', $vars),
            $this->target($root . '/config/' . $slug . '.php') => $this->render('module-config.stub', $vars),
            $this->target($root . '/tests/Unit/.gitkeep') => '',
        ];
    }
}
