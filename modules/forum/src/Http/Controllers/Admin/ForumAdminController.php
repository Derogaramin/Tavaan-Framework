<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumAdminController — مدیریت تالارها (DOC-094 §4: admin).
 */

namespace Modules\Forum\Http\Controllers\Admin;

use Modules\Forum\Application\UseCases\ForumWriteService;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ForumAdminController — CRUD تالار + قفل تالار.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumAdminController
{
    public function __construct(
        private readonly ForumRepositoryInterface $forums,
        private readonly ForumWriteService $write,
    ) {
    }

    /** GET /admin/forum/forums */
    public function index(): mixed
    {
        Gate::authorize('forum.forum.view');

        return view('forum::admin.forums-index', [
            'forums' => $this->forums->all(),
            'canManage' => Gate::allows('forum.forum.manage'),
        ]);
    }

    /** GET /admin/forum/forums/create */
    public function create(): mixed
    {
        Gate::authorize('forum.forum.manage');

        return view('forum::admin.forum-form', [
            'forum' => null,
            'forums' => $this->forums->all(),
            'errors' => Session::pull('forum_forum_errors') ?? [],
            'old' => Session::pull('forum_forum_old') ?? [],
        ]);
    }

    /** POST /admin/forum/forums */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('forum.forum.manage');

        try {
            $forum = $this->write->createForum([
                'title' => (string) $request->input('title', ''),
                'slug' => $request->input('slug'),
                'description' => $request->input('description'),
                'parent_id' => $request->input('parent_id'),
                'position' => $request->input('position', 0),
            ]);
        } catch (ValidationException $e) {
            Session::flash('forum_forum_errors', $e->errors);
            Session::flash('forum_forum_old', $request->all());

            return redirect('/admin/forum/forums/create');
        }

        Session::flash('status', trans('forum::messages.forum_created'));

        return redirect('/admin/forum/forums');
    }

    /** GET /admin/forum/forums/{id}/edit */
    public function edit(string $id): mixed
    {
        Gate::authorize('forum.forum.manage');
        $forum = $this->findForum($id);

        return view('forum::admin.forum-form', [
            'forum' => $forum,
            'forums' => $this->forums->all(),
            'errors' => Session::pull('forum_forum_errors') ?? [],
            'old' => Session::pull('forum_forum_old') ?? [],
        ]);
    }

    /** POST /admin/forum/forums/{id} */
    public function update(RequestInterface $request, string $id): mixed
    {
        Gate::authorize('forum.forum.manage');
        $forum = $this->findForum($id);

        try {
            $this->write->updateForum($forum, [
                'title' => (string) $request->input('title', ''),
                'slug' => $request->input('slug'),
                'description' => $request->input('description'),
                'parent_id' => $request->input('parent_id'),
                'position' => $request->input('position', 0),
            ]);
        } catch (ValidationException $e) {
            Session::flash('forum_forum_errors', $e->errors);
            Session::flash('forum_forum_old', $request->all());

            return redirect('/admin/forum/forums/' . $forum->id() . '/edit');
        }

        Session::flash('status', trans('forum::messages.forum_updated'));

        return redirect('/admin/forum/forums');
    }

    /** POST /admin/forum/forums/{id}/lock — toggle قفل تالار. */
    public function lock(RequestInterface $request, string $id): mixed
    {
        Gate::authorize('forum.forum.manage');
        $forum = $this->findForum($id);

        $locked = $request->input('locked') !== null
            ? (bool) $request->input('locked')
            : !$forum->isLocked();
        $this->write->setLocked($forum, $locked);

        Session::flash('status', $locked
            ? trans('forum::messages.forum_locked')
            : trans('forum::messages.forum_unlocked'));

        return redirect('/admin/forum/forums');
    }

    /** DELETE /admin/forum/forums/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('forum.forum.manage');
        $forum = $this->findForum($id);

        try {
            $this->write->deleteForum($forum);
        } catch (ForumException $e) {
            Session::flash('error', $e->getMessage());

            return redirect('/admin/forum/forums');
        }

        Session::flash('status', trans('forum::messages.forum_deleted'));

        return redirect('/admin/forum/forums');
    }

    private function findForum(string $id): \Modules\Forum\Domain\Entities\Forum
    {
        $forum = $this->forums->findById((int) $id);
        if ($forum === null) {
            throw RouteNotFoundException::forPath('GET', '/admin/forum/forums/' . $id);
        }

        return $forum;
    }
}
