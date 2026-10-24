<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * SitemapController — sitemap خودکار اخبار (DOC-092 §6).
 */

namespace Modules\News\Http\Controllers;

use Modules\News\Application\UseCases\SitemapService;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class SitemapController
{
    public function __construct(
        private readonly SitemapService $sitemaps,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /news/sitemap.xml */
    public function __invoke(RequestInterface $request): mixed
    {
        $config = (array) (\function_exists('config') ? config('news', []) : []);
        if (!(bool) ($config['seo']['sitemap_enabled'] ?? true)) {
            throw new RouteNotFoundException(messageId: 'http.route.not_found');
        }

        $factory = $this->container->make('response.factory');

        $uri = $request->uri();
        $scheme = \method_exists($uri, 'scheme') ? (string) $uri->scheme() : 'http';
        $host = \method_exists($uri, 'host') ? (string) $uri->host() : 'localhost';

        return $factory->make(
            $this->sitemaps->xml($scheme . '://' . $host),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
