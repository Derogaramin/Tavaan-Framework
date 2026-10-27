<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * ProfileController — پروفایل کاربر جاری (/profile).
 */

namespace Modules\CoreUser\Http\Controllers;

use Modules\CoreUser\Application\UseCases\ProfileService;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ProfileController — GET /profile + POST /profile + POST /profile/password.
 *
 * روت‌ها پشت AuthenticateMiddleware هستند؛ کاربر از guard جاری گرفته می‌شود
 * (نه از ورودی — 🔒 بدون IDOR).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class ProfileController
{
    public function __construct(
        private readonly ProfileService $profile,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /** GET /profile */
    public function edit(): mixed
    {
        $user = $this->currentUser();

        return view('core-user::profile.edit', [
            'user' => $user,
            'roles' => Session::pull('core_user_profile_roles') ?? [],
            'errors' => Session::pull('core_user_profile_errors') ?? [],
            'old' => Session::pull('core_user_profile_old') ?? [],
            'ok' => Session::pull('core_user_profile_ok'),
        ]);
    }

    /** POST /profile */
    public function update(RequestInterface $request): mixed
    {
        $user = $this->currentUser();
        $input = [
            'name' => (string) $request->input('name', ''),
            'mobile' => (string) $request->input('mobile', ''),
            'bio' => (string) $request->input('bio', ''),
        ];

        try {
            $this->profile->updateProfile($user, $input);
        } catch (ValidationException $e) {
            Session::flash('core_user_profile_errors', $e->errors);
            Session::flash('core_user_profile_old', $input);

            return redirect('/profile');
        }

        Session::flash('core_user_profile_ok', trans('core-user::messages.profile_updated'));

        return redirect('/profile');
    }

    /** POST /profile/password */
    public function updatePassword(RequestInterface $request): mixed
    {
        $user = $this->currentUser();
        $input = [
            'current_password' => (string) $request->input('current_password', ''),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];

        try {
            $this->profile->changePassword($user, $input);
        } catch (ValidationException $e) {
            Session::flash('core_user_profile_errors', $e->errors);

            return redirect('/profile');
        }

        Session::flash('core_user_profile_ok', trans('core-user::messages.password_changed'));

        return redirect('/profile');
    }

    private function currentUser(): User
    {
        $id = (int) Auth::id();
        $user = $id > 0 ? $this->users->findUserById($id) : null;

        if ($user === null) {
            throw new ValidationException(messageId: 'core-user::messages.user_not_found');
        }

        return $user;
    }
}
