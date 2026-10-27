<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * AuthController — ورود/خروج (فرم وب با session guard).
 */

namespace Modules\CoreUser\Http\Controllers;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Session;

/**
 * AuthController — GET/POST /login + POST /logout.
 *
 * 🔒 محافظت‌ها:
 *  - throttle روی روت (پیش‌فرض ۵ تلاش در ۱۰ دقیقه — config core-user.login.throttle)
 *  - بررسی وضعیت حساب (inactive/banned → خروج + پیام) — SessionGuard موتور
 *    فقط رمز را می‌سنجد، سیاست وضعیت در لایهٔ ماژول است
 *  - پیام خطای یکسان برای «کاربر نیست» و «رمز غلط» (ضد user enumeration)
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class AuthController
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /** GET /login */
    public function showLogin(): mixed
    {
        if (Auth::check()) {
            return redirect($this->redirectAfter());
        }

        return view('core-user::auth.login', [
            'errors' => Session::pull('core_user_login_errors') ?? [],
            'old' => Session::pull('core_user_login_old') ?? [],
            'message' => Session::pull('core_user_login_message'),
            'registrationEnabled' => (bool) config('core-user.registration.enabled', false),
        ]);
    }

    /** POST /login */
    public function login(RequestInterface $request): mixed
    {
        $email = \mb_strtolower(\trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $remember = (bool) $request->input('remember', false);

        if ($email === '' || $password === '') {
            return $this->backWithError($email, 'core-user::messages.credentials_required');
        }

        try {
            $ok = Auth::attempt(['email' => $email, 'password' => $password], $remember);
        } catch (\Tavaan\Core\Exceptions\Http\HttpException $e) {
            // SessionGuard موتور برای حساب مسدود استثنا می‌اندازد (auth.account_banned) —
            // ما آن را به پیام کاربری روی فرم تبدیل می‌کنیم (بدون ارجاع به کلاس موتور).
            if ($e->messageId === 'auth.account_banned') {
                return $this->backWithError($email, 'core-user::messages.account_banned');
            }

            throw $e;
        }

        if (!$ok) {
            return $this->backWithError($email, 'core-user::messages.invalid_credentials');
        }

        // سیاست وضعیت حساب — کاربر غیرفعال/مسدود اجازهٔ ماندن در جلسه ندارد
        // (guard موتور GenericUser برمی‌گرداند؛ status به‌صورت عمومی خوانده می‌شود)
        $user = Auth::user();
        $status = $this->statusOf($user);
        if ($status !== '' && $status !== \Modules\CoreUser\Domain\Enums\UserStatus::Active->value) {
            Auth::logout();
            Session::invalidate();

            return $this->backWithError($email, $status === \Modules\CoreUser\Domain\Enums\UserStatus::Banned->value
                ? 'core-user::messages.account_banned'
                : 'core-user::messages.account_inactive');
        }

        if ($user !== null) {
            $this->users->touchLastLogin((int) $user->getAuthIdentifier());
            Event::dispatch('user.logged_in', [
                'user_id' => (int) $user->getAuthIdentifier(),
                'remember' => $remember,
            ]);
        }

        Session::regenerate();

        $intended = Session::pull('url.intended');

        return redirect(\is_string($intended) && $intended !== '' ? $intended : $this->redirectAfter());
    }

    /** POST /logout */
    public function logout(): mixed
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();
        Session::flash('core_user_login_message', trans('core-user::messages.logged_out'));

        return redirect('/login');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function redirectAfter(): string
    {
        $to = config('core-user.login.redirect_after', '/admin');

        return \is_string($to) && $to !== '' ? $to : '/admin';
    }

    private function backWithError(string $email, string $messageId): mixed
    {
        Session::flash('core_user_login_errors', ['email' => [trans($messageId)]]);
        Session::flash('core_user_login_old', ['email' => $email]);

        return redirect('/login');
    }

    /**
     * خواندن وضعیت کاربر از هر Authenticatable (موجودیت ماژول یا GenericUser موتور).
     */
    private function statusOf(mixed $user): string
    {
        if ($user instanceof User) {
            return $user->status->value;
        }

        if (\is_object($user) && isset($user->status)) {
            return (string) $user->status;
        }

        return '';
    }
}
