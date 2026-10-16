<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\HasMany;

/**
 * Fixture تست ORM — واسط hasManyThrough.
 *
 * @api internal
 */
final class TUserMeta extends Model
{
    protected string $table = 'orm_user_metas';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['user_id', 'nickname'];

    public function posts(): HasMany
    {
        return $this->hasMany(TPost::class, 'user_id');
    }
}
