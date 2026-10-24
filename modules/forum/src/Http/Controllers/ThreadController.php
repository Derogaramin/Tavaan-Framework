<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadController — صفحهٔ موضوع + ساخت موضوع + اشتراک (DOC-094 §3).
 */

namespace Modules\Forum\Http\Controllers;

use Modules\Forum\Application\UseCases\ForumReadService;
use Modules\Forum\Application\UseCases\PostService;
use Modules\Forum\Application\UseCases\ProfileService;
use Modules\Forum\Application\UseCases\ThreadService;
use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Modules\Forum\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ThreadController — نمایش موضوع (پست اول + پاسخ‌ها + فرم پاسخ/نقل‌قول)،
 * ساخت موضوع جدید و اشتراک.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ThreadController
{
    public function __construct(
        private readonly ForumReadService $read,
        private readonly ThreadService $threadService,
        private readonly PostService $postService,
        private readonly ProfileService $profiles,
        private readonly ThreadRepositoryInterface $threads,
        private readonly PostRepositoryInterface $posts,
        private readonly ForumRepositoryInterface $forums,
        private readonly VoteRepositoryInterface $votes,
    ) {
    }

    /** GET /threads/{slug} — موضوع + پست‌ها (?quote= و ?page= پشتیبانی می‌شود). */
    public function show(RequestInterface $request, string $slug): mixed
    {
        $thread = $this->resolveThread($slug);
        if ($thread === null) {
            throw RouteNotFoundException::forPath('GET', '/threads/' . $slug);
        }

        $this->threadService->registerView($thread);

        $page = \max(1, (int) $request->query('page', 1));
        $viewerId = $this->viewerId();
        $viewerIsModerator = $viewerId !== null && Gate::allows('forum.post.hide');
        $data = $this->read->threadPage($thread, $page, $viewerId, $viewerIsModerator);

        // وضعیت رأی بیننده برای هر پست
        $myVotes = [];
        if ($viewerId !== null) {
            foreach ($data['result']['rows'] as $post) {
                $myVotes[$post->id()] = $this->votes->currentVote($post->id(), $viewerId);
            }
            if ($data['first'] !== null) {
                $myVotes[$data['first']->id()] = $this->votes->currentVote($data['first']->id(), $viewerId);
            }
        }

        // نقل‌قول (?quote={postId}) — prefill فرم پاسخ (DOC-094 §6)
        $quoteText = '';
        $quoteId = \trim((string) $request->query('quote', ''));
        if ($quoteId !== '') {
            $quoted = $this->posts->findById($quoteId);
            if ($quoted !== null && $quoted->threadId() === $thread->id() && $quoted->isVisible()) {
                $quoteText = $this->postService->quote($quoted);
            }
        }

        $forum = $this->forums->findById($thread->forumId());

        return view('forum::thread', [
            'thread' => $thread,
            'forum' => $forum,
            'first' => $data['first'],
            'posts' => $data['result']['rows'],
            'total' => $data['result']['total'],
            'page' => $data['result']['page'],
            'perPage' => $data['result']['perPage'],
            'names' => $data['names'],
            'usernames' => $data['usernames'],
            'subscribed' => $data['subscribed'],
            'myVotes' => $myVotes,
            'quoteText' => $quoteText,
            'canReply' => $viewerId !== null
                && Gate::allows('forum.post.create')
                && $thread->acceptsReplies()
                && !$this->profiles->isBanned($viewerId),
            'canModerate' => $viewerId !== null && Gate::allows('forum.post.hide'),
            'canSolution' => $viewerId !== null && Gate::allows('forum.post.solution'),
            'isThreadAuthor' => $viewerId !== null && (string) $viewerId === (string) $thread->authorId(),
            'topMembers' => $this->read->topMembers(5),
        ]);
    }

    /** GET /forums/{slug}/new — فرم موضوع جدید. */
    public function create(string $slug): mixed
    {
        $forum = $this->forums->findBySlug($slug);
        if ($forum === null) {
            throw RouteNotFoundException::forPath('GET', '/forums/' . $slug . '/new');
        }

        Gate::authorize('create', $forum);

        return view('forum::new-thread', [
            'forum' => $forum,
            'forums' => $this->forums->all(),
            'errors' => Session::pull('forum_thread_errors') ?? [],
            'old' => Session::pull('forum_thread_old') ?? [],
        ]);
    }

    /** POST /threads — ساخت موضوع (E2E-01). */
    public function store(RequestInterface $request): mixed
    {
        $forumId = (int) $request->input('forum_id', 0);
        $forum = $this->forums->findById($forumId);
        if ($forum === null) {
            throw RouteNotFoundException::forPath('POST', '/threads');
        }

        Gate::authorize('create', $forum);
        $userId = (int) Auth::id();

        try {
            $result = $this->threadService->createThread($forum, $userId, [
                'title' => (string) $request->input('title', ''),
                'body' => (string) $request->input('body', ''),
            ]);
        } catch (ValidationException $e) {
            Session::flash('forum_thread_errors', $e->errors);
            Session::flash('forum_thread_old', ['title' => $request->input('title'), 'body' => $request->input('body')]);

            return redirect('/forums/' . $forum->slug() . '/new');
        } catch (ForumException $e) {
            Session::flash('error', $e->getMessage());

            return redirect('/forums/' . $forum->slug() . '/new');
        }

        Session::flash('status', trans('forum::messages.thread_created'));

        return redirect('/threads/' . $result['thread']->slug());
    }

    /** POST /threads/{id}/subscribe */
    public function subscribe(RequestInterface $request, string $id): mixed
    {
        $thread = $this->resolveThread($id);
        if ($thread === null) {
            throw RouteNotFoundException::forPath('POST', '/threads/' . $id . '/subscribe');
        }

        Gate::authorize('subscribe', $thread);
        $this->threadService->subscribe((int) Auth::id(), $thread);
        Session::flash('status', trans('forum::messages.subscribed'));

        return redirect('/threads/' . $thread->slug());
    }

    /** POST /threads/{id}/unsubscribe */
    public function unsubscribe(RequestInterface $request, string $id): mixed
    {
        $thread = $this->resolveThread($id);
        if ($thread === null) {
            throw RouteNotFoundException::forPath('POST', '/threads/' . $id . '/unsubscribe');
        }

        Gate::authorize('subscribe', $thread);
        $this->threadService->unsubscribe((int) Auth::id(), $thread);
        Session::flash('status', trans('forum::messages.unsubscribed'));

        return redirect('/threads/' . $thread->slug());
    }

    /** موضوع با slug یا ULID. */
    private function resolveThread(string $slugOrId): ?Thread
    {
        return $this->threads->findBySlug($slugOrId) ?? $this->threads->findById($slugOrId);
    }

    private function viewerId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
