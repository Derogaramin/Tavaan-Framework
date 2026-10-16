<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Engine\Database\ORM\Model;

/**
 * Fixture تست ORM/Auth — کاربر با قرارداد Authenticatable + encrypted cast.
 *
 * @api internal
 */
final class TSecureUser extends Model implements Authenticatable
{
    protected string $table = 'orm_secure_users';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['name', 'email', 'password', 'mobile', 'remember_token'];

    /** @var list<string> */
    protected array $hidden = ['password', 'mobile'];

    /** @var array<string, string> */
    protected array $casts = [
        'password' => 'hashed',
        'mobile' => 'encrypted',
    ];

    public function getAuthIdentifier(): int|string
    {
        return (int) ($this->attributes['id'] ?? 0);
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? '');
    }

    public function getRememberToken(): ?string
    {
        return isset($this->attributes['remember_token']) ? (string) $this->attributes['remember_token'] : null;
    }

    public function setRememberToken(string $value): void
    {
        $this->attributes['remember_token'] = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
