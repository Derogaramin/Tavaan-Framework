<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\BelongsTo;

/**
 * Fixture تست ORM — پروفایل (hasOne/belongsTo).
 *
 * @api internal
 */
final class TProfile extends Model
{
    protected string $table = 'orm_profiles';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['user_id', 'bio', 'phone'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TUser::class, 'user_id');
    }
}
