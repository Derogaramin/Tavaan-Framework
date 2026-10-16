<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Security\Csrf\CsrfManager;
use Tavaan\Engine\Security\Normalize\InputNormalizer;
use Tavaan\Engine\Security\RateLimit\RateLimiter;
use Tavaan\Engine\Security\Ssrf\SsrfGuard;

/**
 * تَوان | Tavaan — Security
 * SecurityServiceProvider — ثبت Security/RateLimiter/Csrf (Facade: Security).
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Security::class, static function (ContainerInterface $c): Security {
            $config = (array) ($c->bound('config') ? $c->make('config')->get('security', []) : []);

            return new Security(
                $config,
                $c->bound(EncrypterInterface::class) ? $c->make(EncrypterInterface::class) : null,
                new SsrfGuard((array) ($config['ssrf'] ?? [])),
                new InputNormalizer((array) ($config['normalize'] ?? [])),
            );
        });
        $this->app->alias(Security::class, 'security');

        $this->app->singleton(RateLimiter::class, static function (ContainerInterface $c): RateLimiter {
            $algorithm = (string) ($c->bound('config') ? $c->make('config')->get('security.rate_limit.algorithm', 'sliding') : 'sliding');

            return new RateLimiter($c->make('cache'), $algorithm);
        });
        $this->app->alias(RateLimiter::class, 'security.rate_limiter');

        $this->app->singleton(CsrfManager::class, static function (ContainerInterface $c): CsrfManager {
            return new CsrfManager(
                static function (): ?\Tavaan\Engine\Session\Session {
                    $session = \Tavaan\Core\Facade\Facade::getFacadeApplication()->bound('session')
                        ? \Tavaan\Core\Facade\Facade::getFacadeApplication()->make('session')
                        : null;

                    return $session instanceof \Tavaan\Engine\Session\Session ? $session : null;
                },
                $c->bound(EncrypterInterface::class) ? $c->make(EncrypterInterface::class) : null,
            );
        });
        $this->app->alias(CsrfManager::class, 'security.csrf');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Security::class, RateLimiter::class, CsrfManager::class, 'security', 'security.rate_limiter', 'security.csrf'];
    }
}
