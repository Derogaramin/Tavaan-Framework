<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadAdminController — قفل/سنجاق/جابجایی/حذف موضوع (DOC-094 §4).
 */

namespace Modules\Forum\Http\Controllers\Admin;

use Modules\Forum\Application\UseCases\ThreadService;
use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ThreadAdminController — فهرست موضوعات + اقدام‌های moderator.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ThreadAdminController
{
    public function __construct(
        private readonly ThreadRepositoryInterface $threads,
        private readonly ForumRepositoryInterface $forums,
        private readonly ThreadService $threadService,
    ) {
    }

    /** GET /admin/forum/threads */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('forum.thread.view');

        $page = \max(1, (int) $request->query('page', 1));
        $forumId = (int) $request->query('forum_id', 0);
        $forumIds = $forumId > 0 ? [$forumId] : [];
        $result = $this->threads->paginate($forumIds, [], $page, 20);

        return view('forum::admin.threads-index', [
            'threads' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'forums' => $this->forums->all(),
            'forumId' => $forumId,
            'canLock' => Gate::allows('forum.thread.lock'),
            'canPin' => Gate::allows('forum.thread.pin'),
            'canMove' => Gate::allows('forum.thread.move'),
            'canDelete' => Gate::allows('forum.thread.delete'),
        ]);
    }

    /** POST /admin/forum/threads/{id}/lock — toggle قفل. */
    public function lock(string $id): mixed
    {
        $thread = $this->findThread($id);
        Gate::authorize('lock', $thread);

        $this->threadService->setLocked($thread, !$thread->isLocked(), (int) Auth::id());
        Session::flash('status', $thread->isLocked()
            ? trans('forum::messages.thread_locked')
            : trans('forum::messages.thread_unlocked'));

        return back();
    }

    /** POST /admin/forum/threads/{id}/pin — toggle سنجاق. */
    public function pin(string $id): mixed
    {
        $thread = $this->findThread($id);
        Gate::authorize('pin', $thread);

        $this->threadService->setPinned($thread, !$thread->isPinned(), (int) Auth::id());
        Session::flash('status', trans('forum::messages.thread_pin_toggled'));

        return back();
    }

    /** POST /admin/forum/threads/{id}/move — جابجایی به تالار دیگر. */
    public function move(RequestInterface $request, string $id): mixed
    {
        $thread = $this->findThread($id);
        Gate::authorize('move', $thread);

        try {
            $this->threadService->move($thread, (int) $request->input('forum_id', 0), (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', trans('forum::messages.thread_moved'));

        return back();
    }

    /** POST /admin/forum/threads/{id}/rename — ویرایش عنوان. */
    public function rename(RequestInterface $request, string $id): mixed
    {
        $thread = $this->findThread($id);
        Gate::authorize('update', $thread);

        try {
            $this->threadService->rename($thread, (int) Auth::id(), (string) $request->input('title', ''));
        } catch (ValidationException $e) {
            Session::flash('error', trans('forum::messages.invalid_title'));

            return back();
        }

        Session::flash('status', trans('forum::messages.thread_renamed'));

        return back();
    }

    /** DELETE /admin/forum/threads/{id} */
    public function destroy(string $id): mixed
    {
        $thread = $this->findThread($id);
        Gate::authorize('delete', $thread);

        $this->threadService->deleteThread($thread, (int) Auth::id());
        Session::flash('status', trans('forum::messages.thread_deleted'));

        return redirect('/admin/forum/threads');
    }

    private function findThread(string $id): Thread
    {
        $thread = $this->threads->findById($id);
        if ($thread === null) {
            throw RouteNotFoundException::forPath('GET', '/admin/forum/threads/' . $id);
        }

        return $thread;
    }
}
