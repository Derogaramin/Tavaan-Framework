<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Middleware;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Contracts\Http\TerminableMiddleware;
use Tavaan\Engine\Http\Cookie\Cookie;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Session\Session;
use Tavaan\Engine\Session\SessionManager;

/**
 * تَوان | Tavaan — Session
 * StartSessionMiddleware — شروع نشست در handle و نوشتن در terminate (P14).
 *
 * 🔒 Session Fixation: رد ID نامعتبر → تولید ID جدید با CSPRNG.
 * 🔒 کوکی: HttpOnly همیشه؛ Secure/SameSite از config.
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class StartSessionMiddleware implements MiddlewareInterface, TerminableMiddleware
{
    private ?Session $session = null;

    private bool $cookieDriver = false;

    private bool $saved = false;

    private string $lastId = '';

    public function __construct(
        private readonly SessionManager $manager,
        private readonly ContainerInterface $container,
        private readonly ?RepositoryInterface $config = null,
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if (!$request instanceof Request) {
            return $next($request);
        }

        $cookieName = (string) ($this->config?->get('session.cookie', 'tavaan_session') ?? 'tavaan_session');
        $driver = (string) ($this->config?->get('session.driver', 'file') ?? 'file');
        $this->cookieDriver = $driver === 'cookie';

        $id = (string) ($request->cookie($cookieName) ?? '');

        if ($this->cookieDriver) {
            // درایور کوکی: مقدار کوکی = payload رمزنگاری‌شده
            $manager = $this->manager;
            $handler = $manager->driver('cookie');
            if ($id !== '' && $handler instanceof \Tavaan\Engine\Session\Handlers\CookieHandler) {
                $sessionId = \substr(\hash('sha256', $id), 0, 40);
                $handler->seedFromCookie($sessionId, $id);
                $id = $sessionId;
            }
        }

        // 🔒 رد ID نامعتبر (Session Fixation / Prediction)
        if (!Session::isValidId($id)) {
            $id = Session::generateId();
        }

        $fingerprint = $this->fingerprint($request);
        $this->session = $this->manager->build($id, $fingerprint);
        $this->session->start();
        $this->lastId = $id;

        // اتصال به container و request
        $this->container->instance('session', $this->session);
        $this->container->instance(Session::class, $this->session);
        $request = $request->withAttribute('session', $this->session);
        $this->container->instance('request', $request);

        $response = $next($request);

        // ذخیرهٔ previousUrl برای back()
        if (\in_array($request->method(), ['GET'], true) && !$request->expectsJson()) {
            $this->session->setPreviousUrl($request->fullUrl());
        }

        // 🔒 نوشتن session + کوکی بلافاصله پس از تولید پاسخ (پیش از send) —
        // در terminate دیر است (پاسخ ارسال شده). AC-LC-05: فقط یک‌بار نوشته می‌شود.
        $this->session->save();
        $this->saved = true;

        if ($response instanceof Response) {
            $response = $response->cookie($this->sessionCookie());
        }

        return $response;
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        // fallback: اگر handle به دلیل exception زودهنگام save نکرد
        if ($this->session !== null && !$this->saved) {
            $this->session->save();
        }
        $this->session = null;
        $this->saved = false;
    }

    private function sessionCookie(): Cookie
    {
        $cookieName = (string) ($this->config?->get('session.cookie', 'tavaan_session') ?? 'tavaan_session');
        $lifetime = (int) ($this->config?->get('session.lifetime', 120) ?? 120);

        $value = (string) ($this->session?->id() ?? '');
        if ($this->cookieDriver) {
            $value = (string) ($this->manager->cookieHandler()?->cookieValue((string) $this->session?->id()) ?? '');
        }

        return new Cookie(
            name: $cookieName,
            value: $value,
            expire: $lifetime > 0 ? \time() + $lifetime * 60 : 0,
            path: (string) ($this->config?->get('session.path', '/') ?? '/'),
            domain: (($this->config?->get('session.domain')) ?: null) ?: null,
            secure: (bool) ($this->config?->get('session.secure', false) ?? false),
            httpOnly: true,                                   // 🔒 همیشه
            sameSite: (string) ($this->config?->get('session.same_site', 'Lax') ?? 'Lax'),
        );
    }

    private function fingerprint(Request $request): string
    {
        if (!(bool) ($this->config?->get('session.fingerprint', true) ?? true)) {
            return '';
        }

        return \hash('sha256', \implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) ($this->config?->get('session.cookie', 'tavaan_session') ?? ''),
        ]));
    }
}
