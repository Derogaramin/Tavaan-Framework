<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\BelongsToMany;

/**
 * Fixture تست ORM — برچسب (belongsToMany).
 *
 * @api internal
 */
final class TTag extends Model
{
    protected string $table = 'orm_tags';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['name'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(TPost::class, 'orm_post_tag', 'tag_id', 'post_id');
    }
}
