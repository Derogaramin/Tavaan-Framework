<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsController — صفحات عمومی پرتال خبری (DOC-092 §5-7).
 */

namespace Modules\News\Http\Controllers;

use Modules\Content\Domain\Services\JalaliMonth;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\News\Application\UseCases\ArticleReadService;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Modules\News\Domain\Repositories\NewsTagRepositoryInterface;
use Modules\News\Domain\Services\JsonLdBuilder;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;

/**
 * NewsController — فهرست/دسته/برچسب/آرشیو/نمایش + ETag/304 + breaking.
 *
 * 🔒 AC-NEWS-07: صفحهٔ خبر با کش (۳۰۰s) + ETag — درخواست تکراری ۳۰۴
 * بدون رندر بدنه. پاسخ‌ها از `response.factory` (هدرهای دقیق).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class NewsController
{
    public function __construct(
        private readonly ArticleReadService $read,
        private readonly NewsCategoryRepositoryInterface $categories,
        private readonly NewsTagRepositoryInterface $tags,
        private readonly JsonLdBuilder $jsonLd,
        private readonly UserReaderInterface $users,
        private readonly MediaGatewayInterface $media,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /news — فهرست + breaking banner + جست‌وجو (?q=) */
    public function index(RequestInterface $request): mixed
    {
        $page = $this->page($request);
        $query = \trim((string) $request->query('q', ''));

        $result = $query !== ''
            ? $this->read->search($query, $page)
            : $this->read->listPublished([], $page);

        return $this->respond('news::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'breaking' => $this->read->breaking($this->breakingLimit()),
            'query' => $query,
            'heading' => $query !== ''
                ? trans('news::messages.search_results', ['q' => $query])
                : trans('news::messages.latest_news'),
        ]);
    }

    /** GET /news/breaking — JSON خبرهای فوری (برای widget/realtime). */
    public function breaking(): mixed
    {
        $breaking = $this->read->breaking($this->breakingLimit());

        return [
            'data' => \array_map(static fn ($a): array => [
                'id' => $a->id(),
                'title' => $a->title(),
                'slug' => $a->slug(),
                'lead' => $a->lead(),
                'url' => '/news/' . $a->slug(),
                'published_at' => $a->publishedAt()?->format(\DateTimeInterface::ATOM),
            ], $breaking),
        ];
    }

    /** GET /news/category/{slug} — با زیردسته‌ها (DOC-092 §2: parent_id). */
    public function category(RequestInterface $request, string $slug): mixed
    {
        $category = $this->categories->findBySlug($slug);
        if ($category === null || $category->id() === null) {
            throw RouteNotFoundException::forPath('GET', '/news/category/' . $slug);
        }

        $result = $this->read->listPublished([
            'category_ids' => $this->categories->withDescendants($category->id()),
        ], $this->page($request));

        return $this->respond('news::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'breaking' => $this->read->breaking(),
            'query' => '',
            'heading' => $category->title(),
            'headingDescription' => $category->description(),
        ]);
    }

    /** GET /news/tag/{slug} */
    public function tag(RequestInterface $request, string $slug): mixed
    {
        $tag = $this->tags->findBySlug($slug);
        if ($tag === null || $tag->id() === null) {
            throw RouteNotFoundException::forPath('GET', '/news/tag/' . $slug);
        }

        $result = $this->read->listPublished(['tag_id' => $tag->id()], $this->page($request));

        return $this->respond('news::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'breaking' => $this->read->breaking(),
            'query' => '',
            'heading' => trans('news::messages.tag_heading', ['tag' => $tag->name()]),
        ]);
    }

    /** GET /news/archive/{year}/{month} — آرشیو جلالی. */
    public function archive(RequestInterface $request, string $year, string $month): mixed
    {
        try {
            $jalali = JalaliMonth::fromRoute($year, $month);
        } catch (\OutOfRangeException) {
            throw RouteNotFoundException::forPath('GET', '/news/archive/' . $year . '/' . $month);
        }

        $result = $this->read->archive($jalali->year, $jalali->month, $this->page($request));

        return $this->respond('news::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'breaking' => $this->read->breaking(),
            'query' => '',
            'heading' => trans('news::messages.archive_heading', ['month' => $jalali->labelFa()]),
        ]);
    }

    /** GET /news/{slug} — صفحهٔ خبر با ETag/304 + JSON-LD + related. */
    public function show(RequestInterface $request, string $slug): mixed
    {
        $article = $this->read->showPublished($slug);

        $etag = $this->read->etagFor($article);
        $ifNoneMatch = (string) ($request->header('If-None-Match') ?? '');
        if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
            return $this->factory()->make('', 304, ['ETag' => $etag]);
        }

        $author = $this->read->authorOf($article);
        $imageUrl = $this->resolveMediaUrl($article->featuredImage());
        $canonical = '/news/' . $article->slug();

        return $this->respond('news::show', [
            'article' => $article,
            'author' => $author,
            'editor' => $this->read->editorOf($article),
            'tags' => $this->read->tagsOf($article),
            'related' => $this->read->related($article),
            'thread' => $this->read->approvedCommentThread($article),
            'categories' => $this->categories->all(),
            'breaking' => $this->read->breaking(),
            'commentsEnabled' => $article->commentsOpen(),
            'featuredImageUrl' => $imageUrl,
            'galleryUrls' => $this->resolveGallery($article->gallery()),
            'jsonLd' => $this->jsonLd->forArticle($article, $author, $canonical, $imageUrl),
            'canonical' => $canonical,
            'etag' => $etag,
            'flash' => \Tavaan\Core\Facade\Facades\Session::pull('news_comment'),
        ], ['ETag' => $etag]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * رندر ویو با هدرهای سفارشی (response.factory — بدون ارجاع به موتور).
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    private function respond(string $template, array $data, array $headers = []): mixed
    {
        return $this->factory()->view($template, $data, 200, $headers);
    }

    private function factory(): mixed
    {
        return $this->container->make('response.factory');
    }

    private function page(RequestInterface $request): int
    {
        return \max(1, (int) $request->query('page', 1));
    }

    private function breakingLimit(): int
    {
        $config = (array) (\function_exists('config') ? config('news', []) : []);

        return (int) ($config['breaking']['limit'] ?? 5);
    }

    private function resolveMediaUrl(?string $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        return $this->media->find($mediaId)?->url;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function resolveGallery(array $ids): array
    {
        $urls = [];
        foreach ($ids as $id) {
            $url = $this->resolveMediaUrl($id);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
