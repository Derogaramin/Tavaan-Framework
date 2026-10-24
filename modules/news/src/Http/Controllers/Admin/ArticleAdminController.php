<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleAdminController — مدیریت اخبار + workflow (DOC-092 §3-4).
 */

namespace Modules\News\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\News\Application\UseCases\ArticleWriteService;
use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Modules\News\Domain\Repositories\NewsTagRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ArticleAdminController — CRUD + submit/review/approve/reject/publish/breaking.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class ArticleAdminController
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ArticleWriteService $write,
        private readonly NewsCategoryRepositoryInterface $categories,
        private readonly NewsTagRepositoryInterface $tags,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** GET /admin/news/articles */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('news.article.view');

        $page = \max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        $perPage = 15;

        $filters = [];
        if ($status !== '' && \in_array($status, ['draft', 'review', 'approved', 'published', 'archived'], true)) {
            $filters['status'] = $status;
        }
        if (!$this->isEditor()) {
            $filters['author_id'] = (int) Auth::id();   // reporter → فقط خبرهای خود
        }

        $result = $this->articles->paginate($filters, $page, $perPage);

        $authorIds = [];
        foreach ($result['rows'] as $article) {
            $authorIds[] = (string) $article->authorId();
            if ($article->editorId() !== null) {
                $authorIds[] = (string) $article->editorId();
            }
        }

        return view('news::admin.articles-index', [
            'articles' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'people' => $authorIds === [] ? [] : $this->users->findByIds(\array_values(\array_unique($authorIds))),
            'isEditor' => $this->isEditor(),
            'canPublish' => Gate::allows('news.article.publish'),
        ]);
    }

    /** GET /admin/news/articles/create */
    public function create(): mixed
    {
        Gate::authorize('news.article.create');

        return view('news::admin.article-form', [
            'article' => null,
            'categories' => $this->categories->all(),
            'articleTags' => [],
            'errors' => Session::pull('news_article_errors') ?? [],
            'old' => Session::pull('news_article_old') ?? [],
        ]);
    }

    /** POST /admin/news/articles */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('news.article.create');

        try {
            $article = $this->write->create($this->articleInput($request), (int) Auth::id(), $this->parseTags($request));
        } catch (ValidationException $e) {
            Session::flash('news_article_errors', $e->errors);
            Session::flash('news_article_old', $this->articleInput($request));

            return redirect('/admin/news/articles/create');
        }

        Session::flash('news_article_ok', trans('news::messages.article_created'));

        return redirect('/admin/news/articles/' . $article->id() . '/edit');
    }

    /** GET /admin/news/articles/{id}/edit */
    public function edit(string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('update', $article);

        return view('news::admin.article-form', [
            'article' => $article,
            'categories' => $this->categories->all(),
            'articleTags' => \array_map(
                static fn ($tag): string => $tag->name(),
                $this->tags->forArticle($article->id()),
            ),
            'errors' => Session::pull('news_article_errors') ?? [],
            'old' => Session::pull('news_article_old') ?? [],
            'canApprove' => Gate::allows('approve', $article),
            'canPublish' => Gate::allows('publish', $article),
            'canSubmit' => Gate::allows('submit', $article),
            'canBreaking' => Gate::allows('breaking', $article),
        ]);
    }

    /** POST /admin/news/articles/{id} */
    public function update(RequestInterface $request, string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('update', $article);

        try {
            $this->write->update($article, $this->articleInput($request), $this->parseTags($request));
        } catch (ValidationException $e) {
            Session::flash('news_article_errors', $e->errors);
            Session::flash('news_article_old', $this->articleInput($request));

            return redirect('/admin/news/articles/' . $id . '/edit');
        }

        Session::flash('news_article_ok', trans('news::messages.article_updated'));

        return redirect('/admin/news/articles/' . $id . '/edit');
    }

    // ─── workflow (DOC-092 §3) ────────────────────────────────────────────

    /** POST /admin/news/articles/{id}/submit — نویسنده → بازبینی */
    public function submit(string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('submit', $article);

        $this->write->submitForReview($article);
        Session::flash('news_article_ok', trans('news::messages.article_submitted'));

        return $this->backToEdit($id);
    }

    /** POST /admin/news/articles/{id}/approve — ویراستار (با scheduled_at اختیاری) */
    public function approve(RequestInterface $request, string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('approve', $article);

        $scheduledAt = $this->parseDate($request->input('scheduled_at'));
        $this->write->approve($article, (int) Auth::id(), $scheduledAt);

        Session::flash('news_article_ok', $scheduledAt !== null
            ? trans('news::messages.article_scheduled')
            : trans('news::messages.article_approved'));

        return $this->backToEdit($id);
    }

    /** POST /admin/news/articles/{id}/reject — ویراستار (با دلیل) */
    public function reject(RequestInterface $request, string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('reject', $article);

        $reason = $request->input('reason');
        $this->write->reject($article, (int) Auth::id(), \is_string($reason) ? $reason : '');
        Session::flash('news_article_ok', trans('news::messages.article_rejected'));

        return $this->backToEdit($id);
    }

    /** POST /admin/news/articles/{id}/publish — سردبیر */
    public function publish(string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('publish', $article);

        try {
            $this->write->publish($article, (int) Auth::id());
        } catch (\DomainException $e) {
            Session::flash('news_article_error', $e->getMessage());

            return $this->backToEdit($id);
        }
        Session::flash('news_article_ok', trans('news::messages.article_published'));

        return $this->backToEdit($id);
    }

    /** POST /admin/news/articles/{id}/breaking — خبر فوری (toggle) */
    public function breaking(RequestInterface $request, string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('breaking', $article);

        $value = $request->input('value');
        $breaking = $value === null ? !$article->isBreaking() : (bool) $value;
        $this->write->setBreaking($article, $breaking);

        Session::flash('news_article_ok', $breaking
            ? trans('news::messages.marked_breaking')
            : trans('news::messages.unmarked_breaking'));

        return $this->backToEdit($id);
    }

    /** POST /admin/news/articles/{id}/archive */
    public function archive(string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('news.article.publish');   // هم‌سطح انتشار

        $this->write->archive($article);
        Session::flash('news_article_ok', trans('news::messages.article_archived'));

        return $this->backToEdit($id);
    }

    /** DELETE /admin/news/articles/{id} */
    public function destroy(string $id): mixed
    {
        $article = $this->findOr404($id);
        Gate::authorize('delete', $article);

        $this->write->trash($article);
        Session::flash('news_article_ok', trans('news::messages.article_deleted'));

        return redirect('/admin/news/articles');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function isEditor(): bool
    {
        return Gate::allows('news.article.update');
    }

    private function findOr404(string $id): Article
    {
        $article = $this->articles->findById($id);
        if ($article === null || $article->isTrashed()) {
            throw new RouteNotFoundException(messageId: 'news::messages.article_not_found', context: ['id' => $id]);
        }

        return $article;
    }

    private function backToEdit(string $id): mixed
    {
        return redirect('/admin/news/articles/' . $id . '/edit');
    }

    /** @return array<string, mixed> */
    private function articleInput(RequestInterface $request): array
    {
        return [
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'lead' => $request->input('lead'),
            'body' => $request->input('body'),
            'category_id' => $request->input('category_id'),
            'featured_image' => $request->input('featured_image'),
            'gallery' => $request->input('gallery'),
            'seo_title' => $request->input('seo_title'),
            'seo_description' => $request->input('seo_description'),
        ];
    }

    /** @return list<string> */
    private function parseTags(RequestInterface $request): array
    {
        $raw = $request->input('tags', '');
        if (\is_array($raw)) {
            return \array_values(\array_filter(\array_map('strval', $raw), static fn (string $t): bool => \trim($t) !== ''));
        }
        if (!\is_string($raw) || \trim($raw) === '') {
            return [];
        }

        $parts = \preg_split('/[,،]/u', $raw) ?: [];

        return \array_values(\array_filter(\array_map(
            static fn (string $t): string => \trim($t),
            $parts,
        ), static fn (string $t): bool => $t !== ''));
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || \trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
