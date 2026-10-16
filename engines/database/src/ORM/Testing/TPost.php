<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Relations\BelongsTo;
use Tavaan\Engine\Database\ORM\Relations\BelongsToMany;
use Tavaan\Engine\Database\ORM\Relations\HasMany;
use Tavaan\Engine\Database\ORM\Relations\MorphMany;
use Tavaan\Support\Str;

/**
 * Fixture تست ORM — پست (cast/enum/scope/mutator/soft-delete).
 *
 * @api internal
 */
class TPost extends Model
{
    protected string $table = 'orm_posts';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    public bool $softDeletes = true;

    /** @var list<string> */
    protected array $fillable = ['user_id', 'title', 'slug', 'body', 'status', 'price', 'views', 'published_at', 'tenant_id'];

    /** @var list<string> */
    protected array $appends = ['excerpt'];

    /** @var array<string, string> */
    protected array $casts = [
        'status' => TPostStatus::class,
        'price' => 'money:IRR',
        'views' => 'int',
        'published_at' => 'immutable_datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TUser::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TComment::class, 'commentable_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TTag::class, 'orm_post_tag', 'post_id', 'tag_id')->withTimestamps();
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(TMedia::class, 'mediable');
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = \strtolower(\str_replace(' ', '-', $value));
    }

    public function getExcerptAttribute(): string
    {
        return Str::limit((string) ($this->attributes['body'] ?? ''), 30);
    }

    public function scopePublished(\Tavaan\Engine\Database\ORM\ModelQueryBuilder $q): \Tavaan\Engine\Database\ORM\ModelQueryBuilder
    {
        $q->where('status', TPostStatus::Published->value);

        return $q;
    }

    public function scopeOfTenant(\Tavaan\Engine\Database\ORM\ModelQueryBuilder $q, int $tenantId): \Tavaan\Engine\Database\ORM\ModelQueryBuilder
    {
        $q->where('tenant_id', $tenantId);

        return $q;
    }
}
