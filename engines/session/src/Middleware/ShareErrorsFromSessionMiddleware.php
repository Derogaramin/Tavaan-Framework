<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Middleware;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Request;

/**
 * تَوان | Tavaan — Session
 * ShareErrorsFromSessionMiddleware — flash `errors` → اشتراک با همهٔ ویوها.
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class ShareErrorsFromSessionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if ($request instanceof Request && $request->hasSession() && $this->container->bound('view')) {
            /** @var \Tavaan\Engine\Session\Session|null $session */
            $session = $request->session();
            $errors = $session?->get('errors', []);
            /** @var \Tavaan\Engine\View\ViewFactory $view */
            $view = $this->container->make('view');
            $view->share('errors', \is_array($errors) ? $errors : []);
        }

        return $next($request);
    }
}
