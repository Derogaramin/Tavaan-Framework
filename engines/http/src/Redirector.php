<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Http\Routing\UrlGenerator;

/**
 * تَوان | Tavaan — Http
 * Redirector — ساخت پاسخ redirect با flash (session نرم) و intended().
 *
 * 🔒 Open Redirect: فقط مسیر نسبی، دامنهٔ اپ، یا allowlist صریح.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class Redirector
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly UrlGenerator $url,
        private readonly array $allowedHosts = [],
    ) {
    }

    public function to(string $path, int $status = 302, array $headers = []): Response
    {
        $this->assertSafe($path);
        $url = \preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path) === 1 ? $path : $this->url->url($path);

        return (new Response('', $status, ['Location' => $url, ...$headers]));
    }

    /** @param array<string, mixed> $parameters */
    public function route(string $name, array $parameters = [], int $status = 302): Response
    {
        return $this->to($this->url->route($name, $parameters, absolute: false), $status);
    }

    public function back(int $status = 302, string $fallback = '/'): Response
    {
        return $this->to($this->url->previous($fallback), $status);
    }

    /** redirect به URL ذخیره‌شده در session (`intended`). */
    public function intended(string $default = '/', int $status = 302): Response
    {
        $session = $this->session();
        $target = $default;
        if ($session !== null && \method_exists($session, 'pull')) {
            $stored = $session->pull('url.intended');
            if (\is_string($stored) && $stored !== '') {
                $target = $stored;
            }
        }

        return $this->to($target, $status);
    }

    /** ذخیرهٔ URL جاری برای intended (توسط middleware احراز هویت). */
    public function setIntendedUrl(string $url): void
    {
        $session = $this->session();
        if ($session !== null && \method_exists($session, 'put')) {
            $session->put('url.intended', $url);
        }
    }

    /** flash ورودی‌ها برای فرم بعدی. @param array<string, mixed> $input */
    public function withInput(array $input): Response
    {
        $this->flash('_old_input', $input);

        return new Response('', 302);
    }

    private function flash(string $key, mixed $value): void
    {
        $session = $this->session();
        if ($session !== null && \method_exists($session, 'flash')) {
            $session->flash($key, $value);
        }
    }

    private function session(): ?object
    {
        if (!$this->container->bound('session')) {
            return null;
        }

        try {
            $session = $this->container->make('session');

            return \is_object($session) ? $session : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertSafe(string $path): void
    {
        if (\str_starts_with($path, '/') && !\str_starts_with($path, '//')) {
            return;
        }

        $host = \parse_url($path, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return;   // نسبی
        }

        $appHost = \parse_url($this->url->url('/'), PHP_URL_HOST);
        if ($host === $appHost || \in_array($host, $this->allowedHosts, true)) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf('Redirect to external host [%s] is not allowed.', $host));
    }
}
