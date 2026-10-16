<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;

/**
 * Fixture تست ORM — نقش.
 *
 * @api internal
 */
final class TRole extends Model
{
    protected string $table = 'orm_roles';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['title'];
}
