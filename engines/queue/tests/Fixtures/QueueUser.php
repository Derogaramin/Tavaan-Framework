<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Engine\Database\ORM\Model;

/** Fixture: مدل ORM برای تست SerializesModels (فقط کلاس+کلید در payload). */
final class QueueUser extends Model
{
    protected string $table = 'queue_users';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['name', 'email'];

    /** @var list<string> */
    protected array $guarded = ['id'];
}
