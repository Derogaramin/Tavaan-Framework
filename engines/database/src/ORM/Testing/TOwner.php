<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\MorphTo;

/**
 * Fixture تست ORM — مالک چندریختی.
 *
 * @api internal
 */
final class TOwner extends Model
{
    protected string $table = 'orm_owners';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['ownable_type', 'ownable_id', 'name'];

    public function ownable(): MorphTo
    {
        return $this->morphTo();
    }
}
