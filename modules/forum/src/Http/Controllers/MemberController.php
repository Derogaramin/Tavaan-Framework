<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * MemberController — پروفایل عمومی عضو + ویرایش پروفایل خود.
 */

namespace Modules\Forum\Http\Controllers;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Forum\Application\UseCases\ProfileService;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * MemberController — GET /members/{username} (عمومی) + ویرایش پروفایل.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class MemberController
{
    public function __construct(
        private readonly ProfileService $profiles,
        private readonly UserReaderInterface $users,
        private readonly ThreadRepositoryInterface $threads,
    ) {
    }

    /** GET /members/{username} — پروفایل عمومی + karma. */
    public function show(string $username): mixed
    {
        $profile = $this->profiles->findByUsername($username);
        if ($profile === null) {
            throw RouteNotFoundException::forPath('GET', '/members/' . $username);
        }

        $user = $this->users->findById($profile->userId());

        return view('forum::member', [
            'profile' => $profile,
            'user' => $user,
            'recentThreads' => $this->threads->paginate([], ['author_id' => $profile->userId()], 1, 5)['rows'],
            'isSelf' => (string) Auth::id() === (string) $profile->userId(),
        ]);
    }

    /** GET /members/{username}/edit — فرم ویرایش پروفایل خود. */
    public function edit(string $username): mixed
    {
        $profile = $this->profiles->findByUsername($username);
        if ($profile === null) {
            throw RouteNotFoundException::forPath('GET', '/members/' . $username . '/edit');
        }

        Gate::authorize('forum.profile.manage');
        if ((string) Auth::id() !== (string) $profile->userId()) {
            Gate::authorize('forum.user.ban');   // فقط admin پروفایل دیگران را ویرایش می‌کند
        }

        return view('forum::edit-profile', [
            'profile' => $profile,
            'errors' => Session::pull('forum_profile_errors') ?? [],
        ]);
    }

    /** POST /members/{username} — ذخیرهٔ پروفایل. */
    public function update(RequestInterface $request, string $username): mixed
    {
        $profile = $this->profiles->findByUsername($username);
        if ($profile === null) {
            throw RouteNotFoundException::forPath('POST', '/members/' . $username);
        }

        Gate::authorize('forum.profile.manage');
        if ((string) Auth::id() !== (string) $profile->userId()) {
            Gate::authorize('forum.user.ban');
        }

        try {
            $this->profiles->updateProfile($profile->userId(), [
                'username' => (string) $request->input('username', $profile->username()),
                'bio' => $request->input('bio'),
                'signature' => $request->input('signature'),
            ]);
        } catch (\InvalidArgumentException|\Tavaan\Core\Exceptions\Validation\ValidationException $e) {
            Session::flash('forum_profile_errors', ['username' => [$e->getMessage()]]);

            return redirect('/members/' . $username . '/edit');
        }

        Session::flash('status', trans('forum::messages.profile_updated'));

        return redirect('/members/' . \rawurlencode((string) $request->input('username', $username)));
    }
}
