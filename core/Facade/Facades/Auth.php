<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade احراز هویت (Auth).
 *
 * @method static \Tavaan\Core\Contracts\Auth\GuardInterface guard(?string $name = null)
 * @method static ?\Tavaan\Core\Contracts\Auth\Authenticatable user()
 * @method static int|string|null id()
 * @method static bool check()
 * @method static bool guest()
 * @method static \Tavaan\Core\Contracts\Auth\Authenticatable userOrFail()
 * @method static bool attempt(array $credentials, bool $remember = false)
 * @method static bool once(array $credentials)
 * @method static bool validate(array $credentials)
 * @method static void login(\Tavaan\Core\Contracts\Auth\Authenticatable $user, bool $remember = false)
 * @method static ?\Tavaan\Core\Contracts\Auth\Authenticatable loginUsingId(mixed $id, bool $remember = false)
 * @method static void logout()
 * @method static \Tavaan\Engine\Auth\TokenManager token()
 * @method static \Tavaan\Engine\Auth\Otp\OtpManager otp()
 * @method static \Tavaan\Engine\Auth\TwoFactor\TwoFactorManager twoFactor()
 * @method static \Tavaan\Engine\Auth\Devices\DeviceManager devices()
 * @method static \Tavaan\Core\Contracts\Auth\Authenticatable register(array $data)
 * @method static string sendPasswordResetLink(string $email)
 * @method static string resetPassword(string $email, string $token, string $newPassword, ?\Closure $callback = null)
 * @mixin \Tavaan\Engine\Auth\AuthManager
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class Auth extends Facade
{
    protected static function accessor(): string
    {
        return 'auth';
    }
}
