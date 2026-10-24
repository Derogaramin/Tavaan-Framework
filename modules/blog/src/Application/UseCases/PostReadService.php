<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostReadService — مسیر خواندن: فهرست/نمایش/آرشیو/جست‌وجو/بازدید.
 */

namespace Modules\Blog\Application\UseCases;

use Modules\Blog\Domain\Entities\Post;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Blog\Domain\Entities\Tag;
use Modules\Blog\Domain\Repositories\TagRepositoryInterface;
use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Comment\Domain\ValueObjects\ThreadNode;
use Modules\Content\Domain\Services\JalaliMonth;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Contracts\UserSnapshot;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Search;

/**
 * PostReadService — خواندن با ترکیب قراردادهای ماژول‌ها (UserReader/CommentStore).
 *
 * 🔒 بدون N+1: نویسندگان و برچسب‌ها به‌صورت دسته‌ای (batch) بارگذاری می‌شوند.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class PostReadService
{
    /**
     * @param array<string, mixed> $config کلید config('blog')
     */
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly TagRepositoryInterface $tags,
        private readonly CommentStoreInterface $comments,
        private readonly CommentThreadBuilder $threadBuilder,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    /**
     * فهرست منتشرشده‌ها با فیلتر اختیاری (دسته/برچسب/آرشیو جلالی/جست‌وجو).
     *
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<Post>, total: int, page: int, perPage: int, authors: array<string, UserSnapshot>, tags: array<string, list<Tag>>}
     */
    public function listPublished(array $filters = [], int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) ($this->config['per_page'] ?? 10);
        $filters['status'] = 'published';

        $result = $this->posts->paginate($filters, $page, $perPage);

        return [...$result, ...$this->decorate($result['rows']), 'page' => \max(1, $page), 'perPage' => $perPage];
    }

    /**
     * آرشیو بر اساس ماه جلالی (DOC-091 §3 + E2E-05).
     *
     * @return array{rows: list<Post>, total: int, page: int, perPage: int, authors: array<string, UserSnapshot>, tags: array<string, list<Tag>>, month: JalaliMonth}
     */
    public function archive(int $jalaliYear, int $jalaliMonth, int $page = 1, ?int $perPage = null): array
    {
        $month = new JalaliMonth($jalaliYear, $jalaliMonth);

        $result = $this->listPublished([
            'published_from' => $month->start()->format('Y-m-d H:i:s.v'),
            'published_to' => $month->endExclusive()->format('Y-m-d H:i:s.v'),
        ], $page, $perPage);

        return [...$result, 'month' => $month];
    }

    /** جست‌وجوی فارسی با موتور search (DOC-091 §6 + E2E-03) + fallback روی SQL. */
    public function search(string $query, int $page = 1, ?int $perPage = null): array
    {
        $query = \trim($query);
        if ($query === '') {
            return $this->listPublished([], $page, $perPage);
        }

        $index = (string) ($this->config['search']['index'] ?? 'blog_posts');
        $ids = [];

        if ((bool) ($this->config['search']['enabled'] ?? true)) {
            try {
                $searchResult = Search::search($index, $query, ['limit' => 100]);
                $hits = \method_exists($searchResult, 'hits') ? $searchResult->hits() : [];
                foreach ((array) $hits as $hit) {
                    $id = \is_array($hit) ? ($hit['id'] ?? null) : (\is_object($hit) && \property_exists($hit, 'id') ? $hit->id : null);
                    if (\is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }
                }
            } catch (\Throwable) {
                $ids = [];   // ایندکس خالی/خطا → fallback
            }
        }

        if ($ids !== []) {
            $perPage = $perPage ?? (int) ($this->config['per_page'] ?? 10);
            $rows = [];
            foreach ($ids as $id) {
                $post = $this->posts->findById($id);
                if ($post !== null && $post->isPublished()) {
                    $rows[] = $post;
                }
            }

            return [...$this->decorate($rows), 'rows' => $rows, 'total' => \count($rows), 'page' => 1, 'perPage' => $perPage];
        }

        // fallback — LIKE روی عنوان/خلاصه
        return $this->listPublished(['search' => $query], $page, $perPage);
    }

    /** نمایش یک مقالهٔ منتشرشده با slug (+ شمارش بازدید). */
    public function showPublished(string $slug, bool $countView = true): Post
    {
        $post = $this->posts->findBySlug($slug);
        if ($post === null || !$post->isPublished()) {
            throw RouteNotFoundException::forPath('GET', '/blog/' . $slug);
        }

        if ($countView) {
            $post->incrementViews();
            $this->posts->save($post);
        }

        return $post;
    }

    /** رشتهٔ نظرات تأییدشدهٔ یک مقاله (nested — DOC-091 §6). @return list<ThreadNode> */
    public function approvedCommentThread(Post $post): array
    {
        $comments = $this->comments->forSubject($post->commentableType(), $post->commentableId(), CommentStatus::Approved);

        return $this->threadBuilder->build($comments);
    }

    public function commentStore(): CommentStoreInterface
    {
        return $this->comments;
    }

    /** نویسندهٔ یک مقاله (snapshot). */
    public function authorOf(Post $post): ?UserSnapshot
    {
        return $this->users->findById($post->authorId());
    }

    /** برچسب‌های یک مقاله. @return list<Tag> */
    public function tagsOf(Post $post): array
    {
        return $this->tags->forPost($post->id());
    }

    /**
     * بارگذاری دسته‌ای نویسندگان/برچسب‌ها برای فهرست (بدون N+1).
     *
     * @param list<Post> $posts
     *
     * @return array{authors: array<string, UserSnapshot>, tags: array<string, list<Tag>>}
     */
    private function decorate(array $posts): array
    {
        $authorIds = [];
        $postIds = [];
        foreach ($posts as $post) {
            $authorIds[] = (string) $post->authorId();
            $postIds[] = $post->id();
        }

        return [
            'authors' => $authorIds === [] ? [] : $this->users->findByIds($authorIds),
            'tags' => $postIds === [] ? [] : $this->tags->forPosts($postIds),
        ];
    }
}
