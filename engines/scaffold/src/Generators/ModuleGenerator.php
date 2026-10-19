<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * ModuleGenerator — `make:module shop [--domain=hexagonal|layered]` (DOC-077 §6).
 *
 * ساختار کامل ماژول + manifest معتبر (AC-SCF-02: `tvn module:validate` پاس).
 * کشف خودکار است: `config/modules.php → paths` شامل `modules/` می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ModuleGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $slug = $this->validateKebab($cmd->nameArgument(), 'module');
        $domain = $cmd->opt('domain');
        $domain = \is_string($domain) && $domain !== '' ? $domain : 'hexagonal';
        if (!\in_array($domain, ['hexagonal', 'layered'], true)) {
            $domain = 'hexagonal';
        }

        return $this->files($slug, $domain);
    }

    /**
     * @return array<string, string>
     */
    public function files(string $slug, string $domain = 'hexagonal'): array
    {
        $slug = $this->validateKebab($slug, 'module');
        $pascal = Str::pascal(\str_replace('-', ' ', $slug));
        $root = 'modules/' . $slug;

        $vars = [
            'slug' => $slug,
            'pascal' => $pascal,
            'envPrefix' => \strtoupper(\str_replace('-', '_', $slug)),
            'domainNote' => $domain === 'hexagonal'
                ? 'Domain/Application/Infrastructure/Contracts'
                : 'Http/Models/Services',
        ];

        $files = [
            $this->target($root . '/module.json') => $this->render('module-json.stub', $vars),
            $this->target($root . '/composer.json') => $this->render('module-composer.stub', $vars),
            $this->target($root . '/README.md') => $this->render('module-readme.stub', $vars),
            $this->target($root . '/src/Providers/' . $pascal . 'ServiceProvider.php') => $this->render('module-provider.stub', $vars),
            $this->target($root . '/config/' . $slug . '.php') => $this->render('module-config.stub', $vars),
            $this->target($root . '/views/.gitkeep') => '',
            $this->target($root . '/migrations/.gitkeep') => '',
            $this->target($root . '/seeds/.gitkeep') => '',
            $this->target($root . '/tests/Unit/.gitkeep') => '',
        ];

        foreach (['web', 'api', 'admin'] as $channel) {
            $files[$this->target($root . '/routes/' . $channel . '.php')] = $this->render('module-routes.stub', [...$vars, 'channel' => $channel]);
        }
        foreach (['fa', 'en'] as $lang) {
            $files[$this->target($root . '/lang/' . $lang . '/' . $slug . '.php')] = $this->render('module-lang.stub', [...$vars, 'lang' => $lang]);
        }

        $srcDirs = $domain === 'hexagonal'
            ? [
                'src/Http/Controllers',
                'src/Domain/Entities', 'src/Domain/ValueObjects', 'src/Domain/Services', 'src/Domain/Events',
                'src/Application/UseCases', 'src/Application/Commands', 'src/Application/Queries',
                'src/Infrastructure/Persistence',
                'src/Contracts',
            ]
            : ['src/Http/Controllers', 'src/Models', 'src/Services'];

        foreach ($srcDirs as $dir) {
            $files[$this->target($root . '/' . $dir . '/.gitkeep')] = '';
        }

        return $files;
    }
}
