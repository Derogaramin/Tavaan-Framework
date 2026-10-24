<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * FeedController — RSS/Atom اخبار (DOC-092 §6).
 */

namespace Modules\News\Http\Controllers;

use Modules\News\Application\UseCases\FeedService;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class FeedController
{
    public function __construct(
        private readonly FeedService $feeds,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /news/feed?format=rss|atom */
    public function __invoke(RequestInterface $request): mixed
    {
        $siteUrl = $this->siteUrl($request);
        $format = \strtolower((string) $request->query('format', 'rss'));

        $factory = $this->container->make('response.factory');

        if ($format === 'atom') {
            return $factory->make($this->feeds->atom($siteUrl), 200, ['Content-Type' => 'application/atom+xml; charset=UTF-8']);
        }

        return $factory->make($this->feeds->rss($siteUrl), 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
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
