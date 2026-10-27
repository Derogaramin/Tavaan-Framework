<?php

declare(strict_types=1);

namespace Modules\CoreUser\Providers;

use Modules\CoreUser\Application\UseCases\ProfileService;
use Modules\CoreUser\Application\UseCases\RegistrationService;
use Modules\CoreUser\Application\UseCases\UserAdministrationService;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * CoreUserServiceProvider — bindingها + routes + lang + ویجت داشبورد.
 *
 * موتور auth با DatabaseUserProvider (table=users) کار می‌کند؛ این ماژول
 * موجودیت/خواندن/نوشتنِ سطح-دامنه + UI احراز هویت و مدیریت کاربران را
 * فراهم می‌کند.
 *
 * 🔒 ARCH-003: فقط Core (Facades/Contracts) — نقش‌ها از alias `rbac.roles`.
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class CoreUserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseUserRepository::class, static fn (): DatabaseUserRepository => new DatabaseUserRepository());
        $this->app->singleton(UserRepositoryInterface::class, static fn (ContainerInterface $c): UserRepositoryInterface => $c->make(DatabaseUserRepository::class));
        $this->app->alias(UserRepositoryInterface::class, 'core-user.repository');

        // قرارداد عمومی بین‌ماژولی (DOC-090 §6) — همان آداپتر
        $this->app->singleton(UserReaderInterface::class, static fn (ContainerInterface $c): UserReaderInterface => $c->make(DatabaseUserRepository::class));
        $this->app->alias(UserReaderInterface::class, 'users.reader');

        // ── Application: سرویس‌های UseCase ───────────────────────────────
        $this->app->singleton(RegistrationService::class, static function (ContainerInterface $c): RegistrationService {
            return new RegistrationService(
                users: $c->make(UserRepositoryInterface::class),
                app: $c,
                config: $c->bound('config') ? (array) $c->make('config')->get('core-user', []) : [],
            );
        });

        $this->app->singleton(ProfileService::class, static function (ContainerInterface $c): ProfileService {
            return new ProfileService(
                users: $c->make(UserRepositoryInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('core-user', []) : [],
            );
        });

        $this->app->singleton(UserAdministrationService::class, static function (ContainerInterface $c): UserAdministrationService {
            return new UserAdministrationService(
                users: $c->make(UserRepositoryInterface::class),
                app: $c,
                config: $c->bound('config') ? (array) $c->make('config')->get('core-user', []) : [],
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerDashboardWidget();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            UserReaderInterface::class,
            UserRepositoryInterface::class,
            DatabaseUserRepository::class,
            RegistrationService::class,
            ProfileService::class,
            UserAdministrationService::class,
            'users.reader',
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function registerLang(string $moduleRoot): void
    {
        if (!$this->app->bound('lang')) {
            return;
        }
        $translator = $this->app->make('lang');
        if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
            $translator->addNamespace('core-user', $moduleRoot . '/lang');
        }
    }

    private function registerRoutes(string $moduleRoot): void
    {
        foreach (['web', 'admin'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }
    }

    /** ویجت «کاربران» روی پیشخوان مدیریت (DOC-078 §5 — Renderable). */
    private function registerDashboardWidget(): void
    {
        if (!$this->app->bound('admin')) {
            return;
        }

        try {
            $admin = $this->app->make('admin');
            if (\is_object($admin) && \method_exists($admin, 'widget')) {
                $admin->widget(\Modules\CoreUser\Dashboard\UsersWidget::class);
            }
        } catch (\Throwable) {
            // نبود موتور admin نباید بوت ماژول را بشکند
        }
    }
}
