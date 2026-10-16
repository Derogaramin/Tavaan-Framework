<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\BelongsToMany;
use Tavaan\Engine\Database\ORM\Relations\HasMany;
use Tavaan\Engine\Database\ORM\Relations\HasManyThrough;
use Tavaan\Engine\Database\ORM\Relations\HasOne;

/**
 * Fixture تست ORM — کاربر (DOC-050 §11).
 *
 * @api internal
 */
final class TUser extends Model
{
    protected string $table = 'orm_users';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['name', 'email', 'tenant_id', 'role'];

    /** @var list<string> */
    protected array $guarded = ['id', 'is_admin'];

    /** @var list<string> */
    protected array $hidden = ['secret_note'];

    /** @var array<string, string> */
    protected array $casts = ['is_admin' => 'bool'];

    public function profile(): HasOne
    {
        return $this->hasOne(TProfile::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TPost::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(TRole::class, 'orm_role_user', 'user_id', 'role_id');
    }

    public function distantPosts(): HasManyThrough
    {
        return $this->hasManyThrough(TPost::class, TUserMeta::class, 'user_id', 'user_id', 'id');
    }
}
