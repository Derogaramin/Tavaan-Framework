<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * RegistrationService — ثبت‌نام عمومی کاربر (اعتبارسنجی + نقش + رویداد).
 */

namespace Modules\CoreUser\Application\UseCases;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * RegistrationService — UseCase ثبت‌نام (DOC-090 §5).
 *
 * جریان: اعتبارسنجی → ساخت کاربر → انتساب نقش پیش‌فرض (اگر موجود) →
 * رویداد `user.registered`. ورود خودکار در کنترلر انجام می‌شود (لایهٔ وب).
 *
 * 🔒 ARCH-003: نقش از alias کانتینر `rbac.roles` (بدون ارجاع به موتور).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class RegistrationService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ContainerInterface $app,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input name/email/password/password_confirmation/mobile
     *
     * @throws ValidationException اگر ثبت‌نام بسته باشد یا ورودی نامعتبر
     */
    public function register(array $input): User
    {
        if (!$this->enabled()) {
            throw new ValidationException(
                errors: ['registration' => ['ثبت‌نام عمومی غیرفعال است.']],
                messageId: 'core-user::messages.registration_disabled',
            );
        }

        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $this->users->create(
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            plainPassword: (string) $validated['password'],
            mobile: isset($validated['mobile']) && $validated['mobile'] !== '' ? (string) $validated['mobile'] : null,
        );

        $role = $this->assignDefaultRole($user->id);

        Event::dispatch('user.registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $role,
        ]);

        return $user;
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['registration']['enabled'] ?? false);
    }

    public function autoLogin(): bool
    {
        return (bool) ($this->config['registration']['auto_login'] ?? true);
    }

    /** انتساب نقش پیش‌فرض — اگر RoleManager در دسترس و نقش تعریف‌شده باشد. */
    private function assignDefaultRole(int $userId): string
    {
        $roleKey = (string) ($this->config['registration']['default_role'] ?? '');
        if ($roleKey === '' || !$this->app->bound('rbac.roles')) {
            return '';
        }

        $roles = $this->app->make('rbac.roles');
        if (!\is_object($roles) || !\method_exists($roles, 'findRole') || !\method_exists($roles, 'assign')) {
            return '';
        }

        if ($roles->findRole($roleKey) === null) {
            return '';
        }

        $roles->assign($userId, $roleKey);

        return $roleKey;
    }
}
