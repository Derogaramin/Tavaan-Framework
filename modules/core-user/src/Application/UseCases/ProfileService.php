<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * ProfileService — ویرایش پروفایل و تغییر رمز عبور (کاربر جاری).
 */

namespace Modules\CoreUser\Application\UseCases;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Hash;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ProfileService — UseCase پروفایل کاربری.
 *
 * 🔒 تغییر رمز نیازمند رمز جاری است (current_password)؛ تغییر ایمیل به‌صورت
 * پیش‌فرض بسته است (config core-user.profile.allow_email_change).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class ProfileService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input name/mobile/bio
     */
    public function updateProfile(User $user, array $input): User
    {
        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->users->updateProfile($user->id, [
            'name' => (string) $validated['name'],
            'mobile' => isset($validated['mobile']) && $validated['mobile'] !== '' ? (string) $validated['mobile'] : null,
            'bio' => isset($validated['bio']) && $validated['bio'] !== '' ? (string) $validated['bio'] : null,
        ]);

        $fresh = $this->users->findUserById($user->id);
        if ($fresh === null) {
            throw new ValidationException(messageId: 'core-user::messages.user_not_found');
        }

        return $fresh;
    }

    /**
     * تغییر رمز عبور با راستی‌آزمایی رمز جاری.
     *
     * @param array<string, mixed> $input current_password/password/password_confirmation
     */
    public function changePassword(User $user, array $input): void
    {
        $validated = Validator::validate($input, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        if (!Hash::check((string) $validated['current_password'], $user->getAuthPassword())) {
            throw new ValidationException(
                errors: ['current_password' => ['رمز عبور جاری نادرست است.']],
                messageId: 'core-user::messages.wrong_current_password',
            );
        }

        $this->users->updatePassword($user->id, (string) $validated['password']);
    }

    public function allowEmailChange(): bool
    {
        return (bool) ($this->config['profile']['allow_email_change'] ?? false);
    }
}
