<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Workflow\Store\ArrayStore;
use Tavaan\Engine\Workflow\Store\DatabaseStore;
use Tavaan\Engine\Workflow\Store\StoreInterface;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowServiceProvider — ثبت WorkflowManager به‌عنوان `workflow` (Facade: Workflow).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        WorkflowResolver::set($this->app);

        $this->app->singleton(StoreInterface::class, static function (ContainerInterface $c): StoreInterface {
            /** @var array<string, mixed> $config */
            $config = $c->bound('config') ? (array) $c->make('config')->get('workflow', []) : [];
            $driver = (string) ($config['driver'] ?? 'database');

            if ($driver === 'array') {
                return new ArrayStore();
            }

            if ($c->bound('db')) {
                $db = $c->make('db');
                if ($db instanceof ConnectionManager) {
                    return new DatabaseStore($db, (array) ($config['tables'] ?? []));
                }
            }

            // DB در دسترس نبود → fallback حافظه‌ای (بدون شکست boot)
            return new ArrayStore();
        });

        $this->app->singleton(WorkflowManager::class, static function (ContainerInterface $c): WorkflowManager {
            /** @var array<string, mixed> $config */
            $config = $c->bound('config') ? (array) $c->make('config')->get('workflow', []) : [];

            $events = null;
            if ($c->bound(DispatcherInterface::class)) {
                $candidate = $c->make(DispatcherInterface::class);
                $events = $candidate instanceof DispatcherInterface ? $candidate : null;
            } elseif ($c->bound('events')) {
                $candidate = $c->make('events');
                $events = $candidate instanceof DispatcherInterface ? $candidate : null;
            }

            $store = $c->make(StoreInterface::class);

            return new WorkflowManager(
                $store instanceof StoreInterface ? $store : new ArrayStore(),
                $config,
                $events,
                $c,
            );
        });
        $this->app->alias(WorkflowManager::class, 'workflow');

        $this->app->singleton(Visualizer::class, static fn (): Visualizer => new Visualizer());
        $this->app->alias(Visualizer::class, 'workflow.visualizer');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [WorkflowManager::class, 'workflow', StoreInterface::class, Visualizer::class, 'workflow.visualizer'];
    }
}
