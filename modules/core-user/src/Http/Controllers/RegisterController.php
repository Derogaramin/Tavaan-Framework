<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * RegisterController — ثبت‌نام عمومی (در صورت فعال بودن config).
 */

namespace Modules\CoreUser\Http\Controllers;

use Modules\CoreUser\Application\UseCases\RegistrationService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * RegisterController — GET/POST /register.
 *
 * 🔒 وقتی `core-user.registration.enabled=false` باشد → 404 (نه 403؛
 * پنهان‌سازی وجود قابلیت).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class RegisterController
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    /** GET /register */
    public function showRegister(): mixed
    {
        if (!$this->registration->enabled()) {
            throw new RouteNotFoundException(messageId: 'http.route.not_found', context: ['uri' => '/register']);
        }

        if (Auth::check()) {
            return redirect('/profile');
        }

        return view('core-user::auth.register', [
            'errors' => Session::pull('core_user_register_errors') ?? [],
            'old' => Session::pull('core_user_register_old') ?? [],
        ]);
    }

    /** POST /register */
    public function register(RequestInterface $request): mixed
    {
        if (!$this->registration->enabled()) {
            throw new RouteNotFoundException(messageId: 'http.route.not_found', context: ['uri' => '/register']);
        }

        $input = [
            'name' => (string) $request->input('name', ''),
            'email' => (string) $request->input('email', ''),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'mobile' => (string) $request->input('mobile', ''),
        ];

        try {
            $user = $this->registration->register($input);
        } catch (ValidationException $e) {
            Session::flash('core_user_register_errors', $e->errors);
            Session::flash('core_user_register_old', ['name' => $input['name'], 'email' => $input['email'], 'mobile' => $input['mobile']]);

            return redirect('/register');
        }

        if ($this->registration->autoLogin()) {
            Auth::login($user);
            Session::regenerate();
        }

        Session::flash('core_user_profile_ok', trans('core-user::messages.registered'));

        return redirect($this->registration->autoLogin() ? '/profile' : '/login');
    }
}
