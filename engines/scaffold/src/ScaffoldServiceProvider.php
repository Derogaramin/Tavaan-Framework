<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Scaffold\Generators\CommandGenerator;
use Tavaan\Engine\Scaffold\Generators\ControllerGenerator;
use Tavaan\Engine\Scaffold\Generators\EngineGenerator;
use Tavaan\Engine\Scaffold\Generators\EventGenerator;
use Tavaan\Engine\Scaffold\Generators\FactoryGenerator;
use Tavaan\Engine\Scaffold\Generators\JobGenerator;
use Tavaan\Engine\Scaffold\Generators\ListenerGenerator;
use Tavaan\Engine\Scaffold\Generators\MiddlewareGenerator;
use Tavaan\Engine\Scaffold\Generators\MigrationGenerator;
use Tavaan\Engine\Scaffold\Generators\ModelGenerator;
use Tavaan\Engine\Scaffold\Generators\ModuleGenerator;
use Tavaan\Engine\Scaffold\Generators\PolicyGenerator;
use Tavaan\Engine\Scaffold\Generators\RequestGenerator;
use Tavaan\Engine\Scaffold\Generators\ResourceGenerator;
use Tavaan\Engine\Scaffold\Generators\SeederGenerator;
use Tavaan\Engine\Scaffold\Generators\TestGenerator;
use Tavaan\Engine\Scaffold\Generators\WidgetGenerator;

/**
 * تَوان | Tavaan — Scaffold
 * ScaffoldServiceProvider — ثبت GeneratorManager با هر ۱۷ Generator (تنبل).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ScaffoldServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<Generator>> کلید make:<key> → Generator */
    public const GENERATORS = [
        'module' => ModuleGenerator::class,
        'controller' => ControllerGenerator::class,
        'model' => ModelGenerator::class,
        'migration' => MigrationGenerator::class,
        'request' => RequestGenerator::class,
        'policy' => PolicyGenerator::class,
        'command' => CommandGenerator::class,
        'job' => JobGenerator::class,
        'event' => EventGenerator::class,
        'listener' => ListenerGenerator::class,
        'seeder' => SeederGenerator::class,
        'factory' => FactoryGenerator::class,
        'test' => TestGenerator::class,
        'widget' => WidgetGenerator::class,
        'resource' => ResourceGenerator::class,
        'engine' => EngineGenerator::class,
        'middleware' => MiddlewareGenerator::class,
    ];

    public function register(): void
    {
        $this->app->singleton(GeneratorManager::class, static function (ContainerInterface $c): GeneratorManager {
            unset($c);
            $basePath = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';

            return self::makeManager($basePath . '/resources/stubs', $basePath);
        });

        $this->app->alias(GeneratorManager::class, 'scaffold');
    }

    /**
     * ساخت Manager با ثبت هر ۱۷ Generator (تنبل) — قابل استفادهٔ مجدد در تست.
     */
    public static function makeManager(string $stubsPath, string $basePath): GeneratorManager
    {
        $manager = new GeneratorManager(new StubRenderer($stubsPath), $basePath);

        foreach (self::GENERATORS as $key => $class) {
            $manager->register($key, static fn (StubRenderer $stubs, string $base): Generator => new $class($stubs, $base));
        }

        return $manager;
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [GeneratorManager::class, 'scaffold'];
    }
}
