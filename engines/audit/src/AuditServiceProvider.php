<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Audit
 * AuditServiceProvider — ثبت Auditor به‌عنوان `audit` (Facade: Audit).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Auditor::class, static function (ContainerInterface $c): Auditor {
            $config = (array) ($c->bound('config') ? $c->make('config')->get('audit', []) : []);
            $appKey = (string) ($c->bound('config') ? $c->make('config')->get('app.key', '') : '');

            return new Auditor(
                $c->make(ConnectionInterface::class),
                $config,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
                $appKey,
            );
        });
        $this->app->alias(Auditor::class, 'audit');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Auditor::class, 'audit'];
    }
}
