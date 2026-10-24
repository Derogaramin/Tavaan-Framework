<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * FeedController — RSS/Atom (DOC-091 §3: GET /blog/feed).
 */

namespace Modules\Blog\Http\Controllers;

use Modules\Blog\Application\UseCases\FeedService;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;

/**
 * FeedController — خروجی XML با Content-Type صحیح از راه
 * binding کانتینر `response.factory` (بدون ارجاع به کلاس موتور — ARCH-003).
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class FeedController
{
    public function __construct(
        private readonly FeedService $feeds,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /blog/feed?format=rss|atom */
    public function __invoke(RequestInterface $request): mixed
    {
        $siteUrl = $this->siteUrl($request);
        $format = \strtolower((string) $request->query('format', 'rss'));

        if ($format === 'atom') {
            return $this->respond(
                $this->feeds->atom($siteUrl),
                'application/atom+xml; charset=UTF-8',
            );
        }

        return $this->respond(
            $this->feeds->rss($siteUrl),
            'application/rss+xml; charset=UTF-8',
        );
    }

    private function respond(string $xml, string $contentType): mixed
    {
        $factory = $this->container->make('response.factory');

        return $factory->make($xml, 200, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
