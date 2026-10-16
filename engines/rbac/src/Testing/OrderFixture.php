<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Testing;

use Tavaan\Engine\Database\ORM\Model;

/**
 * Fixture تست RBAC — سفارش (برای Policy/OwnershipScope).
 *
 * @api internal
 */
final class OrderFixture extends Model
{
    protected string $table = 'orders';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['user_id', 'status', 'total'];

    public function getOwnerColumn(): string
    {
        return 'user_id';
    }

    public function isRefundable(): bool
    {
        return ($this->attributes['status'] ?? '') === 'paid';
    }
}
