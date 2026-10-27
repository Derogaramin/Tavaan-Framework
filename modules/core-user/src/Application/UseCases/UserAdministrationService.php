<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserAdministrationService — مدیریت کاربران در پنل (CRUD + وضعیت + نقش + رمز).
 */

namespace Modules\CoreUser\Application\UseCases;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * UserAdministrationService — UseCase مدیریت کاربران (/admin/users).
 *
 * 🔒 محافظت‌ها:
 *  - حذف/تغییر وضعیت خودِ کاربر جاری ممنوع (self-lockout)
 *  - `protect_last_admin`: آخرین مدیر فعال حذف/مسدود/غیرفعال نمی‌شود
 *
 * 🔒 ARCH-003: نقش‌ها از alias کانتینر `rbac.roles`.
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class UserAdministrationService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ContainerInterface $app,
        private readonly array $config = [],
    ) {
    }

    /**
     * فهرست صفحه‌بندی‌شده با جست‌وجو/فیلتر.
     *
     * @return array{rows: list<User>, total: int}
     */
    public function paginate(string $search, ?string $status, int $page): array
    {
        $statusEnum = $status !== null && $status !== '' ? UserStatus::tryFrom($status) : null;
        $perPage = (int) ($this->config['admin']['per_page'] ?? 20);

        return $this->users->paginate($search, $statusEnum, $page, $perPage);
    }

    /**
     * ساخت کاربر از پنل (با نقش‌ها).
     *
     * @param array<string, mixed> $input name/email/password/mobile/status
     * @param list<string>         $roles کلید نقش‌ها
     */
    public function create(array $input, array $roles = []): User
    {
        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:active,inactive,banned'],
        ]);

        $status = UserStatus::tryFrom((string) ($validated['status'] ?? 'active')) ?? UserStatus::Active;

        $user = $this->users->create(
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            plainPassword: (string) $validated['password'],
            mobile: isset($validated['mobile']) && $validated['mobile'] !== '' ? (string) $validated['mobile'] : null,
            status: $status,
        );

        $this->syncRoles($user->id, $roles);

        Event::dispatch('user.registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => \implode(',', $roles),
            'via' => 'admin',
        ]);

        return $user;
    }

    /**
     * ویرایش کاربر (پروفایل + وضعیت + نقش‌ها؛ رمز فقط در resetPassword).
     *
     * @param array<string, mixed> $input
     * @param list<string>         $roles
     */
    public function update(User $target, array $input, array $roles = [], ?int $actorId = null): User
    {
        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:active,inactive,banned'],
        ]);

        // یکتایی ایمیل (به‌جز خود کاربر)
        $byEmail = $this->users->findByEmail((string) $validated['email']);
        if ($byEmail !== null && (string) $byEmail->id !== (string) $target->id) {
            throw new ValidationException(
                errors: ['email' => ['این ایمیل پیش‌تر ثبت شده است.']],
                messageId: 'core-user::messages.email_taken',
            );
        }

        $newStatus = UserStatus::tryFrom((string) ($validated['status'] ?? $target->status->value)) ?? $target->status;
        if ($newStatus !== $target->status) {
            $this->guardStatusChange($target, $newStatus, $actorId);
        }

        $this->users->updateProfile($target->id, [
            'name' => (string) $validated['name'],
            'mobile' => isset($validated['mobile']) && $validated['mobile'] !== '' ? (string) $validated['mobile'] : null,
            'bio' => isset($validated['bio']) && $validated['bio'] !== '' ? (string) $validated['bio'] : null,
        ]);

        // ایمیل (اگر تغییر کرده و مجاز است)
        if (\mb_strtolower((string) $validated['email']) !== $target->email) {
            $this->users->updateEmail($target->id, (string) $validated['email']);
        }

        $this->users->setStatus($target->id, $newStatus);
        if ($newStatus !== $target->status) {
            Event::dispatch('user.status.changed', [
                'user_id' => $target->id,
                'from' => $target->status->value,
                'to' => $newStatus->value,
                'changed_by' => $actorId,
            ]);
        }

        $this->syncRoles($target->id, $roles);

        $fresh = $this->users->findUserById($target->id);

        return $fresh ?? $target;
    }

    /** تغییر سریع وضعیت (دکمه‌های فعال/مسدود فهرست). */
    public function setStatus(User $target, UserStatus $status, ?int $actorId = null): void
    {
        if ($status === $target->status) {
            return;
        }

        $this->guardStatusChange($target, $status, $actorId);
        $this->users->setStatus($target->id, $status);

        Event::dispatch('user.status.changed', [
            'user_id' => $target->id,
            'from' => $target->status->value,
            'to' => $status->value,
            'changed_by' => $actorId,
        ]);
    }

    /** بازنشانی رمز عبور از پنل (بدون نیاز به رمز قبلی). */
    public function resetPassword(User $target, string $newPassword): void
    {
        $validated = Validator::validate(['password' => $newPassword], [
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $this->users->updatePassword($target->id, (string) $validated['password']);
    }

    /** حذف کاربر با محافظت‌های self/last-admin. */
    public function delete(User $target, ?int $actorId = null): void
    {
        if ($actorId !== null && $actorId === $target->id) {
            throw new ValidationException(
                errors: ['user' => ['نمی‌توانید حساب کاربری خود را حذف کنید.']],
                messageId: 'core-user::messages.cannot_delete_self',
            );
        }

        if ($this->protectLastAdmin() && $target->status === UserStatus::Active && $this->isAdmin($target->id)) {
            $activeAdmins = $this->users->countActiveAdmins();
            if ($activeAdmins <= 1) {
                throw new ValidationException(
                    errors: ['user' => ['آخرین مدیر فعال را نمی‌توان حذف کرد.']],
                    messageId: 'core-user::messages.last_admin_protected',
                );
            }
        }

        $this->users->delete($target->id);

        Event::dispatch('user.deleted', [
            'user_id' => $target->id,
            'email' => $target->email,
            'deleted_by' => $actorId,
        ]);
    }

    /**
     * نقش‌های کاربر (کلیدها) — برای فرم ویرایش.
     *
     * @return list<string>
     */
    public function rolesOf(int $userId): array
    {
        $roles = $this->roleManager();
        if ($roles === null) {
            return [];
        }

        $keys = [];
        foreach ((array) $roles->rolesOf($userId) as $role) {
            $key = \is_array($role) ? (string) ($role['key'] ?? '') : (\is_object($role) ? (string) ($role->key ?? '') : (string) $role);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** فهرست کلید نقش‌های موجود (برای select فرم). */
    public function availableRoles(): array
    {
        $roles = $this->roleManager();
        if ($roles === null || !\method_exists($roles, 'allRoles')) {
            return [];
        }

        $out = [];
        foreach ((array) $roles->allRoles() as $role) {
            $row = \is_array($role) ? $role : (array) $role;
            $key = (string) ($row['key'] ?? '');
            if ($key !== '') {
                $out[] = ['key' => $key, 'name' => (string) ($row['name'] ?? $key)];
            }
        }

        return $out;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function guardStatusChange(User $target, UserStatus $newStatus, ?int $actorId): void
    {
        if ($actorId !== null && $actorId === $target->id && $newStatus !== UserStatus::Active) {
            throw new ValidationException(
                errors: ['status' => ['نمی‌توانید وضعیت حساب خود را تغییر دهید.']],
                messageId: 'core-user::messages.cannot_change_self_status',
            );
        }

        if (
            $this->protectLastAdmin()
            && $target->status === UserStatus::Active
            && $newStatus !== UserStatus::Active
            && $this->isAdmin($target->id)
            && $this->users->countActiveAdmins() <= 1
        ) {
            throw new ValidationException(
                errors: ['status' => ['آخرین مدیر فعال را نمی‌توان غیرفعال/مسدود کرد.']],
                messageId: 'core-user::messages.last_admin_protected',
            );
        }
    }

    private function isAdmin(int $userId): bool
    {
        $roles = $this->roleManager();
        if ($roles === null) {
            return false;
        }

        foreach ($this->adminRoleKeys() as $key) {
            if ($roles->hasRole($userId, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function adminRoleKeys(): array
    {
        $keys = $this->config['admin']['admin_roles'] ?? ['admin', 'super-admin'];

        return \is_array($keys) ? \array_values(\array_map('strval', $keys)) : ['admin', 'super-admin'];
    }

    private function protectLastAdmin(): bool
    {
        return (bool) ($this->config['admin']['protect_last_admin'] ?? true);
    }

    private function roleManager(): ?object
    {
        if (!$this->app->bound('rbac.roles')) {
            return null;
        }

        $roles = $this->app->make('rbac.roles');

        return \is_object($roles) ? $roles : null;
    }

    /** همگام‌سازی نقش‌ها: افزودن جدیدها + حذف واگذارشده‌ها. */
    private function syncRoles(int $userId, array $newRoles): void
    {
        $roles = $this->roleManager();
        if ($roles === null || !\method_exists($roles, 'assign')) {
            return;
        }

        $newRoles = \array_values(\array_unique(\array_filter(\array_map('strval', $newRoles), static fn (string $k): bool => $k !== '')));
        $current = $this->rolesOf($userId);

        foreach ($newRoles as $key) {
            if (\method_exists($roles, 'findRole') && $roles->findRole($key) === null) {
                continue;   // نقش تعریف‌نشده — بی‌اثر
            }
            if (!\in_array($key, $current, true)) {
                $roles->assign($userId, $key);
            }
        }

        if (\method_exists($roles, 'remove')) {
            foreach ($current as $key) {
                if (!\in_array($key, $newRoles, true)) {
                    $roles->remove($userId, $key);
                }
            }
        }
    }
}
