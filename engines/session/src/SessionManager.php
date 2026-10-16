<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\Session\Handlers\ArrayHandler;
use Tavaan\Engine\Session\Handlers\CacheHandler;
use Tavaan\Engine\Session\Handlers\CookieHandler;
use Tavaan\Engine\Session\Handlers\DatabaseHandler;
use Tavaan\Engine\Session\Handlers\FileHandler;

/**
 * تَوان | Tavaan — Session
 * SessionManager — ساخت Session با درایور انتخابی از config.
 *
 * درایورها: array/file/cookie/database (M3) + redis (M4 — روی موتور cache).
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class SessionManager
{
    private ?SessionHandlerInterface $handler = null;

    private ?CookieHandler $cookieHandler = null;

    public function __construct(
        private readonly ?RepositoryInterface $config = null,
        private readonly ?Crypt $crypt = null,
    ) {
    }

    public function driver(?string $name = null): SessionHandlerInterface
    {
        $name ??= (string) ($this->config?->get('session.driver', 'file') ?? 'file');

        return $this->handler ??= match ($name) {
            'array'  => new ArrayHandler(),
            'file'   => new FileHandler(
                (string) ($this->config?->get('session.files', \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/sessions' : \sys_get_temp_dir())),
                (array) ($this->config?->get('session.lottery', [2, 100]) ?? [2, 100]),
            ),
            'cookie' => $this->cookieHandler = new CookieHandler($this->requireCrypt()),
            'database' => new DatabaseHandler($this->requireConnection()),
            'redis' => new CacheHandler(
                $this->requireCache()->store('redis'),
                prefix: 'session:',
                lifetimeSeconds: (int) ($this->config?->get('session.lifetime', 120) ?? 120) * 60,
            ),
            default => throw new \InvalidArgumentException(\sprintf('Unknown session driver [%s].', $name)),
        };
    }

    public function cookieHandler(): ?CookieHandler
    {
        return $this->cookieHandler;
    }

    /** ساخت Session برای یک درخواست. */
    public function build(string $id, string $fingerprint = ''): Session
    {
        $get = fn (string $key, mixed $default): mixed => $this->config?->get('session.' . $key, $default) ?? $default;

        return new Session(
            id: $id,
            name: (string) $get('cookie', 'tavaan_session'),
            handler: $this->driver(),
            idleTimeout: (int) $get('idle_timeout', 1800),
            absoluteTimeout: (int) $get('absolute_timeout', 43200),
            fingerprintEnabled: (bool) $get('fingerprint', true),
            fingerprint: $fingerprint,
        );
    }

    private function requireCrypt(): Crypt
    {
        if ($this->crypt === null) {
            throw new \RuntimeException('Session cookie driver requires the crypt engine (APP_KEY). Run `tvn key:generate`.');
        }

        return $this->crypt;
    }

    private function requireConnection(): ConnectionInterface
    {
        $connection = Facade::getFacadeApplication()->make(ConnectionInterface::class);
        if (!$connection instanceof ConnectionInterface) {
            throw new \RuntimeException('Session database driver requires the database engine (M3).');
        }

        return $connection;
    }

    private function requireCache(): \Tavaan\Engine\Cache\CacheManager
    {
        $cache = Facade::getFacadeApplication()->make('cache');
        if (!$cache instanceof \Tavaan\Engine\Cache\CacheManager) {
            throw new \RuntimeException('Session redis driver requires the cache engine (M4).');
        }

        return $cache;
    }
}
