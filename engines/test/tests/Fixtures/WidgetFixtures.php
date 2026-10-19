<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Fixtures;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Testing\Database\Factory;
use Tavaan\Testing\Database\Faker;

/**
 * Fixtureهای تست موتور Test — Database/Factory/Http (DOC-084E §5-§6).
 *
 * @api internal
 */

/**
 * مدل Fixture روی جدول `tvn_test_widgets` (در تست با SchemaBuilder ساخته می‌شود).
 *
 * Factory صریح با `public static string $factory` (مسیر اولِ قرارداد).
 */
final class WidgetModel extends Model
{
    /** @var class-string<WidgetFactory> */
    public static string $factory = WidgetFactory::class;

    protected string $table = 'tvn_test_widgets';

    /** @var list<string> */
    protected array $fillable = ['name', 'price', 'active'];

    protected array $casts = [
        'price' => 'int',
        'active' => 'bool',
    ];
}

/**
 * مدل Fixture برای قرارداد نام (`<NS>\GizmoModelFactory` — بدون $factory صریح).
 */
final class GizmoModel extends Model
{
    protected string $table = 'tvn_test_gizmos';

    /** @var list<string> */
    protected array $fillable = ['label'];
}

/**
 * Factory قراردادنامی GizmoModel (فقط make — بدون نیاز به جدول).
 */
final class GizmoModelFactory extends Factory
{
    /** @var class-string<GizmoModel> */
    protected string $model = GizmoModel::class;

    public function definition(): array
    {
        return ['label' => $this->faker->word()];
    }
}

/**
 * Factory قراردادنامی WidgetModel (`<NS>\WidgetFactory` → همان namespace).
 */
final class WidgetFactory extends Factory
{
    /** @var class-string<WidgetModel> */
    protected string $model = WidgetModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'price' => $this->faker->numberBetween(1000, 99999),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['active' => false]);
    }

    public function priced(int $price): self
    {
        return $this->state(['price' => $price]);
    }
}

/**
 * کاربر Fixture برای `actingAs` (بدون DB).
 */
final class TestUserFixture implements Authenticatable
{
    private string $rememberToken = '';

    public function __construct(private readonly int $id = 42, private readonly string $name = 'کاربر تست')
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

    public function name(): string
    {
        return $this->name;
    }
}
