<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Tests\Feature;

require_once __DIR__ . '/../Fixtures/AdminFixtures.php';

use Tavaan\Engine\Admin\Action;
use Tavaan\Engine\Admin\AdminManager;
use Tavaan\Engine\Admin\Dashboard\Dashboard;
use Tavaan\Engine\Admin\Form\Form;
use Tavaan\Engine\Admin\Resource;
use Tavaan\Engine\Admin\Show;
use Tavaan\Engine\Admin\Tests\Fixtures\AdminTestWidget;
use Tavaan\Engine\Admin\Tests\Fixtures\AdminUserFixture;
use Tavaan\Engine\Admin\Tests\Fixtures\BrokenWidgetFixture;
use Tavaan\Engine\Admin\Tests\Fixtures\StatsWidgetFixture;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Rbac\Gate;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست CRUD Generator پیشخوان — DOC-078 §9 (T-01..T-08) + AC-ADM-01..04.
 *
 * مسیر واقعی: MakesHttpRequests درون‌فرایندی + SQLite in-memory + actingAs +
 * Gate::define — بدون I/O خارجی.
 */
final class AdminCrudTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->createWidgetsTable();

        $config = $this->app()->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('app.debug', true);
        $config->set('session.csrf.except', ['admin/*']);

        $this->definePermissions(allow: true);
        $this->registerResource();
    }

    // ─── T-01: هفت route ─────────────────────────────────────────────────

    public function testResource_GeneratesStandardRoutes(): void
    {
        /** @var Router $router */
        $router = $this->app()->make(Router::class);

        foreach ([
            ['GET', '/admin', 'admin.dashboard'],
            ['GET', '/admin/widgets', 'admin.index'],
            ['GET', '/admin/widgets/create', 'admin.create'],
            ['POST', '/admin/widgets', 'admin.store'],
            ['GET', '/admin/widgets/1', 'admin.show'],
            ['GET', '/admin/widgets/1/edit', 'admin.edit'],
            ['PUT', '/admin/widgets/1', 'admin.update'],
            ['DELETE', '/admin/widgets/1', 'admin.destroy'],
            ['POST', '/admin/widgets/1/actions/publish', 'admin.action'],
            ['POST', '/admin/widgets/actions/delete', 'admin.bulk'],
        ] as [$method, $uri, $expectedName]) {
            $match = $router->matchUri($method, $uri);
            self::assertSame($expectedName, $match->route?->getName(), 'route ناموفق: ' . $method . ' ' . $uri);
        }
    }

    // ─── فهرست + جست‌وجو + مرتب‌سازی + صفحه‌بندی ─────────────────────────

    public function testIndex_ListsSearchesAndPaginates(): void
    {
        $this->widget('آلفا', 300);
        $this->widget('بتا', 100);
        $this->widget('گاما', 200);

        // perPage=2 + sort قطعی (price asc) → صفحهٔ اول: بتا(۱۰۰) گاما(۲۰۰)
        $page1 = $this->actingAs(new AdminUserFixture())->get('/admin/widgets?sort=price&dir=asc');
        $page1->assertOk()->assertSee('بتا')->assertSee('گاما')->assertDontSee('آلفا');
        $page1->assertSee('page=2');

        // صفحهٔ دوم: آلفا(۳۰۰)
        $page2 = $this->actingAs(new AdminUserFixture())->get('/admin/widgets?page=2&sort=price&dir=asc');
        $page2->assertOk()->assertSee('آلفا')->assertDontSee('بتا');

        // صفحه‌بندی بدون sort صریح هم قطعی است (default order = کلید اصلی)
        $unsorted = $this->actingAs(new AdminUserFixture())->get('/admin/widgets');
        $unsorted->assertOk();

        // جست‌وجو
        $search = $this->actingAs(new AdminUserFixture())->get('/admin/widgets?q=' . \rawurlencode('گاما'));
        $search->assertOk()->assertSee('گاما')->assertDontSee('آلفا');

        // مرتب‌سازی مجاز (price asc) — بتا(۱۰۰) پیش از گاما(۲۰۰) در صفحهٔ اول
        $sorted = $this->actingAs(new AdminUserFixture())->get('/admin/widgets?sort=price&dir=asc');
        $sorted->assertOk();
        $body = $sorted->content();
        self::assertTrue(
            \mb_strpos($body, 'بتا') !== false && \mb_strpos($body, 'گاما') !== false,
            'هر دو ردیف صفحهٔ اول باید حاضر باشند',
        );
        self::assertTrue(
            \mb_strpos($body, 'بتا') < \mb_strpos($body, 'گاما'),
            'بتا (۱۰۰) باید پیش از گاما (۲۰۰) باشد',
        );
    }

    // ─── T-03: فرم + validation خودکار + store ───────────────────────────

    public function testCreateForm_RendersAllFieldTypes(): void
    {
        $response = $this->actingAs(new AdminUserFixture())->get('/admin/widgets/create');

        $response->assertOk()
            ->assertSee('name="title"')
            ->assertSee('name="price"')
            ->assertSee('name="status"')
            ->assertSee('name="published_at"')
            ->assertSee('name="active"')
            ->assertSee('name="description"')
            ->assertSee('1403/04/12');   // placeholder جلالی
    }

    public function testStore_ValidationFailure_DoesNotPersist(): void
    {
        $response = $this->actingAs(new AdminUserFixture())->post('/admin/widgets', [
            'title' => '',            // required → رد
            'price' => 'abc',         // numeric → رد
        ]);

        $response->assertStatus(302);   // back با errors
        self::assertSame(0, AdminTestWidget::query()->count());
    }

    public function testStore_ValidData_PersistsWhitelistedFields(): void
    {
        $response = $this->actingAs(new AdminUserFixture())->post('/admin/widgets', [
            'title' => 'ویجت نو',
            'price' => '1500',
            'status' => 'draft',
            'active' => '1',
            'summary' => 'خلاصه',
            'evil_extra_field' => 'should-be-dropped',   // خارج از whitelist فرم
        ]);

        $response->assertRedirect('/admin/widgets');

        $widget = AdminTestWidget::query()->first();
        self::assertNotNull($widget);
        self::assertSame('ویجت نو', $widget->title);
        self::assertSame(1500, $widget->price);
        self::assertTrue((bool) $widget->active);
        self::assertNull($widget->{'evil_extra_field'} ?? null);   // mass assignment محافظت‌شده (§7)
    }

    // ─── T-07: jalali_date دوطرفه ────────────────────────────────────────

    public function testJalaliDate_StoredGregorian_DisplayedJalali(): void
    {
        $this->actingAs(new AdminUserFixture())->post('/admin/widgets', [
            'title' => 'تاریخ‌دار',
            'price' => 10,
            'published_at' => '1403/04/12',
        ])->assertRedirect('/admin/widgets');

        $widget = AdminTestWidget::query()->first();
        self::assertNotNull($widget);
        self::assertSame('2024-07-02', (string) $widget->published_at);   // DB: میلادی UTC

        $id = (string) $widget->getKey();
        $this->actingAs(new AdminUserFixture())
            ->get('/admin/widgets/' . $id . '/edit')
            ->assertOk()
            ->assertSee('1403/04/12');   // UI: جلالی
    }

    // ─── T-04: sanitizer در richtext ─────────────────────────────────────

    public function testRichtext_Sanitized_OnStore(): void
    {
        $this->actingAs(new AdminUserFixture())->post('/admin/widgets', [
            'title' => 'ریچ',
            'price' => 5,
            'description' => '<p>سلام</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
        ])->assertRedirect('/admin/widgets');

        $widget = AdminTestWidget::query()->first();
        self::assertNotNull($widget);
        $description = (string) $widget->description;

        self::assertStringContainsString('<p>سلام</p>', $description);
        self::assertStringNotContainsString('<script>', $description);
        self::assertStringNotContainsString('javascript:', $description);
    }

    // ─── show / update / delete ──────────────────────────────────────────

    public function testShow_RendersDefinedFields(): void
    {
        $widget = $this->widget('نمایشی', 42);
        $response = $this->actingAs(new AdminUserFixture())->get('/admin/widgets/' . $widget->getKey());

        $response->assertOk()->assertSee('نمایشی')->assertSee('42');
    }

    public function testUpdate_ChangesPersist(): void
    {
        $widget = $this->widget('قدیم', 10);
        $this->actingAs(new AdminUserFixture())->post('/admin/widgets/' . $widget->getKey(), [
            '_method' => 'PUT',
            'title' => 'جدید',
            'price' => '99',
            'status' => 'active',
        ])->assertRedirect('/admin/widgets');

        $fresh = AdminTestWidget::find((string) $widget->getKey());
        self::assertNotNull($fresh);
        self::assertSame('جدید', $fresh->title);
        self::assertSame(99, $fresh->price);
    }

    public function testDestroy_RemovesRecord(): void
    {
        $widget = $this->widget('حذف‌شدنی', 1);
        $this->actingAs(new AdminUserFixture())->post('/admin/widgets/' . $widget->getKey(), ['_method' => 'DELETE'])
            ->assertRedirect('/admin/widgets');

        self::assertNull(AdminTestWidget::find((string) $widget->getKey()));
    }

    // ─── actions ─────────────────────────────────────────────────────────

    public function testSingleAction_RunsHandler(): void
    {
        $widget = $this->widget('پیش‌نویس', 1, status: 'draft');
        $this->actingAs(new AdminUserFixture())->post('/admin/widgets/' . $widget->getKey() . '/actions/publish')
            ->assertStatus(302);

        $fresh = AdminTestWidget::find((string) $widget->getKey());
        self::assertSame('active', (string) $fresh->status);
    }

    public function testBulkAction_DeletesSelected(): void
    {
        $a = $this->widget('یک', 1);
        $b = $this->widget('دو', 2);
        $c = $this->widget('سه', 3);

        $this->actingAs(new AdminUserFixture())->post('/admin/widgets/actions/delete', [
            'ids' => [(string) $a->getKey(), (string) $b->getKey()],
        ])->assertStatus(302);

        self::assertNull(AdminTestWidget::find((string) $a->getKey()));
        self::assertNull(AdminTestWidget::find((string) $b->getKey()));
        self::assertNotNull(AdminTestWidget::find((string) $c->getKey()));
    }

    // ─── T-02: permission روی هر عملیات ──────────────────────────────────

    public function testGuest_Receives401_OnJsonRequest(): void
    {
        // درخواست وب → redirect به ورود؛ درخواست JSON → 401 (رفتار استاندارد auth)
        $this->getJson('/admin/widgets')->assertUnauthorized();
        $this->get('/admin/widgets')->assertRedirect();
    }

    public function testAuthenticatedWithoutPermission_Receives403(): void
    {
        $this->definePermissions(allow: false);

        $this->actingAs(new AdminUserFixture())->get('/admin/widgets')->assertForbidden();
        $this->actingAs(new AdminUserFixture())->get('/admin/widgets/create')->assertForbidden();
    }

    public function testStoreDenied_ForUnauthorizedUser(): void
    {
        $this->definePermissions(allow: true);
        // فقط create را ببند
        $gate = $this->app()->make(Gate::class);
        $gate->define('admin.widget.create', static fn (): bool => false);

        $this->actingAs(new AdminUserFixture())->post('/admin/widgets', ['title' => 'x', 'price' => 1])
            ->assertForbidden();
        self::assertSame(0, AdminTestWidget::query()->count());
    }

    // ─── T-05: منو بر اساس مجوز ──────────────────────────────────────────

    public function testMenu_FilteredByPermission(): void
    {
        $allowed = $this->actingAs(new AdminUserFixture())->get('/admin');
        $allowed->assertOk()->assertSee('ویجت‌ها');   // منبع در sidebar

        $this->definePermissions(allow: false);
        $denied = $this->actingAs(new AdminUserFixture())->get('/admin');
        $denied->assertOk()->assertDontSee('ویجت‌ها')->assertSee('داشبورد');
    }

    // ─── T-06: ویجت داشبورد ──────────────────────────────────────────────

    public function testDashboard_RendersWidgetsAndCharts_WithIsolation(): void
    {
        /** @var AdminManager $admin */
        $admin = $this->app()->make(AdminManager::class);
        $admin->dashboard(function (Dashboard $d): void {
            $d->widget(StatsWidgetFixture::class, ['label' => 'فروش امروز']);
            $d->widget(BrokenWidgetFixture::class);   // خطا ایزوله می‌شود
            $d->chart('sales', ['فروردین' => 10, 'اردیبهشت' => 24], 'line', 'نمودار فروش');
        });

        $response = $this->actingAs(new AdminUserFixture())->get('/admin');

        $response->assertOk()
            ->assertSee('فروش امروز: ۱٬۲۳۴٬۰۰۰ ریال')
            ->assertSee('نمودار فروش')
            ->assertSee('<svg')
            ->assertSee('widget crashed');   // پیام خطای ویجت خراب (نه crash صفحه)
    }

    // ─── T-08: RTL و i18n ────────────────────────────────────────────────

    public function testLayout_IsRtlPersian(): void
    {
        $response = $this->actingAs(new AdminUserFixture())->get('/admin');

        $response->assertOk()
            ->assertSee('dir="rtl"')
            ->assertSee('lang="fa"')
            ->assertSee('داشبورد')
            ->assertSee('Vazirmatn');
    }

    // ─── AC-ADM-04: theme override ───────────────────────────────────────

    public function testThemeOverride_VendorPathTakesPriority(): void
    {
        // ViewFinder ابتدا resources/views/vendor/admin را می‌بیند (§8)
        $overrideDir = (string) TVN_BASE_PATH . '/resources/views/vendor/admin';
        $made = !\is_dir($overrideDir) && \mkdir($overrideDir, 0775, true);
        $overrideFile = $overrideDir . '/dashboard.tv.php';
        \file_put_contents($overrideFile, 'OVERRIDE-MARKER {{ $appTitle ?? "" }}');

        try {
            $response = $this->actingAs(new AdminUserFixture())->get('/admin');
            $response->assertOk()->assertSee('OVERRIDE-MARKER');
        } finally {
            @\unlink($overrideFile);
            if ($made) {
                @\rmdir($overrideDir);
                @\rmdir(\dirname($overrideDir));
            }
        }
    }

    public function testUnknownResourceKey_Returns404(): void
    {
        $this->actingAs(new AdminUserFixture())->get('/admin/nonexistent')->assertNotFound();
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    private function createWidgetsTable(): void
    {
        /** @var SchemaBuilder $schema */
        $schema = $this->app()->make(SchemaBuilder::class);
        if ($schema->hasTable('admin_test_widgets')) {
            return;
        }
        $schema->create('admin_test_widgets', static function (Blueprint $t): void {
            $t->string('id', 26)->primary();
            $t->string('title', 190);
            $t->text('summary')->nullable();
            $t->bigInteger('price')->default(0);
            $t->string('status', 30)->default('draft');
            $t->string('published_at', 30)->nullable();
            $t->boolean('active')->default(false);
            $t->text('description')->nullable();
            $t->timestamps();
        });
    }

    private function registerResource(): void
    {
        /** @var AdminManager $admin */
        $admin = $this->app()->make(AdminManager::class);
        $admin->resource(AdminTestWidget::class, static function (Resource $r): void {
            $r->title('ویجت‌ها')
                ->icon('🧩')
                ->uri('widgets')
                ->permission('admin.widget')
                ->columns(['id', 'title', 'price', 'status', 'published_at'])
                ->searchable(['title'])
                ->sortable(['price', 'published_at'])
                ->perPage(2);

            $r->form(static function (Form $f): void {
                $f->text('title')->required()->maxLength(100);
                $f->textarea('summary')->nullable();
                $f->money('price')->min(0);
                $f->select('status')->options(['draft' => 'پیش‌نویس', 'active' => 'فعال']);
                $f->jalaliDate('published_at')->nullable();
                $f->switch('active');
                $f->richtext('description')->nullable()->sanitizer('rich_text');
            });

            $r->actions([
                Action::make('publish')->permission('admin.widget.publish')->handler(
                    static fn (AdminTestWidget $model): bool => $model->update(['status' => 'active']),
                ),
                Action::bulk('delete')->permission('admin.widget.delete'),
            ]);

            $r->show(static function (Show $s): void {
                $s->fields(['title', 'price', 'status', 'published_at']);
            });
        });

        $admin->registerRoutes($this->app()->make(Router::class));
    }

    private function definePermissions(bool $allow): void
    {
        /** @var Gate $gate */
        $gate = $this->app()->make(Gate::class);
        foreach (['view', 'create', 'update', 'delete', 'publish'] as $operation) {
            $gate->define('admin.widget.' . $operation, static fn (): bool => $allow);
        }
    }

    private function widget(string $title, int $price, string $status = 'active'): AdminTestWidget
    {
        return AdminTestWidget::create([
            'title' => $title,
            'price' => $price,
            'status' => $status,
            'active' => true,
        ]);
    }
}
