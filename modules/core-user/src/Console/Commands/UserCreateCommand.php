<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserCreateCommand — `user:create` ساخت کاربر از CLI (نصب/seed/ادمین).
 */

namespace Modules\CoreUser\Console\Commands;

use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Engine\Console\Command;

/**
 * `tvn user:create admin@example.com --name="مدیر" --password=secret --role=admin`
 *
 * نقش‌ها از راه alias کانتینر `rbac.roles` (بدون ارجاع به کلاس موتور —
 * ARCH-003). رمز عبور با موتور hash (Argon2id) هش می‌شود.
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class UserCreateCommand extends Command
{
    protected string $signature = 'user:create {email : ایمیل کاربر} {--name= : نام نمایشی}'
        . ' {--password= : رمز عبور (بدون آن: پرسش امن)} {--mobile= : موبایل}'
        . ' {--role=* : نقش(ها) — کلید نقش مثل admin} {--status=active : active|inactive}';

    protected string $description = 'ساخت کاربر (جدول users ماژول core-user)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (\filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('ایمیل نامعتبر: ' . $email);

            return self::FAILURE;
        }

        $name = $this->option('name');
        $name = \is_string($name) && $name !== '' ? $name : \explode('@', $email)[0];

        $password = $this->option('password');
        if (!\is_string($password) || $password === '') {
            $password = (string) ($this->secret('رمز عبور:') ?? '');
        }
        if (\strlen($password) < 8) {
            $this->error('رمز عبور باید حداقل ۸ نویسه باشد.');

            return self::FAILURE;
        }

        $mobile = $this->option('mobile');
        $statusValue = (string) ($this->option('status') ?? 'active');
        $status = UserStatus::tryFrom($statusValue) ?? UserStatus::Active;

        /** @var DatabaseUserRepository $users */
        $users = $this->app->make(DatabaseUserRepository::class);

        try {
            $user = $users->create(name: $name, email: $email, plainPassword: $password, mobile: \is_string($mobile) ? $mobile : null, status: $status);
        } catch (ValidationException $e) {
            $this->error($e->errors['email'][0] ?? $e->messageId);

            return self::FAILURE;
        }

        // نقش‌ها — از alias کانتینر (RoleManager موتور rbac بدون ارجاع کلاسی)
        $roles = (array) $this->option('role');
        $assigned = [];
        if ($roles !== [] && $this->app->bound('rbac.roles')) {
            $roleManager = $this->app->make('rbac.roles');
            foreach ($roles as $roleKey) {
                $roleKey = (string) $roleKey;
                if ($roleKey === '' || !\is_object($roleManager) || !\method_exists($roleManager, 'assign')) {
                    continue;
                }
                $roleManager->assign($user->id, $roleKey);
                $assigned[] = $roleKey;
            }
        }

        $this->info(\sprintf('✓ کاربر #%d ساخته شد: %s <%s>', $user->id, $user->name, $user->email));
        if ($assigned !== []) {
            $this->info('  نقش‌ها: ' . \implode('، ', $assigned));
        }

        return self::SUCCESS;
    }
}
