<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostAdminController — مدیریت مقالات (DOC-091 §3: /admin/blog/posts).
 */

namespace Modules\Blog\Http\Controllers\Admin;

use Modules\Blog\Application\UseCases\PostWriteService;
use Modules\Blog\Domain\Entities\Post;
use Modules\Blog\Domain\Repositories\CategoryRepositoryInterface;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Blog\Domain\Repositories\TagRepositoryInterface;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * PostAdminController — CRUD مدیریت با Gate/Policy (DOC-091 §4 + DoD §1.4).
 *
 * - author: فقط مقالات خود (فیلتر author_id + policy مالکیت)
 * - editor/admin: همهٔ مقالات + انتشار
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class PostAdminController
{
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly PostWriteService $write,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TagRepositoryInterface $tags,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** GET /admin/blog/posts */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('blog.post.view');

        $page = \max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        $perPage = 15;

        $filters = ['with_trashed' => false];
        if ($status !== '' && \in_array($status, ['draft', 'published', 'scheduled', 'archived'], true)) {
            $filters['status'] = $status;
        }
        if (!$this->isEditor()) {
            $filters['author_id'] = (int) Auth::id();   // author → فقط مقالات خود
        }

        $result = $this->posts->paginate($filters, $page, $perPage);

        $authorIds = [];
        foreach ($result['rows'] as $post) {
            $authorIds[] = (string) $post->authorId();
        }

        return view('blog::admin.posts-index', [
            'posts' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'authors' => $authorIds === [] ? [] : $this->users->findByIds($authorIds),
            'isEditor' => $this->isEditor(),
        ]);
    }

    /** GET /admin/blog/posts/create */
    public function create(): mixed
    {
        Gate::authorize('blog.post.create');

        return view('blog::admin.post-form', [
            'post' => null,
            'categories' => $this->categories->all(),
            'postTags' => [],
            'errors' => Session::pull('blog_post_errors') ?? [],
            'old' => Session::pull('blog_post_old') ?? [],
        ]);
    }

    /** POST /admin/blog/posts */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('blog.post.create');

        $authorId = (int) Auth::id();
        $input = $this->postInput($request);

        try {
            $post = $this->write->create($input, $authorId, $this->parseTags($request));
        } catch (ValidationException $e) {
            Session::flash('blog_post_errors', $e->errors);
            Session::flash('blog_post_old', $input);

            return redirect('/admin/blog/posts/create');
        }

        Session::flash('blog_post_ok', trans('blog::messages.post_created'));

        return redirect('/admin/blog/posts/' . $post->id() . '/edit');
    }

    /** GET /admin/blog/posts/{id}/edit */
    public function edit(string $id): mixed
    {
        $post = $this->findOr404($id);
        Gate::authorize('update', $post);

        return view('blog::admin.post-form', [
            'post' => $post,
            'categories' => $this->categories->all(),
            'postTags' => \array_map(
                static fn ($tag): string => $tag->name(),
                $this->tags->forPost($post->id()),
            ),
            'errors' => Session::pull('blog_post_errors') ?? [],
            'old' => Session::pull('blog_post_old') ?? [],
        ]);
    }

    /** POST /admin/blog/posts/{id} (PUT) */
    public function update(RequestInterface $request, string $id): mixed
    {
        $post = $this->findOr404($id);
        Gate::authorize('update', $post);

        $input = $this->postInput($request);

        try {
            $this->write->update($post, $input, $this->parseTags($request));
        } catch (ValidationException $e) {
            Session::flash('blog_post_errors', $e->errors);
            Session::flash('blog_post_old', $input);

            return redirect('/admin/blog/posts/' . $id . '/edit');
        }

        Session::flash('blog_post_ok', trans('blog::messages.post_updated'));

        return redirect('/admin/blog/posts/' . $id . '/edit');
    }

    /** POST /admin/blog/posts/{id}/publish */
    public function publish(RequestInterface $request, string $id): mixed
    {
        $post = $this->findOr404($id);
        Gate::authorize('publish', $post);

        $at = (string) $request->input('published_at', '');
        if ($at !== '' && $post->status()->canTransitionTo(ContentStatus::Scheduled)) {
            try {
                $this->write->schedule($post, new \DateTimeImmutable($at, new \DateTimeZone('UTC')));
                Session::flash('blog_post_ok', trans('blog::messages.post_scheduled'));

                return redirect('/admin/blog/posts/' . $id . '/edit');
            } catch (\Exception) {
                // تاریخ نامعتبر → انتشار فوری در ادامه
            }
        }

        $this->write->publish($post);
        Session::flash('blog_post_ok', trans('blog::messages.post_published'));

        return redirect('/admin/blog/posts/' . $id . '/edit');
    }

    /** POST /admin/blog/posts/{id}/archive */
    public function archive(string $id): mixed
    {
        $post = $this->findOr404($id);
        Gate::authorize('blog.post.publish');   // هم‌سطح انتشار

        $this->write->archive($post);
        Session::flash('blog_post_ok', trans('blog::messages.post_archived'));

        return redirect('/admin/blog/posts/' . $id . '/edit');
    }

    /** DELETE /admin/blog/posts/{id} */
    public function destroy(string $id): mixed
    {
        $post = $this->findOr404($id);
        Gate::authorize('delete', $post);

        $this->write->trash($post);
        Session::flash('blog_post_ok', trans('blog::messages.post_deleted'));

        return redirect('/admin/blog/posts');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function isEditor(): bool
    {
        return Gate::allows('blog.post.update');
    }

    private function findOr404(string $id): Post
    {
        $post = $this->posts->findById($id);
        if ($post === null || $post->isTrashed()) {
            throw new RouteNotFoundException(messageId: 'blog::messages.post_not_found', context: ['id' => $id]);
        }

        return $post;
    }

    /** @return array<string, mixed> */
    private function postInput(RequestInterface $request): array
    {
        return [
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'body' => $request->input('body'),
            'excerpt' => $request->input('excerpt'),
            'category_id' => $request->input('category_id'),
            'featured_image' => $request->input('featured_image'),
            'seo_title' => $request->input('seo_title'),
            'seo_description' => $request->input('seo_description'),
            'seo_keywords' => $request->input('seo_keywords'),
            'status' => $request->input('status', 'draft'),
            'published_at' => $request->input('published_at'),
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
}
