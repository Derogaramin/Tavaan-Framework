<?php

declare(strict_types=1);

namespace Modules\Media\Providers;

use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\Media\Infrastructure\FacadeMediaGateway;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaServiceProvider — bind پورت MediaGatewayInterface → آداپتر Facade.
 *
 * @api 1.0
 * @see modules/media/README.md
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaGatewayInterface::class, static fn (): MediaGatewayInterface => new FacadeMediaGateway());
        $this->app->alias(MediaGatewayInterface::class, 'media.gateway');
    }

    public function boot(): void
    {
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('media', \dirname(__DIR__, 2) . '/lang');
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [MediaGatewayInterface::class, 'media.gateway'];
    }
}
