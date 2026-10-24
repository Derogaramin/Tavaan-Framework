<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ModerationAdminController — صف flag + پنهان/حذف پست + ban کاربر (DOC-094 §4/§5).
 */

namespace Modules\Forum\Http\Controllers\Admin;

use Modules\Forum\Application\UseCases\ModerationService;
use Modules\Forum\Application\UseCases\PostService;
use Modules\Forum\Application\UseCases\ProfileService;
use Modules\Forum\Domain\Entities\Flag;
use Modules\Forum\Domain\Entities\Post;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ModerationAdminController — صف moderation (E2E-04) + اقدام‌های پست + ban.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ModerationAdminController
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly PostService $postService,
        private readonly ProfileService $profiles,
        private readonly FlagRepositoryInterface $flags,
        private readonly PostRepositoryInterface $posts,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** GET /admin/forum/moderation — صف گزارش‌های باز. */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('forum.flag.manage');

        $page = \max(1, (int) $request->query('page', 1));
        $queue = $this->moderation->pendingQueue($page, 20);

        $flaggedPosts = [];
        $userIds = [];
        foreach ($queue['rows'] as $flag) {
            $flaggedPosts[$flag->postId()] = $this->posts->findById($flag->postId());
            if ($flag->userId() !== null) {
                $userIds[] = (string) $flag->userId();
            }
            if (isset($flaggedPosts[$flag->postId()]) && $flaggedPosts[$flag->postId()] !== null) {
                $userIds[] = (string) $flaggedPosts[$flag->postId()]->authorId();
            }
        }

        $names = [];
        foreach ($this->users->findByIds(\array_values(\array_unique($userIds))) as $id => $snapshot) {
            $names[(string) $id] = $snapshot->displayName();
        }

        return view('forum::admin.moderation-index', [
            'queue' => $queue['rows'],
            'total' => $queue['total'],
            'page' => $page,
            'perPage' => 20,
            'posts' => $flaggedPosts,
            'names' => $names,
            'canBan' => Gate::allows('forum.user.ban'),
        ]);
    }

    /** POST /admin/forum/moderation/{flagId}/resolve — تأیید گزارش → hide. */
    public function resolve(RequestInterface $request, string $flagId): mixed
    {
        Gate::authorize('forum.flag.manage');
        $flag = $this->findFlag($flagId);

        $this->moderation->resolve($flag, (int) Auth::id(), (string) $request->input('note', ''));
        Session::flash('status', trans('forum::messages.flag_resolved'));

        return redirect('/admin/forum/moderation');
    }

    /** POST /admin/forum/moderation/{flagId}/dismiss — رد گزارش. */
    public function dismiss(string $flagId): mixed
    {
        Gate::authorize('forum.flag.manage');
        $flag = $this->findFlag($flagId);

        $this->moderation->dismiss($flag, (int) Auth::id());
        Session::flash('status', trans('forum::messages.flag_dismissed'));

        return redirect('/admin/forum/moderation');
    }

    /** POST /admin/forum/posts/{id}/hide */
    public function hide(RequestInterface $request, string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('hide', $post);

        $this->postService->hide($post, (int) Auth::id(), (string) $request->input('reason', ''));
        Session::flash('status', trans('forum::messages.post_hidden'));

        return back();
    }

    /** POST /admin/forum/posts/{id}/unhide */
    public function unhide(string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('hide', $post);

        $this->postService->unhide($post, (int) Auth::id());
        Session::flash('status', trans('forum::messages.post_unhidden'));

        return back();
    }

    /** DELETE /admin/forum/posts/{id} — حذف نرم. */
    public function destroyPost(string $id): mixed
    {
        $post = $this->findPost($id);
        Gate::authorize('delete', $post);

        $this->postService->deletePost($post, (int) Auth::id());
        Session::flash('status', trans('forum::messages.post_deleted'));

        return back();
    }

    /** POST /admin/forum/users/{id}/ban — ban کاربر (DOC-094 §4). */
    public function ban(RequestInterface $request, string $id): mixed
    {
        Gate::authorize('forum.user.ban');

        $userId = (int) $id;
        $days = (int) $request->input('days', 0);
        $until = $days > 0
            ? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->add(new \DateInterval('P' . $days . 'D'))
            : null;

        $this->profiles->ban($userId, $until, (string) $request->input('reason', ''));
        Session::flash('status', trans('forum::messages.user_banned'));

        return back();
    }

    /** POST /admin/forum/users/{id}/unban */
    public function unban(string $id): mixed
    {
        Gate::authorize('forum.user.ban');

        $this->profiles->unban((int) $id);
        Session::flash('status', trans('forum::messages.user_unbanned'));

        return back();
    }

    private function findFlag(string $flagId): Flag
    {
        $flag = $this->flags->findById((int) $flagId);
        if ($flag === null) {
            throw RouteNotFoundException::forPath('GET', '/admin/forum/moderation/' . $flagId);
        }

        return $flag;
    }

    private function findPost(string $id): Post
    {
        $post = $this->posts->findById($id);
        if ($post === null) {
            throw RouteNotFoundException::forPath('GET', '/admin/forum/posts/' . $id);
        }

        return $post;
    }
}
