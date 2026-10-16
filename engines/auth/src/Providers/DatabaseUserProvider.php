<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Providers;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Auth\UserProviderInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Engine\Auth\GenericUser;

/**
 * تَوان | Tavaan — Auth
 * DatabaseUserProvider — منبع کاربر از جدول دیتابیس (DOC-056 §3).
 *
 * اگر `model` یک کلاس ORM با Authenticatable باشد → نمونهٔ مدل hydrate می‌شود؛
 * وگرنه GenericUser.
 *
 * 🔒 Rehash خودکار پس از verify موفق (DOC-056 §5.2).
 * 🔒 retrieveByCredentials: بدون timing leak (همیشه یک هش dummy verify می‌شود).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class DatabaseUserProvider implements UserProviderInterface
{
    /**
     * @param array<string, mixed> $config provider config (table/model/identifier)
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly HasherInterface $hasher,
        private readonly array $config = [],
    ) {
    }

    private function table(): string
    {
        return (string) ($this->config['table'] ?? 'users');
    }

    private function identifierColumn(): string
    {
        return (string) ($this->config['identifier'] ?? 'id');
    }

    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $row = $this->connection->table($this->table())
            ->where($this->identifierColumn(), $identifier)
            ->first();

        return $row === null ? null : $this->toUser($row);
    }

    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable
    {
        $row = $this->connection->table($this->table())
            ->where($this->identifierColumn(), $identifier)
            ->where('remember_token', $token)
            ->first();

        return $row === null ? null : $this->toUser($row);
    }

    public function updateRememberToken(Authenticatable $user, string $token): void
    {
        $user->setRememberToken($token);

        if ($this->isModelUser($user)) {
            /** @var \Tavaan\Engine\Database\ORM\Model $user */
            $user->save();

            return;
        }

        $this->connection->table($this->table())
            ->where($this->identifierColumn(), $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $query = $this->connection->table($this->table());
        $hasCredentials = false;

        foreach ($credentials as $key => $value) {
            if (\in_array($key, ['password', 'remember', 'token'], true) || !\is_scalar($value)) {
                continue;
            }
            // 🔒 allowlist ستون (بدون injection در نام)
            if (\preg_match('/^[a-z_][a-z0-9_]*$/i', (string) $key) !== 1) {
                continue;
            }
            $query->where((string) $key, $value);
            $hasCredentials = true;
        }

        if (!$hasCredentials) {
            return null;
        }

        $row = $query->first();

        return $row === null ? null : $this->toUser($row);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = (string) ($credentials['password'] ?? '');
        $hash = $user->getAuthPassword();

        if ($hash === '') {
            // 🔒 timing یکسان: حتی وقتی کاربر هست/نیست یک verify انجام شود
            $this->hasher->check($plain, '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXlzYWx0ZHVtbXk$ZHVtbXloYXNoZHVtbXloYXNoZHVt');

            return false;
        }

        return $this->hasher->check($plain, $hash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials): void
    {
        if (!$this->hasher->needsRehash($user->getAuthPassword())) {
            return;
        }

        $newHash = $this->hasher->make((string) ($credentials['password'] ?? ''));

        if ($this->isModelUser($user)) {
            /** @var \Tavaan\Engine\Database\ORM\Model $user */
            $user->forceFill(['password' => $newHash])->save();

            return;
        }

        $this->connection->table($this->table())
            ->where($this->identifierColumn(), $user->getAuthIdentifier())
            ->update(['password' => $newHash]);
    }

    /** ذخیرهٔ کاربر جدید (register). */
    public function create(array $attributes): Authenticatable
    {
        $id = $this->connection->table($this->table())->insertGetId($attributes);
        $row = $this->connection->table($this->table())->find($id);

        return $this->toUser($row ?? $attributes);
    }

    /** @param array<string, mixed> $row */
    private function toUser(array $row): Authenticatable
    {
        $model = (string) ($this->config['model'] ?? '');

        if ($model !== '' && \class_exists($model) && \is_subclass_of($model, Authenticatable::class)) {
            /** @var \Tavaan\Engine\Database\ORM\Model $instance */
            $instance = new $model();
            if (\method_exists($instance, 'newFromRow')) {
                return $instance->newFromRow($row);
            }
        }

        return new GenericUser($row);
    }

    private function isModelUser(Authenticatable $user): bool
    {
        return $user instanceof \Tavaan\Engine\Database\ORM\Model;
    }
}
