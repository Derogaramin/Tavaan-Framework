<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;

/**
 * Fixture تست ORM — پست با Global Scope چنداجاره‌ای.
 *
 * @api internal
 */
final class TTenantPost extends Model
{
    protected string $table = 'orm_posts';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['user_id', 'title', 'slug', 'tenant_id', 'status'];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new TenantScopeFixture());
    }
}
