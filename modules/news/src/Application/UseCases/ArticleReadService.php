<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleReadService — خواندن کش‌شده (DOC-092 §5: کش بحرانی است).
 */

namespace Modules\News\Application\UseCases;

use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Comment\Domain\ValueObjects\ThreadNode;
use Modules\Content\Domain\Services\JalaliMonth;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Contracts\UserSnapshot;
use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Entities\NewsTag;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Domain\Repositories\NewsTagRepositoryInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Search;

/**
 * ArticleReadService — فهرست/نمایش/breaking/آرشیو/related با کش تگ‌محور.
 *
 * استراتژی DOC-092 §5: فهرست‌ها TTL کوتاه (۶۰s) + invalidate با tag در
 * هر نوشتن؛ صفحهٔ خبر TTL بلندتر (۳۰۰s) + ETag در کنترلر؛ breaking کش
 * خیلی کوتاه (۱۰s).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class ArticleReadService
{
    /**
     * @param array<string, mixed> $config کلید config('news')
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly NewsTagRepositoryInterface $tags,
        private readonly CommentStoreInterface $comments,
        private readonly CommentThreadBuilder $threadBuilder,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    /**
     * فهرست منتشرشده‌ها با فیلتر (کش‌شده).
     *
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<Article>, total: int, page: int, perPage: int, authors: array<string, UserSnapshot>, tags: array<string, list<NewsTag>>}
     */
    public function listPublished(array $filters = [], int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) ($this->config['per_page'] ?? 12);
        $page = \max(1, $page);
        $filters['status'] = 'published';

        $result = $this->remember('list:' . $this->hashFilters($filters) . ':' . $page . ':' . $perPage, (int) ($this->config['cache']['list_ttl'] ?? 60), function () use ($filters, $page, $perPage): array {
            $data = $this->articles->paginate($filters, $page, $perPage);
            $data['rows'] = \array_map(static fn (Article $a): array => $a->toRow(), $data['rows']);

            return $data;
        });

        $rows = \array_map(static fn (array $row): Article => Article::fromRow($row), (array) ($result['rows'] ?? []));

        return [...$this->decorate($rows), 'rows' => $rows, 'total' => (int) ($result['total'] ?? 0), 'page' => $page, 'perPage' => $perPage];
    }

    /** آرشیو بر اساس ماه جلالی. */
    public function archive(int $jalaliYear, int $jalaliMonth, int $page = 1, ?int $perPage = null): array
    {
        $month = new JalaliMonth($jalaliYear, $jalaliMonth);

        $result = $this->listPublished([
            'published_from' => $month->start()->format('Y-m-d H:i:s.v'),
            'published_to' => $month->endExclusive()->format('Y-m-d H:i:s.v'),
        ], $page, $perPage);

        return [...$result, 'month' => $month];
    }

    /**
     * خبر فوری (breaking) — کش خیلی کوتاه (DOC-092 §5).
     *
     * @return list<Article>
     */
    public function breaking(int $limit = 5): array
    {
        $rows = $this->remember('breaking:' . $limit, (int) ($this->config['cache']['breaking_ttl'] ?? 10), function () use ($limit): array {
            return \array_map(
                static fn (Article $a): array => $a->toRow(),
                $this->articles->latestPublished($limit, onlyBreaking: true),
            );
        });

        return \array_map(static fn (array $row): Article => Article::fromRow($row), (array) $rows);
    }

    /** جست‌وجوی فارسی (ایندکس search + fallback LIKE). */
    public function search(string $query, int $page = 1, ?int $perPage = null): array
    {
        $query = \trim($query);
        if ($query === '') {
            return $this->listPublished([], $page, $perPage);
        }

        $index = (string) ($this->config['search']['index'] ?? 'news_articles');
        $ids = [];

        if ((bool) ($this->config['search']['enabled'] ?? true)) {
            try {
                $searchResult = Search::search($index, $query, ['limit' => 100]);
                foreach ((array) (\method_exists($searchResult, 'hits') ? $searchResult->hits() : []) as $hit) {
                    $id = \is_array($hit) ? ($hit['id'] ?? null) : null;
                    if (\is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }
                }
            } catch (\Throwable) {
                $ids = [];
            }
        }

        if ($ids !== []) {
            $rows = [];
            foreach ($ids as $id) {
                $article = $this->articles->findById($id);
                if ($article !== null && $article->isPublished()) {
                    $rows[] = $article;
                }
            }

            return [...$this->decorate($rows), 'rows' => $rows, 'total' => \count($rows), 'page' => 1, 'perPage' => $perPage ?? (int) ($this->config['per_page'] ?? 12)];
        }

        return $this->listPublished(['search' => $query], $page, $perPage);
    }

    /** نمایش یک خبر منتشرشده (+ شمارش بازدید خارج از کش). */
    public function showPublished(string $slug, bool $countView = true): Article
    {
        $article = $this->articles->findBySlug($slug);
        if ($article === null || !$article->isPublished()) {
            throw RouteNotFoundException::forPath('GET', '/news/' . $slug);
        }

        if ($countView) {
            $article->incrementViews();
            $this->articles->save($article);
        }

        return $article;
    }

    /** اخبار مرتبط (DOC-092 §6) — کش کوتاه. @return list<Article> */
    public function related(Article $article, int $limit = 4): array
    {
        $rows = $this->remember('related:' . $article->id() . ':' . $limit, (int) ($this->config['cache']['list_ttl'] ?? 60), function () use ($article, $limit): array {
            return \array_map(
                static fn (Article $a): array => $a->toRow(),
                $this->articles->related($article, $limit),
            );
        });

        return \array_map(static fn (array $row): Article => Article::fromRow($row), (array) $rows);
    }

    /** رشتهٔ نظرات تأییدشده. @return list<ThreadNode> */
    public function approvedCommentThread(Article $article): array
    {
        $comments = $this->comments->forSubject($article->commentableType(), $article->commentableId(), CommentStatus::Approved);

        return $this->threadBuilder->build($comments);
    }

    public function authorOf(Article $article): ?UserSnapshot
    {
        return $this->users->findById($article->authorId());
    }

    public function editorOf(Article $article): ?UserSnapshot
    {
        return $article->editorId() === null ? null : $this->users->findById($article->editorId());
    }

    /** @return list<NewsTag> */
    public function tagsOf(Article $article): array
    {
        return $this->tags->forArticle($article->id());
    }

    /** ETag محتوایی صفحهٔ خبر (بدون view_count — AC-NEWS-07). */
    public function etagFor(Article $article): string
    {
        $material = \implode('|', [
            $article->id(),
            $article->slug(),
            $article->updatedAt()?->format('Y-m-d H:i:s.v') ?? '',
            $article->publishedAt()?->format('Y-m-d H:i:s.v') ?? '',
            $article->status()->value,
        ]);

        return '"' . \hash('xxh128', $material) . '"';
    }

    /**
     * بارگذاری دسته‌ای نویسندگان/برچسب‌ها (بدون N+1).
     *
     * @param list<Article> $articles
     *
     * @return array{authors: array<string, UserSnapshot>, tags: array<string, list<NewsTag>>}
     */
    private function decorate(array $articles): array
    {
        $authorIds = [];
        $articleIds = [];
        foreach ($articles as $article) {
            $authorIds[] = (string) $article->authorId();
            $articleIds[] = $article->id();
        }

        return [
            'authors' => $authorIds === [] ? [] : $this->users->findByIds($authorIds),
            'tags' => $articleIds === [] ? [] : $this->tags->forArticles($articleIds),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function hashFilters(array $filters): string
    {
        \ksort($filters);

        return \substr(\hash('xxh128', (string) \json_encode($filters, JSON_UNESCAPED_UNICODE)), 0, 16);
    }

    /**
     * remember با tag «news» در صورت پشتیبانی؛ بدون کش در صورت غیرفعال.
     *
     * @return mixed
     */
    private function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true) || $ttl <= 0) {
            return $callback();
        }

        try {
            return Cache::tags(['news'])->remember('news:' . $key, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();   // درایور بدون tag → بدون کش
        }
    }
}
