<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Validation
 * ValidationServiceProvider — ثبت ValidatorFactory به‌عنوان `validator`.
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ValidatorFactory::class, static function (ContainerInterface $c): ValidatorFactory {
            return new ValidatorFactory(
                $c->bound('translator') ? $c->make('translator') : null,
                $c,   // db تنبل — فقط وقتی قاعدهٔ دیتابیس استفاده شود resolve می‌شود
                $c->bound('config') ? $c->make('config') : null,
            );
        });
        $this->app->alias(ValidatorFactory::class, 'validator');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ValidatorFactory::class, 'validator'];
    }
}
