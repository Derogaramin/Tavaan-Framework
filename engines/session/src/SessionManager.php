<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Session\Handlers\ArrayHandler;
use Tavaan\Engine\Session\Handlers\CookieHandler;
use Tavaan\Engine\Session\Handlers\FileHandler;

/**
 * تَوان | Tavaan — Session
 * SessionManager — ساخت Session با درایور انتخابی از config.
 *
 * درایورهای M2: array/file/cookie — database/redis در M3/M4 افزوده می‌شوند.
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
            'database', 'redis' => throw new \RuntimeException(\sprintf(
                'Session driver [%s] در فاز M3/M4 با موتورهای database/cache فعال می‌شود.',
                $name,
            )),
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
}
