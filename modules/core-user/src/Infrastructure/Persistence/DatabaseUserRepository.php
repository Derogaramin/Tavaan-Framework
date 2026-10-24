<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * DatabaseUserRepository — آداپتر ماندگاری کاربر (DB Facade → جدول users).
 */

namespace Modules\CoreUser\Infrastructure\Persistence;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Contracts\UserSnapshot;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Hash;

/**
 * DatabaseUserRepository — خواندن/نوشتن کاربران بدون ORM (پرس‌وجوی prepared).
 *
 * 🔒 ARCH-003: فقط Facadeهای هسته (DB/Hash). مرز عمومی ماژول =
 * UserReaderInterface؛ متدهای نوشتن برای Provider/Command/Seeder خود ماژول‌اند.
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class DatabaseUserRepository implements UserReaderInterface
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
}
