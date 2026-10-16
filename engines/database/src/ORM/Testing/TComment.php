<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\MorphTo;

/**
 * Fixture تست ORM — نظر (morphTo).
 *
 * @api internal
 */
final class TComment extends Model
{
    protected string $table = 'orm_comments';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['commentable_type', 'commentable_id', 'body'];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
