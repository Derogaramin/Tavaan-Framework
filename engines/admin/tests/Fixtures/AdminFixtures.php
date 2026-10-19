<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Tests\Fixtures;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Support\Renderable;
use Tavaan\Engine\Database\ORM\Model;

/**
 * Fixtureهای تست موتور Admin (DOC-078 §9).
 *
 * @api internal
 */

/**
 * مدل تست روی جدول `admin_test_widgets` (در تست با SchemaBuilder ساخته می‌شود).
 */
final class AdminTestWidget extends Model
{
    protected string $table = 'admin_test_widgets';

    /** @var list<string> */
    protected array $fillable = ['title', 'summary', 'price', 'status', 'published_at', 'active', 'description'];

    protected array $casts = [
        'price' => 'int',
        'active' => 'bool',
    ];
}

/**
 * کاربر مدیر Fixture (بدون DB).
 */
final class AdminUserFixture implements Authenticatable
{
    private string $rememberToken = '';

    public function __construct(private readonly int $id = 7)
    {
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken === '' ? null : $this->rememberToken;
    }

    public function setRememberToken(string $value): void
    {
        $this->rememberToken = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}

/**
 * ویجت داشبورد Fixture (Renderable — §5).
 */
final class StatsWidgetFixture implements Renderable
{
    public function render(): mixed
    {
        return '<div class="stat">فروش امروز: ۱٬۲۳۴٬۰۰۰ ریال</div>';
    }
}

/**
 * ویجت خراب — ایزولاسیون خطای ویجت در داشبورد (§5).
 */
final class BrokenWidgetFixture implements Renderable
{
    public function render(): mixed
    {
        throw new \RuntimeException('widget crashed');
    }
}
