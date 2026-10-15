<?php

declare(strict_types=1);

namespace Tavaan\Engine\Pipeline;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Pipeline\PipelineInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Pipeline
 * PipelineServiceProvider — ثبت factory برای Pipeline (هر make = زنجیرهٔ تازه).
 *
 * @api 1.0
 * @see docs/03-engines/pipeline.md
 */
final class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Pipeline::class, static function (ContainerInterface $c): Pipeline {
            $config = $c->bound('config') ? (array) $c->make('config')->get('pipeline', []) : [];

            return new Pipeline($c, $config);
        });
        $this->app->alias(Pipeline::class, PipelineInterface::class);
        $this->app->alias(Pipeline::class, 'pipeline');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Pipeline::class, PipelineInterface::class, 'pipeline'];
    }
}
