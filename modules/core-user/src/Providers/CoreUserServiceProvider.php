<?php

declare(strict_types=1);

namespace Modules\CoreUser\Providers;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * CoreUserServiceProvider — bind پورت UserReaderInterface + namespace ترجمه.
 *
 * موتور auth با DatabaseUserProvider (table=users) کار می‌کند؛ این ماژول
 * موجودیت/خواندنِ سطح-دامنه را برای سایر ماژول‌ها فراهم می‌کند.
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class CoreUserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseUserRepository::class, static fn (): DatabaseUserRepository => new DatabaseUserRepository());
        $this->app->singleton(UserReaderInterface::class, static fn ($app): UserReaderInterface => $app->make(DatabaseUserRepository::class));
        $this->app->alias(UserReaderInterface::class, 'users.reader');
    }

    public function boot(): void
    {
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('core-user', \dirname(__DIR__, 2) . '/lang');
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [UserReaderInterface::class, DatabaseUserRepository::class, 'users.reader'];
    }
}
