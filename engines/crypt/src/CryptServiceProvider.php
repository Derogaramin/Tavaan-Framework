<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Crypt
 * CryptServiceProvider — ثبت KeyRing/Crypt به‌عنوان `encrypter`.
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class CryptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeyRing::class, static function (ContainerInterface $c): KeyRing {
            $key = $c->bound('config') ? (string) $c->make('config')->get('app.key', '') : '';

            return new KeyRing($key);
        });

        $this->app->singleton(Crypt::class, static fn (ContainerInterface $c): Crypt => new Crypt($c->make(KeyRing::class)));
        $this->app->alias(Crypt::class, 'encrypter');
        $this->app->alias(Crypt::class, EncrypterInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Crypt::class, KeyRing::class, 'encrypter'];
    }
}
