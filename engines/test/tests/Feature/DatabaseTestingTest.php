<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Feature;

require_once __DIR__ . '/../Fixtures/WidgetFixtures.php';

use Tavaan\Engine\Database\Migration\Migrator;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Testing\Database\Factory;
use Tavaan\Testing\Database\Faker;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;
use Tavaan\Testing\Tests\Fixtures\GizmoModel;
use Tavaan\Testing\Tests\Fixtures\GizmoModelFactory;
use Tavaan\Testing\Tests\Fixtures\WidgetFactory;
use Tavaan\Testing\Tests\Fixtures\WidgetModel;

/**
 * تست DatabaseTest با SQLite in-memory + migrate (DOC-084E §6 — AC-TEST-03)
 * و Factory/Model::factory().
 */
final class DatabaseTestingTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->createWidgetsTable();
    }

    private function createWidgetsTable(): void
    {
        /** @var SchemaBuilder $schema */
        $schema = $this->app()->make(SchemaBuilder::class);
        if ($schema->hasTable('tvn_test_widgets')) {
            return;
        }
        $schema->create('tvn_test_widgets', static function (Blueprint $t): void {
            $t->string('id', 26)->primary();
            $t->string('name', 190);
            $t->bigInteger('price')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    // ─── AC-TEST-03: in-memory + migrate ──────────────────────────────────

    public function testSetUpDatabase_UsesInMemorySqlite(): void
    {
        $config = $this->app()->make('config');
        self::assertSame('sqlite_memory', $config->get('database.default'));

        $row = $this->databaseConnection()->selectOne('SELECT 1 AS m');
        self::assertSame(1, (int) ($row['m'] ?? 0));
    }

    public function testMigrate_CreatesRepositoryAndEngineTables(): void
    {
        $connection = $this->databaseConnection();

        self::assertTrue($this->app()->make(SchemaBuilder::class)->hasTable('tvn_migrations'));

        $migrated = $connection->table('tvn_migrations')->count();
        self::assertGreaterThan(5, $migrated, 'migrationهای موتورها باید اجرا شده باشند');

        // نمونه: جدول موتور cache از engines/cache/migrations
        self::assertTrue($this->app()->make(SchemaBuilder::class)->hasTable('cache'));
    }

    public function testMigrate_SecondRun_HasNothingPending(): void
    {
        $result = $this->migrate();
        self::assertSame([], $result->migrations);
        self::assertTrue($result->success);
    }

    public function testMigrate_IsIsolatedPerTestInstance(): void
    {
        // دادهٔ تست قبلی نباید دیده شود (هر متد تست → app/DB تازه)
        self::assertSame(0, $this->databaseConnection()->table('tvn_test_widgets')->count());
    }

    // ─── Factory ──────────────────────────────────────────────────────────

    public function testFactoryCreate_PersistsToInMemoryDatabase(): void
    {
        /** @var WidgetModel $widget */
        $widget = (new WidgetFactory(new Faker(7)))->create(['name' => 'ویجت تست']);

        self::assertTrue($widget->exists);
        self::assertNotEmpty((string) $widget->getKey());

        $row = $this->databaseConnection()->table('tvn_test_widgets')
            ->where('id', $widget->getKey())
            ->first();

        self::assertNotNull($row);
        self::assertSame('ویجت تست', $row['name']);
    }

    public function testFactoryMake_DoesNotPersist(): void
    {
        $widget = (new WidgetFactory(new Faker(9)))->make();

        self::assertFalse($widget->exists);
        self::assertSame(0, $this->databaseConnection()->table('tvn_test_widgets')->count());
    }

    public function testFactoryCountAndStates(): void
    {
        $factory = (new WidgetFactory(new Faker(11)))->count(3)->inactive();
        $widgets = $factory->create();

        self::assertCount(3, $widgets);
        self::assertSame(3, $this->databaseConnection()->table('tvn_test_widgets')->where('active', false)->count());

        // state بازگشتی است: factory پایه دست‌نخورده می‌ماند
        $single = (new WidgetFactory(new Faker(11)))->create();
        self::assertTrue((bool) $single->active);
    }

    public function testFactoryOverrides_WinOverDefinition(): void
    {
        /** @var WidgetModel $widget */
        $widget = (new WidgetFactory(new Faker(13)))->priced(500)->create(['price' => 999]);

        self::assertSame(999, $widget->price);
    }

    public function testModelFactory_ExplicitFactoryProperty(): void
    {
        $factory = WidgetModel::factory();

        self::assertInstanceOf(WidgetFactory::class, $factory);

        /** @var WidgetModel $widget */
        $widget = WidgetModel::factory()->create();
        self::assertTrue($widget->exists);
        self::assertSame(1, $this->databaseConnection()->table('tvn_test_widgets')->count());
    }

    public function testFactoryFor_NamingConvention(): void
    {
        // بدون $factory صریح → قرارداد `<NS>\<Model>Factory`
        $factory = Factory::for(GizmoModel::class);
        self::assertInstanceOf(GizmoModelFactory::class, $factory);

        $gizmo = $factory->make();
        self::assertInstanceOf(GizmoModel::class, $gizmo);
        self::assertNotEmpty((string) $gizmo->label);
    }

    public function testModelFactory_DeterministicWithSeededFaker(): void
    {
        $a = (new WidgetFactory(new Faker(99)))->make();
        $b = (new WidgetFactory(new Faker(99)))->make();

        self::assertSame($a->name, $b->name);
        self::assertSame($a->price, $b->price);
    }

    public function testFactoryFor_UnknownModel_Throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Factory::for(Migrator::class);   // کلاس وجود دارد ولی Factory قراردادنامی ندارد
    }
}
