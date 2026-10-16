<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade سنجه‌ها (Metrics — DOC-067 §2).
 *
 * @method static \Tavaan\Engine\Observability\Metrics\Counter counter(string $name, string $help = '')
 * @method static \Tavaan\Engine\Observability\Metrics\Gauge gauge(string $name, float|int|null $value = null, array $labels = [], string $help = '')
 * @method static \Tavaan\Engine\Observability\Metrics\Histogram histogram(string $name, ?array $buckets = null, string $help = '')
 * @method static \Tavaan\Engine\Observability\Metrics\Summary summary(string $name, ?array $quantiles = null, string $help = '')
 * @method static mixed timer(string $name, callable $fn, array $labels = [])
 * @method static string collect()
 * @method static array snapshot()
 * @method static void reset()
 * @mixin \Tavaan\Engine\Observability\Metrics\MetricsRegistry
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Metrics extends Facade
{
    protected static function accessor(): string
    {
        return 'metrics';
    }
}
