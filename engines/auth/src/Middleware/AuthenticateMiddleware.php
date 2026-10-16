<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Exceptions\Auth\AuthenticationException;
use Tavaan\Engine\Auth\AuthManager;
use Tavaan\Engine\Auth\Guards\SessionGuard;

/**
 * تَوان | Tavaan — Auth
 * AuthenticateMiddleware — محافظ روت‌ها: `middleware: auth` یا `auth:api`.
 *
 * وب → هدایت به login (یا ۴۰۱ برای JSON)؛ API → ۴۰۱ خالص.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class AuthenticateMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        $guardName = $params[0] ?? null;
        $guard = $this->auth->guard($guardName);

        if ($guard->check()) {
            return $next($request);
        }

        // درخواست JSON → ۴۰۱ خالص
        if (\method_exists($request, 'expectsJson') && $request->expectsJson()) {
            throw new AuthenticationException(guards: [$guardName ?? $this->auth->getDefaultGuard()]);
        }

        // وب → redirect به مسیر login با ذخیرهٔ previous url
        if ($guard instanceof SessionGuard) {
            $redirectTo = (string) ($this->redirectTo($request) ?? '/login');
            $response = new \Tavaan\Engine\Http\Response('', 302, ['Location' => $redirectTo]);

            return $response;
        }

        throw new AuthenticationException(guards: [$guardName ?? $this->auth->getDefaultGuard()]);
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
    }

    private function redirectTo(RequestInterface $request): ?string
    {
        if (\method_exists($request, 'session')) {
            $session = $request->session();
            if ($session !== null && \method_exists($session, 'put')) {
                $session->put('url.intended', \method_exists($request, 'fullUrl') ? $request->fullUrl() : '/');
            }
        }

        return '/login';
    }
}
