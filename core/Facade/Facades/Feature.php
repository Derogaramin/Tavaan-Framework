<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پرچم قابلیت (Feature — DOC-075).
 *
 * @method static bool enabled(string $feature, ?object $user = null)
 * @method static \Tavaan\Engine\FeatureFlag\ScopedFeature for(?object $user)
 * @method static mixed value(string $feature, mixed $default = null)
 * @method static void define(string $feature, array $definition)
 * @method static void percentage(string $feature, int $percentage)
 * @method static void kill(string $feature)
 * @method static void revive(string $feature)
 * @method static bool isKilled(string $feature)
 * @method static string|null experiment(string $experiment, array $variants, ?object $user = null)
 * @method static list<string> known()
 * @method static void flushCache(?string $feature = null)
 * @mixin \Tavaan\Engine\FeatureFlag\FeatureManager
 * @api 1.0
 * @see docs/03-engines/feature-flag.md
 */
final class Feature extends Facade
{
    protected static function accessor(): string
    {
        return 'feature';
    }
}
