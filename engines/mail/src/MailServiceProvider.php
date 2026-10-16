<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Mail
 * MailServiceProvider — ثبت Mailer به‌عنوان `mailer` (Facade: Mail).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Mailer::class, static function (ContainerInterface $c): Mailer {
            return new Mailer(
                $c->bound('config') ? $c->make('config') : null,
                $c,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
            );
        });
        $this->app->alias(Mailer::class, 'mailer');
        $this->app->alias(Mailer::class, 'mail');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Mailer::class, 'mailer', 'mail'];
    }
}
