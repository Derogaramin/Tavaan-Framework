<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\MorphOne;
use Tavaan\Engine\Database\ORM\Relations\MorphTo;

/**
 * Fixture تست ORM — رسانه (morphMany/morphTo/morphOne).
 *
 * @api internal
 */
final class TMedia extends Model
{
    protected string $table = 'orm_medias';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['mediable_type', 'mediable_id', 'path'];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): MorphOne
    {
        return $this->morphOne(TOwner::class, 'ownable');
    }
}
