<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * AmpController — خروجی AMP خبر (DOC-092 §6: AMP output).
 */

namespace Modules\News\Http\Controllers;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\News\Application\UseCases\ArticleReadService;
use Modules\News\Domain\Services\JsonLdBuilder;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;

/**
 * AmpController — صفحهٔ AMP معتبر (ساختاری): `<html ⚡>`، canonical،
 * boilerplate، amp-img و JSON-LD. تبدیل <img> بدنه به amp-img در ویو.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class AmpController
{
    public function __construct(
        private readonly ArticleReadService $read,
        private readonly JsonLdBuilder $jsonLd,
        private readonly UserReaderInterface $users,
        private readonly MediaGatewayInterface $media,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /news/{slug}/amp */
    public function __invoke(RequestInterface $request, string $slug): mixed
    {
        $article = $this->read->showPublished($slug, countView: false);
        $author = $this->read->authorOf($article);

        $imageUrl = null;
        if ($article->featuredImage() !== null) {
            $imageUrl = $this->media->find($article->featuredImage())?->url;
        }

        $uri = $request->uri();
        $scheme = \method_exists($uri, 'scheme') ? (string) $uri->scheme() : 'http';
        $host = \method_exists($uri, 'host') ? (string) $uri->host() : 'localhost';
        $canonical = $scheme . '://' . $host . '/news/' . $article->slug();

        $factory = $this->container->make('response.factory');

        return $factory->view('news::amp.article', [
            'article' => $article,
            'author' => $author,
            'canonical' => $canonical,
            'imageUrl' => $imageUrl,
            'jsonLd' => $this->jsonLd->forArticle($article, $author, $canonical, $imageUrl),
        ], 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Frame-Options' => 'SAMEORIGIN']);
    }
}
