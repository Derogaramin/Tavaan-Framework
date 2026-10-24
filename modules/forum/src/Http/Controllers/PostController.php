<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostController — پاسخ، ویرایش، رأی، برگزیده، flag (DOC-094 §3).
 */

namespace Modules\Forum\Http\Controllers;

use Modules\Forum\Application\UseCases\PostService;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * PostController — نوشتن/رأی/برگزیده/گزارش پست.
 *
 * همهٔ اکشن‌های نوشتن احراز هویت می‌خواهند (middleware) و مجوز ریز
 * با Gate/Policy بررسی می‌شود (DOC-094 §4).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class PostController
{
    public function __construct(
        private readonly PostService $postService,
        private readonly PostRepositoryInterface $posts,
        private readonly ThreadRepositoryInterface $threads,
    ) {
    }

    /** POST /threads/{id}/posts — پاسخ جدید (E2E-01/07). */
    public function store(RequestInterface $request, string $id): mixed
    {
        $thread = $this->threads->findById($id) ?? $this->threads->findBySlug($id);
        if ($thread === null) {
            throw RouteNotFoundException::forPath('POST', '/threads/' . $id . '/posts');
        }

        Gate::authorize('forum.post.create');
        $userId = (int) Auth::id();

        try {
            $this->postService->createPost($thread, $userId, (string) $request->input('body', ''), [
                'parent_id' => $request->input('parent_id'),
                'website' => $request->input('website'),   // honeypot
            ]);
        } catch (ValidationException $e) {
            Session::flash('error', \implode(' — ', \array_map(
                static fn (array $messages): string => (string) ($messages[0] ?? ''),
                $e->errors,
            )));

            return redirect('/threads/' . $thread->slug() . '#reply');
        } catch (ForumException $e) {
            Session::flash('error', $e->getMessage());

            return redirect('/threads/' . $thread->slug() . '#reply');
        }

        Session::flash('status', trans('forum::messages.post_created'));

        return redirect('/threads/' . $thread->slug() . '#posts');
    }

    /** GET /posts/{id}/edit — فرم ویرایش. */
    public function edit(string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('update', $post);

        return view('forum::edit-post', [
            'post' => $post,
            'errors' => Session::pull('forum_post_errors') ?? [],
        ]);
    }

    /** POST /posts/{id} — ذخیرهٔ ویرایش. */
    public function update(RequestInterface $request, string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('update', $post);

        try {
            $this->postService->updatePost($post, (int) Auth::id(), (string) $request->input('body', ''));
        } catch (ValidationException|ForumException $e) {
            Session::flash('forum_post_errors', $e instanceof ValidationException ? $e->errors : ['body' => [$e->getMessage()]]);

            return redirect('/posts/' . $post->id() . '/edit');
        }

        Session::flash('status', trans('forum::messages.post_updated'));

        return redirect($this->threadUrl($post->threadId()) . '#post-' . $post->id());
    }

    /** POST /posts/{id}/vote — رأی +1/-1 با toggle (E2E-02). */
    public function vote(RequestInterface $request, string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('vote', $post);

        $value = (int) $request->input('value', 1);

        try {
            $result = $this->postService->vote($post, (int) Auth::id(), $value);
        } catch (ForumException $e) {
            Session::flash('error', $e->getMessage());

            return redirect($this->threadUrl($post->threadId()) . '#post-' . $post->id());
        }

        if ($this->wantsJson($request)) {
            return ['ok' => true, 'value' => $result['value'], 'score' => $result['score']];
        }

        return redirect($this->threadUrl($post->threadId()) . '#post-' . $post->id());
    }

    /** POST /posts/{id}/mark-solution — پاسخ برگزیده (E2E-03). */
    public function markSolution(RequestInterface $request, string $id): mixed
    {
        $post = $this->findPost($id);
        $thread = $this->threads->findById($post->threadId());
        if ($thread === null) {
            throw RouteNotFoundException::forPath('POST', '/posts/' . $id . '/mark-solution');
        }

        Gate::authorize('solution', $post, $thread);

        $marked = !$post->isSolution();
        $this->postService->markSolution($thread, $post, (int) Auth::id(), $marked);

        Session::flash('status', $marked
            ? trans('forum::messages.solution_marked')
            : trans('forum::messages.solution_unmarked'));

        return redirect('/threads/' . $thread->slug() . '#post-' . $post->id());
    }

    /** POST /posts/{id}/flag — گزارش → صف moderation (E2E-04). */
    public function flag(RequestInterface $request, string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('flag', $post);

        try {
            $this->postService->flag($post, (int) Auth::id(), (string) $request->input('reason', ''));
        } catch (ForumException $e) {
            Session::flash('error', $e->getMessage());

            return redirect($this->threadUrl($post->threadId()) . '#post-' . $post->id());
        }

        Session::flash('status', trans('forum::messages.flagged'));

        return redirect($this->threadUrl($post->threadId()) . '#post-' . $post->id());
    }

    /** GET /posts/{id}/quote — نقل‌قول JSON (برای prefill فرم پاسخ). */
    public function quote(string $id): mixed
    {
        $post = $this->findPost($id);
        if (!$post->isVisible()) {
            throw RouteNotFoundException::forPath('GET', '/posts/' . $id . '/quote');
        }

        return ['quote' => $this->postService->quote($post)];
    }

    private function findPost(string $id): \Modules\Forum\Domain\Entities\Post
    {
        $post = $this->posts->findById($id);
        if ($post === null) {
            throw RouteNotFoundException::forPath('GET', '/posts/' . $id);
        }

        return $post;
    }

    private function threadUrl(string $threadId): string
    {
        $thread = $this->threads->findById($threadId);

        return '/threads/' . ($thread?->slug() ?? $threadId);
    }

    private function wantsJson(RequestInterface $request): bool
    {
        $accept = (string) ($request->header('Accept') ?? '');

        return \str_contains($accept, 'application/json')
            || (string) $request->query('format', '') === 'json';
    }
}
