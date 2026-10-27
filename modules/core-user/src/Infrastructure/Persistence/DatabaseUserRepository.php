<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * DatabaseUserRepository — آداپتر ماندگاری کاربر (DB Facade → جدول users).
 */

namespace Modules\CoreUser\Infrastructure\Persistence;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Contracts\UserSnapshot;
use Modules\CoreUser\Contracts\UserWriterInterface;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Hash;

/**
 * DatabaseUserRepository — خواندن/نوشتن کاربران بدون ORM (پرس‌وجوی prepared).
 *
 * 🔒 ARCH-003: فقط Facadeهای هسته (DB/Hash). مرز عمومی ماژول =
 * UserReaderInterface؛ مرز داخلی = UserRepositoryInterface (UseCaseها).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class DatabaseUserRepository implements UserRepositoryInterface
{
    public function findById(int|string $id): ?UserSnapshot
    {
        $row = DB::table('users')->where('id', $id)->first();

        return $row === null ? null : UserSnapshot::fromRow($row);
    }

    public function findByEmail(string $email): ?UserSnapshot
    {
        $row = DB::table('users')->where('email', \mb_strtolower($email))->first();

        return $row === null ? null : UserSnapshot::fromRow($row);
    }

    public function findByIds(array $ids): array
    {
        $ids = \array_values(\array_unique(\array_map('strval', $ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('users')->whereIn('id', $ids)->get();

        $map = [];
        foreach ($rows as $row) {
            $snapshot = UserSnapshot::fromRow($row);
            $map[(string) $snapshot->id] = $snapshot;
        }

        return $map;
    }

    public function displayNameOf(int|string $id, string $fallback = 'کاربر'): string
    {
        return $this->findById($id)?->displayName() ?? $fallback;
    }

    public function findNotifiable(int|string $id): ?\Tavaan\Core\Contracts\Notification\Notifiable
    {
        return $this->findUserById($id);
    }

    /** موجودیت کامل (داخل ماژول/تست‌ها). */
    public function findUserById(int|string $id): ?User
    {
        $row = DB::table('users')->where('id', $id)->first();

        return $row === null ? null : User::fromRow($row);
    }

    /**
     * ساخت کاربر با رمز هش‌شده (Argon2id موتور hash).
     *
     * @throws ValidationException اگر ایمیل تکراری باشد
     */
    public function create(
        string $name,
        string $email,
        string $plainPassword,
        ?string $mobile = null,
        UserStatus $status = UserStatus::Active,
    ): User {
        $email = \mb_strtolower(\trim($email));
        if ($this->findByEmail($email) !== null) {
            throw new ValidationException(
                errors: ['email' => ['این ایمیل پیش‌تر ثبت شده است.']],
                messageId: 'core-user::messages.email_taken',
            );
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
        $id = DB::table('users')->insertGetId([
            'name' => \trim($name),
            'email' => $email,
            'mobile' => $mobile,
            'password' => Hash::make($plainPassword),
            'status' => $status->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = $this->findUserById($id);
        if ($user === null) {
            throw new ValidationException(messageId: 'core-user::messages.create_failed');
        }

        return $user;
    }

    public function updatePassword(int|string $id, string $plainPassword): bool
    {
        return DB::table('users')->where('id', $id)->update([
            'password' => Hash::make($plainPassword),
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]) > 0;
    }

    public function touchLastLogin(int|string $id): void
    {
        DB::table('users')->where('id', $id)->update([
            'last_login_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }

    // ─── نوشتن (UserWriterInterface) ───────────────────────────────────────

    public function updateProfile(int|string $id, array $fields): bool
    {
        $row = [];
        foreach (['name', 'mobile', 'bio', 'avatar'] as $key) {
            if (\array_key_exists($key, $fields)) {
                $value = $fields[$key];
                $row[$key] = $value === null ? null : (string) $value;
            }
        }

        if ($row === []) {
            return false;
        }

        $row['updated_at'] = $this->utcNow();

        return DB::table('users')->where('id', $id)->update($row) > 0;
    }

    public function updateEmail(int|string $id, string $email): bool
    {
        $email = \mb_strtolower(\trim($email));

        $duplicate = DB::table('users')->where('email', $email)->where('id', '!=', $id)->exists();
        if ($duplicate) {
            throw new ValidationException(
                errors: ['email' => ['این ایمیل پیش‌تر ثبت شده است.']],
                messageId: 'core-user::messages.email_taken',
            );
        }

        return DB::table('users')->where('id', $id)->update([
            'email' => $email,
            'email_verified_at' => null,      // 🔒 ایمیل تغییر کرد → تأیید قبلی باطل
            'updated_at' => $this->utcNow(),
        ]) > 0;
    }

    public function setStatus(int|string $id, UserStatus $status): bool
    {
        return DB::table('users')->where('id', $id)->update([
            'status' => $status->value,
            'updated_at' => $this->utcNow(),
        ]) > 0;
    }

    public function delete(int|string $id): bool
    {
        return DB::table('users')->where('id', $id)->delete() > 0;
    }

    // ─── خواندن مدیریتی (UserRepositoryInterface) ─────────────────────────

    public function findUserByEmail(string $email): ?User
    {
        $row = DB::table('users')->where('email', \mb_strtolower($email))->first();

        return $row === null ? null : User::fromRow($row);
    }

    public function paginate(string $search = '', ?UserStatus $status = null, int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('users');
        $rows = DB::table('users');

        $search = \trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $count->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            });
            $rows->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            });
        }

        if ($status !== null) {
            $count->where('status', $status->value);
            $rows->where('status', $status->value);
        }

        $total = $count->count();
        $records = $rows->orderByDesc('id')->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): User => User::fromRow($row), $records),
            'total' => $total,
        ];
    }

    public function countAll(): int
    {
        return DB::table('users')->count();
    }

    public function countByStatus(UserStatus $status): int
    {
        return DB::table('users')->where('status', $status->value)->count();
    }

    public function latest(int $limit = 5): array
    {
        $limit = \max(1, \min(50, $limit));
        $rows = DB::table('users')->orderByDesc('id')->limit($limit)->get();

        return \array_map(static fn (array $row): User => User::fromRow($row), $rows);
    }

    public function countActiveAdmins(): int
    {
        // 🔒 بدون Join بین‌نوعی (user_id در tvn_role_user رشته است) — دو گام:
        // نقش‌های مدیر → شناسه‌های کاربر → شمارش فعال‌ها. اگر جداول RBAC
        // مهاجرت نشده باشند (نصب حداقل)، محافظ try/catch صفر برمی‌گرداند.
        try {
            $roleRows = DB::table('tvn_roles')->whereIn('key', ['admin', 'super-admin'])->get();
            $roleIds = [];
            foreach ($roleRows as $row) {
                $roleIds[] = (int) $row['id'];
            }
            if ($roleIds === []) {
                return 0;
            }

            $linkRows = DB::table('tvn_role_user')
                ->whereIn('role_id', $roleIds)
                ->whereNull('tenant_id')
                ->get();

            $userIds = [];
            foreach ($linkRows as $row) {
                $id = (int) $row['user_id'];
                if ($id > 0) {
                    $userIds[$id] = true;
                }
            }
            if ($userIds === []) {
                return 0;
            }

            return DB::table('users')
                ->whereIn('id', \array_keys($userIds))
                ->where('status', UserStatus::Active->value)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function utcNow(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }
}
