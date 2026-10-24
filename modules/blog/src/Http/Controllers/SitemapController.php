<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * SitemapController — sitemap.xml وبلاگ (DOC-091 §6: SEO).
 */

namespace Modules\Blog\Http\Controllers;

use Modules\Blog\Application\UseCases\SitemapService;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;

/**
 * @api 1.0
 * @see modules/blog/README.md
 */
final class SitemapController
{
    public function __construct(
        private readonly SitemapService $sitemaps,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /blog/sitemap.xml */
    public function __invoke(RequestInterface $request): mixed
    {
        $config = (array) (\function_exists('config') ? config('blog', []) : []);
        if (!(bool) ($config['seo']['sitemap_enabled'] ?? true)) {
            throw new RouteNotFoundException(messageId: 'http.route.not_found');
        }

        $factory = $this->container->make('response.factory');

        return $factory->make(
            $this->sitemaps->xml($this->siteUrl($request)),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }

    private function siteUrl(RequestInterface $request): string
    {
        $uri = $request->uri();
        $scheme = \method_exists($uri, 'scheme') ? (string) $uri->scheme() : 'http';
        $host = \method_exists($uri, 'host') ? (string) $uri->host() : 'localhost';
        $port = \method_exists($uri, 'port') ? $uri->port() : null;

        $authority = $host . ($port !== null && $port !== '' && !\in_array((int) $port, [80, 443], true) ? ':' . $port : '');

        return $scheme . '://' . $authority;
    }
}
