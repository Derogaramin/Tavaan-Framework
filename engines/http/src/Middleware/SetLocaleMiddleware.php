<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

use Tavaan\Core\Contracts\Http\MiddlewareInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Contracts\I18n\TranslatorInterface;
use Tavaan\Engine\Http\Request;

/**
 * تَوان | Tavaan — Http
 * SetLocaleMiddleware — تعیین locale از session → کاربر → Accept-Language → config.
 *
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
final class SetLocaleMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly array $config = [],
    ) {
    }

    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface
    {
        if ($this->translator === null) {
            return $next($request);
        }

        $available = (array) ($this->config['available'] ?? ['fa', 'en']);
        $default = (string) ($this->config['locale'] ?? 'fa');
        $locale = null;

        // 1) session
        if ($request instanceof Request) {
            $session = $request->session();
            if ($session !== null && \method_exists($session, 'get')) {
                $stored = $session->get('locale');
                if (\is_string($stored) && \in_array($stored, $available, true)) {
                    $locale = $stored;
                }
            }

            // 2) query ?lang=
            $query = $request->query('lang');
            if ($locale === null && \is_string($query) && \in_array($query, $available, true)) {
                $locale = $query;
            }
        }

        // 3) Accept-Language negotiation
        if ($locale === null) {
            $header = (string) ($request->header('Accept-Language') ?? '');
            if ($header !== '' && \method_exists($this->translator, 'negotiate')) {
                $locale = $this->translator->negotiate($header, $available, $default);
            }
        }

        $this->translator->setLocale($locale ?? $default);

        return $next($request);
    }
}
