<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Tests\Fixtures;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Tenancy\BelongsToTenant;
use Tavaan\Engine\Tenancy\RestoresTenantContext;

/**
 * Fixtureهای تست Tenancy.
 *
 * @api internal
 */

/** سفارش tenant-aware (استراتژی A). */
class TOrder extends Model
{
    use BelongsToTenant;

    protected string $table = 'tvn_test_orders';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['title', 'amount', 'tenant_id'];

    protected static function boot(): void
    {
        self::bootBelongsToTenant();
    }
}

/** یادداشت سراسری (بدون scope — allowGlobalTenant). */
class TGlobalNote extends Model
{
    use BelongsToTenant;

    protected string $table = 'tvn_test_notes';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['body'];

    // 🔒 override متد (نه property — ناسازگاری trait property خطای fatal است)
    public function allowGlobalTenant(): bool
    {
        return true;
    }

    protected static function boot(): void
    {
        self::bootBelongsToTenant();
    }
}

/** job صف‌روند با بازیابی context. */
final class TTenantJob
{
    use RestoresTenantContext;

    public mixed $result = null;

    public function __construct()
    {
        $this->captureTenant();
    }

    public function handle(): mixed
    {
        return $this->withTenantContext(function (): ?string {
            return \Tavaan\Engine\Tenancy\TenantContext::identifier();
        });
    }
}
