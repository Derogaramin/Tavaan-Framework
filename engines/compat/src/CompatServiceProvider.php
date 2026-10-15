<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Compat
 * CompatServiceProvider — اعمال force_fallbacks از config.
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final class CompatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        $force = (bool) $this->app->make('config')->get('compat.force_fallbacks', false);
        Compat::forceFallbacks($force);
    }
}
