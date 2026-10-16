<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Tests\Feature;

require_once __DIR__ . '/../Fixtures/TenancyFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Tenancy\Console\TenantQueryGuard;
use Tavaan\Engine\Tenancy\DatabaseTenantManager;
use Tavaan\Engine\Tenancy\Events\TenantCreated;
use Tavaan\Engine\Tenancy\Events\TenantQuotaExceeded;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotFoundException;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotResolvedException;
use Tavaan\Engine\Tenancy\Exceptions\TenantQuotaExceededException;
use Tavaan\Engine\Tenancy\Exceptions\TenantSuspendedException;
use Tavaan\Engine\Tenancy\Exceptions\TenancyException;
use Tavaan\Engine\Tenancy\Middleware\IdentifyTenantMiddleware;
use Tavaan\Engine\Tenancy\QuotaGuard;
use Tavaan\Engine\Tenancy\Resolvers\DefaultResolver;
use Tavaan\Engine\Tenancy\Resolvers\DomainResolver;
use Tavaan\Engine\Tenancy\Resolvers\HeaderResolver;
use Tavaan\Engine\Tenancy\Resolvers\PathResolver;
use Tavaan\Engine\Tenancy\Resolvers\QueryResolver;
use Tavaan\Engine\Tenancy\Resolvers\TokenResolver;
use Tavaan\Engine\Tenancy\Tenant;
use Tavaan\Engine\Tenancy\TenantConfigRepository;
use Tavaan\Engine\Tenancy\TenantContext;
use Tavaan\Engine\Tenancy\TenantManager;
use Tavaan\Engine\Tenancy\TenantProvisioner;
use Tavaan\Engine\Tenancy\TenantResolverChain;
use Tavaan\Engine\Tenancy\Tests\Fixtures\TGlobalNote;
use Tavaan\Engine\Tenancy\Tests\Fixtures\TOrder;
use Tavaan\Engine\Tenancy\Tests\Fixtures\TTenantJob;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Tenancy — DOC-082E §11 (T-01..T-07) + DOC-028 §9/§10.
 */
final class TenancyTest extends TestCase
{
    private Application $app;

    private TenantManager $tenants;

    private string $tmp;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        Model::flushTestState();

        $this->tmp = \sys_get_temp_dir() . '/tvn-tenancy-test-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp, 0775, true);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $config->set('cache.default', 'array');
        $config->set('tenancy.enabled', true);
        $config->set('tenancy.strategy', 'shared');
        $config->set('tenancy.sqlite_path', $this->tmp);
        $config->set('tenancy.storage_path', $this->tmp . '/files');

        $schema = $this->app->make('schema');
        $schema->create('tvn_tenants', function (Blueprint $t): void {
            $t->id();
            $t->string('identifier', 64)->unique();
            $t->string('name', 190);
            $t->string('domain', 190)->nullable();
            $t->string('plan', 50)->default('free');
            $t->text('settings')->nullable();
            $t->boolean('is_active')->default(true);
            $t->text('quota')->nullable();
            $t->timestamps(3);
        });
        $schema->create('tvn_test_orders', function (Blueprint $t): void {
            $t->id();
            $t->unsignedInteger('tenant_id')->nullable();
            $t->string('title');
            $t->integer('amount')->default(0);
            $t->timestamps(3);
        });
        $schema->create('tvn_test_notes', function (Blueprint $t): void {
            $t->id();
            $t->unsignedInteger('tenant_id')->nullable();
            $t->string('body')->default('');
            $t->timestamps(3);
        });

        TenantContext::clear();
        $this->tenants = $this->app->make('tenant');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Clock::setTestNow(null);
        Model::flushTestState();
        $this->rrmdir($this->tmp);
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }

    private function createTenant(string $identifier, array $extra = []): TenantInterface
    {
        return $this->tenants->create(['identifier' => $identifier, 'name' => \ucfirst($identifier), ...$extra]);
    }

    // ─── T-01: Resolve از domain/header/path/token ───────────────────────

    public function testDomainResolverFromSubdomain(): void
    {
        $resolver = new DomainResolver('.example.com');
        $request = Request::create('http://shop1.example.com/products');
        $this->assertSame('shop1', $resolver->resolve($request));

        // دامنهٔ بدون subdomain → null
        $this->assertNull($resolver->resolve(Request::create('http://example.com/products')));
        // subdomain چندسطحی → null
        $this->assertNull($resolver->resolve(Request::create('http://a.b.example.com/')));
    }

    public function testDomainResolverFullDomainLookup(): void
    {
        $resolver = new DomainResolver('', fn (string $d): ?string => $d === 'acme.ir' ? 'acme' : null);
        $this->assertSame('acme', $resolver->resolve(Request::create('http://acme.ir/')));
        $this->assertNull($resolver->resolve(Request::create('http://other.ir/')));
    }

    public function testHeaderResolver(): void
    {
        $resolver = new HeaderResolver();
        $request = Request::create('http://localhost/', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => 'acme']);
        $this->assertSame('acme', $resolver->resolve($request));

        // 🔒 ورودی مخرب/خیلی بلند → null
        $evil = Request::create('http://localhost/', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => \str_repeat('x', 65)]);
        $this->assertNull($resolver->resolve($evil));
        $this->assertNull($resolver->resolve(Request::create('http://localhost/')));
    }

    public function testPathResolver(): void
    {
        $resolver = new PathResolver('t');
        $this->assertSame('shop1', $resolver->resolve(Request::create('http://localhost/t/shop1/products')));
        $this->assertNull($resolver->resolve(Request::create('http://localhost/products')));
        $this->assertNull($resolver->resolve(Request::create('http://localhost/t/')));
    }

    public function testQueryResolverDevOnly(): void
    {
        $resolver = new QueryResolver('tenant', true);
        // محیط تست = testing → مجاز
        $this->assertSame('acme', $resolver->resolve(Request::create('http://localhost/?tenant=acme')));

        // 🔒 در production غیرفعال
        $previous = $_SERVER['TVN_APP_ENV'] ?? null;
        $_SERVER['TVN_APP_ENV'] = 'production';
        try {
            $this->assertNull($resolver->resolve(Request::create('http://localhost/?tenant=acme')));
        } finally {
            if ($previous === null) {
                unset($_SERVER['TVN_APP_ENV']);
            } else {
                $_SERVER['TVN_APP_ENV'] = $previous;
            }
        }
    }

    public function testTokenResolverFromJwtClaim(): void
    {
        $resolver = new TokenResolver('tenant_id', true);

        $payload = $this->b64url((string) \json_encode(['sub' => 1, 'tenant_id' => 'acme', 'exp' => \time() + 3600]));
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.' . $payload . '.sig';
        $request = Request::create('http://localhost/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);
        $this->assertSame('acme', $resolver->resolve($request));

        // 🔒 توکن منقضی → null
        $expired = $this->b64url((string) \json_encode(['tenant_id' => 'acme', 'exp' => \time() - 10]));
        $request2 = Request::create('http://localhost/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer x.' . $expired . '.sig']);
        $this->assertNull($resolver->resolve($request2));

        // decodeUnverified=false → فقط attribute
        $strict = new TokenResolver('tenant_id', false);
        $this->assertNull($strict->resolve($request));
        $withAttr = Request::create('http://localhost/')->withAttribute('tenant_id', 42);
        $this->assertSame('42', $strict->resolve($withAttr));
    }

    private function b64url(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }

    public function testResolverChainOrderAndDefault(): void
    {
        $chain = new TenantResolverChain([
            new HeaderResolver(),
            new PathResolver(),
            new DefaultResolver('fallback'),
        ]);

        // header بر path اولویت دارد
        $request = Request::create('http://localhost/t/from-path', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => 'from-header']);
        $this->assertSame('from-header', $chain->resolve($request));

        // بدون header → path
        $this->assertSame('from-path', $chain->resolve(Request::create('http://localhost/t/from-path')));

        // هیچ‌کدام → default
        $this->assertSame('fallback', $chain->resolve(Request::create('http://localhost/')));
    }

    // ─── Middleware ──────────────────────────────────────────────────────

    public function testMiddlewareSetsAndClearsContext(): void
    {
        $this->createTenant('acme');

        /** @var IdentifyTenantMiddleware $mw */
        $mw = $this->app->make(IdentifyTenantMiddleware::class);
        $request = Request::create('http://localhost/dashboard', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => 'acme']);

        /** @var Response $response */
        $response = $mw->handle($request, static function (Request $r): Response {
            // context داخل handler ست شده + attribute تزریق شده
            $tenant = $r->attribute('tenant');
            TestCase::assertTrue($tenant instanceof TenantInterface);
            TestCase::assertSame('acme', TenantContext::identifier());

            return Response::json(['tenant' => TenantContext::identifier()]);
        });

        $this->assertSame(200, $response->statusCode());
        $this->assertSame('acme', TenantContext::identifier());

        // 🔒 TEN-SEC-05: پس از terminate پاک می‌شود
        $mw->terminate($request, $response);
        $this->assertNull(TenantContext::id());
    }

    public function testMiddlewareThrowsForUnknownTenant(): void
    {
        /** @var IdentifyTenantMiddleware $mw */
        $mw = $this->app->make(IdentifyTenantMiddleware::class);
        $request = Request::create('http://localhost/', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => 'ghost']);

        $this->expectException(TenantNotFoundException::class);
        $mw->handle($request, static fn (): Response => Response::json([]));
    }

    public function testMiddlewareThrowsForSuspendedTenant(): void
    {
        $tenant = $this->createTenant('suspended-co');
        $this->tenants->deactivate($tenant);

        /** @var IdentifyTenantMiddleware $mw */
        $mw = $this->app->make(IdentifyTenantMiddleware::class);
        $request = Request::create('http://localhost/', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => 'suspended-co']);

        $this->expectException(TenantSuspendedException::class);
        $mw->handle($request, static fn (): Response => Response::json([]));
    }

    // ─── T-02: Row-Level isolation (استراتژی A) ──────────────────────────

    public function testRowLevelIsolationBetweenTenants(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');

        TenantContext::run($a, static function (): void {
            TOrder::create(['title' => 'سفارش آلفا ۱', 'amount' => 100]);
            TOrder::create(['title' => 'سفارش آلفا ۲', 'amount' => 200]);
        });
        TenantContext::run($b, static function (): void {
            TOrder::create(['title' => 'سفارش بتا', 'amount' => 300]);
        });

        TenantContext::run($a, static function (): void {
            $orders = TOrder::all();
            TestCase::assertCount(2, $orders);
            foreach ($orders as $order) {
                TestCase::assertStringContainsString('آلفا', (string) $order->getAttribute('title'));
            }
        });

        TenantContext::run($b, static function (): void {
            $orders = TOrder::all();
            TestCase::assertCount(1, $orders);
            TestCase::assertSame(300, (int) $orders[0]->getAttribute('amount'));
        });
    }

    public function testObserverAutoFillsTenantId(): void
    {
        $a = $this->createTenant('alpha');

        $order = TenantContext::run($a, static fn (): TOrder => TOrder::create(['title' => 't', 'amount' => 1]));
        $this->assertSame($a->id(), $order->getAttribute('tenant_id'));
    }

    public function testInsertWithoutContextFails(): void
    {
        // 🔒 TEN-SEC-06 مانند: بدون context → fail نه اجرای با tenant قبلی
        TenantContext::clear();
        $this->expectException(TenantNotResolvedException::class);
        TOrder::create(['title' => 'orphane']);
    }

    public function testQueryWithoutContextFails(): void
    {
        TenantContext::clear();
        $this->expectException(TenantNotResolvedException::class);
        TOrder::all();
    }

    public function testTenantIdIsNotMassAssignable(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');

        // 🔒 TEN-SEC-02: تلاش برای ست‌کردن tenant_id از ورودی کاربر → fail صریح
        $thrown = false;
        TenantContext::run($a, function () use ($b, &$thrown): void {
            try {
                TOrder::create(['title' => 'hack', 'tenant_id' => $b->id()]);
            } catch (\Tavaan\Engine\Database\ORM\Exceptions\MassAssignmentException $e) {
                $thrown = true;
            }
        });
        $this->assertTrue($thrown, ' MassAssignmentException انتظار می‌رفت');

        // ایجاد بدون tenant_id → خودکار از context پر می‌شود
        $order = TenantContext::run($a, static fn (): TOrder => TOrder::create(['title' => 'ok']));
        $this->assertSame($a->id(), $order->getAttribute('tenant_id'));

        // setAttribute مستقیم (مسیر provisioning) همچنان ممکن است
        $forced = TenantContext::run($a, static function () use ($b): TOrder {
            $o = new TOrder();
            $o->title = 'admin-set';
            $o->setAttribute('tenant_id', $b->id());
            $o->save();

            return $o;
        });
        $this->assertSame($b->id(), $forced->getAttribute('tenant_id'));
    }

    public function testWithoutCurrentScopeSeesAllRows(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');
        TenantContext::run($a, static fn () => TOrder::create(['title' => 'a1']));
        TenantContext::run($b, static fn () => TOrder::create(['title' => 'b1']));

        TenantContext::run($a, function (): void {
            $all = $this->tenants->withoutCurrentScope(static fn (): array => TOrder::all(), 'audit-test');
            $this->assertCount(2, $all);

            // پس از اتمام، scope برمی‌گردد
            $this->assertCount(1, TOrder::all());
        });
    }

    public function testGlobalTenantModelWorksWithoutContext(): void
    {
        // allowGlobalTenant=true → بدون context هم کوئری/insert ممکن است
        TenantContext::clear();
        TGlobalNote::create(['body' => 'یادداشت سراسری']);
        $notes = TGlobalNote::all();
        $this->assertCount(1, $notes);

        // با context → scope اعمال می‌شود (tenant_id پر می‌شود)
        $a = $this->createTenant('alpha');
        TenantContext::run($a, static function (): void {
            TGlobalNote::create(['body' => 'یادداشت آلفا']);
        });
        TenantContext::run($a, function (): void {
            $this->assertCount(1, TGlobalNote::all());   // فقط یادداشت آلفا (tenant_id=alpha)
        });
    }

    // ─── Context mechanics ───────────────────────────────────────────────

    public function testRunRestoresPreviousContextEvenOnException(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');

        TenantContext::set($a);
        try {
            TenantContext::run($b, static function (): void {
                throw new \RuntimeException('x');
            });
        } catch (\RuntimeException) {
            // انتظار
        }
        $this->assertSame('alpha', TenantContext::identifier());

        // تودرتو
        $result = TenantContext::run($b, static fn (): string => TenantContext::run($a, static fn (): string => TenantContext::identifier() . '<' . TenantContext::identifier()));
        $this->assertSame('alpha<alpha', $result);
        $this->assertSame('alpha', TenantContext::identifier());
        TenantContext::clear();
    }

    // ─── T-03: Cache isolation ───────────────────────────────────────────

    public function testCacheIsScopedPerTenant(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');

        $cache = $this->app->make('cache')->store('tenant');

        TenantContext::run($a, static function () use ($cache): void {
            $cache->put('greeting', 'from-alpha', 60);
        });

        TenantContext::run($b, static function () use ($cache): void {
            TestCase::assertNull($cache->get('greeting'));   // 🔒 نشت ندارد
            $cache->put('greeting', 'from-beta', 60);
        });

        TenantContext::run($a, static function () use ($cache): void {
            TestCase::assertSame('from-alpha', $cache->get('greeting'));
        });

        // بدون context → bucket سراسری جدا
        TenantContext::clear();
        $this->assertNull($cache->get('greeting'));
    }

    // ─── Quota ───────────────────────────────────────────────────────────

    public function testQuotaGuardBlocksAndEmitsEvent(): void
    {
        $a = $this->createTenant('alpha', ['quota' => ['orders_per_day' => ['limit' => 2, 'period' => 'day']]]);

        $captured = [];
        $this->app->make('events')->listen(TenantQuotaExceeded::class, static function (TenantQuotaExceeded $e) use (&$captured): void {
            $captured[] = $e;
        });

        /** @var QuotaGuard $quota */
        $quota = $this->app->make('tenant.quota');

        TenantContext::run($a, static function () use ($quota, &$captured): void {
            $quota->consume('orders_per_day');
            $quota->assertAvailable('orders_per_day', 1);   // ۱ مصرف ≤ ۲ → ok
            $quota->consume('orders_per_day');

            try {
                $quota->assertAvailable('orders_per_day', 1);
                TestCase::fail('باید TenantQuotaExceededException پرتاب می‌شد');
            } catch (TenantQuotaExceededException $e) {
                TestCase::assertSame('tenancy.quota_exceeded', $e->messageId);
                TestCase::assertSame('orders_per_day', $e->context['resource']);
                TestCase::assertSame(2, $e->context['used']);
            }
        });

        $this->assertCount(1, $captured);
        $this->assertSame('orders_per_day', $captured[0]->resource);
    }

    public function testQuotaWindowResetsNextPeriod(): void
    {
        $a = $this->createTenant('alpha', ['quota' => ['orders_per_day' => ['limit' => 1, 'period' => 'day']]]);

        /** @var QuotaGuard $quota */
        $quota = $this->app->make('tenant.quota');

        Clock::setTestNow('2026-06-01 10:00:00');
        TenantContext::run($a, static function () use ($quota): void {
            $quota->consume('orders_per_day');
            try {
                $quota->assertAvailable('orders_per_day');
                TestCase::fail('quota باید پر می‌شد');
            } catch (TenantQuotaExceededException) {
                TestCase::assertTrue(true);
            }
        });

        // روز بعد → پنجرهٔ تازه
        Clock::setTestNow('2026-06-02 10:00:00');
        TenantContext::run($a, static function () use ($quota): void {
            $quota->assertAvailable('orders_per_day');
            TestCase::assertSame(0, $quota->usage('orders_per_day'));
        });
    }

    public function testQuotaUnlimitedWhenNotConfigured(): void
    {
        $a = $this->createTenant('alpha');
        /** @var QuotaGuard $quota */
        $quota = $this->app->make('tenant.quota');

        TenantContext::run($a, static function () use ($quota): void {
            for ($i = 0; $i < 50; $i++) {
                $quota->consume('anything');
                $quota->assertAvailable('anything');
            }
        });
        $this->assertTrue(true);
    }

    // ─── T-04: Provisioning (استراتژی B) ─────────────────────────────────

    public function testStrategyBProvisionsSqliteDatabasePerTenant(): void
    {
        $this->app->make('config')->set('tenancy.strategy', 'database');
        $this->tenants->flushMemo();

        /** @var TenantProvisioner $provisioner */
        $provisioner = $this->app->make('tenant.provisioner');
        $tenant = $provisioner->create(['identifier' => 'acme'], migrate: false);

        $dbFile = $this->tmp . '/acme.sqlite';
        $this->assertTrue(\is_file($dbFile), 'فایل DB tenant باید ساخته شود');

        // run() → اتصال tenant + بازگشت به مرکز
        $roundTrip = $this->tenants->run($tenant, function (): string {
            $connection = $this->app->make('db')->connection();
            $connection->statement('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');
            $connection->statement("INSERT INTO notes (body) VALUES ('tenant-data')");
            $rows = $connection->select('SELECT body FROM notes');

            return (string) ($rows[0]['body'] ?? '');
        });

        $this->assertSame('tenant-data', $roundTrip);

        // پس از run: اتصال پیش‌فرض دوباره مرکزی است (tvn_tenants قابل خواندن)
        $central = $this->app->make('db')->connection();
        $count = $central->select('SELECT COUNT(*) AS n FROM tvn_tenants');
        $this->assertSame(1, (int) $count[0]['n']);

        // دادهٔ tenant در DB مرکزی نیست
        $leaked = false;
        try {
            $central->select('SELECT * FROM notes');
            $leaked = true;
        } catch (\Throwable) {
            $leaked = false;
        }
        $this->assertFalse($leaked, 'جدول tenant نباید در DB مرکزی باشد');
    }

    public function testStrategyBDeprovisionRemovesDatabase(): void
    {
        $this->app->make('config')->set('tenancy.strategy', 'database');

        /** @var TenantProvisioner $provisioner */
        $provisioner = $this->app->make('tenant.provisioner');
        $tenant = $provisioner->create(['identifier' => 'gone'], migrate: false);
        $this->assertTrue(\is_file($this->tmp . '/gone.sqlite'));

        $provisioner->delete($tenant, force: true);
        $this->assertFalse(\is_file($this->tmp . '/gone.sqlite'));
        $this->assertNull($this->tenants->findByIdentifier('gone'));
    }

    public function testProvisionerRejectsUnsafeDatabaseName(): void
    {
        $this->app->make('config')->set('tenancy.strategy', 'database');

        /** @var DatabaseTenantManager $dbm */
        $dbm = $this->app->make(DatabaseTenantManager::class);
        $tenant = $this->createTenant('weird', ['settings' => ['db_database' => 'evil;DROP TABLE x--']]);

        $this->expectException(TenancyException::class);
        $this->expectExceptionMessageId('tenancy.unsafe_identifier');
        $dbm->provision($tenant);
    }

    // ─── TenantConfigRepository ──────────────────────────────────────────

    public function testTenantConfigOverlay(): void
    {
        $a = $this->createTenant('alpha', ['settings' => ['mail.from' => 'alpha@corp.com', 'theme' => 'dark']]);
        $this->app->make('config')->set('mail.from', 'app@default.com');

        $overlay = new TenantConfigRepository($this->app->make('config'));

        // بدون context → پایه
        TenantContext::clear();
        $this->assertSame('app@default.com', $overlay->get('mail.from'));

        // با context → setting tenant
        TenantContext::run($a, static function () use ($overlay): void {
            TestCase::assertSame('alpha@corp.com', $overlay->get('mail.from'));
            TestCase::assertSame('dark', $overlay->get('theme'));
            TestCase::assertTrue($overlay->has('theme'));
            // کلید بدون setting → پایه
            TestCase::assertSame('fa', $overlay->get('app.locale', 'fa'));
        });
    }

    // ─── Manager CRUD ────────────────────────────────────────────────────

    public function testCreateFindUpdateDeactivate(): void
    {
        $captured = [];
        $this->app->make('events')->listen(TenantCreated::class, static function (TenantCreated $e) use (&$captured): void {
            $captured[] = $e->identifier;
        });

        $tenant = $this->createTenant('acme', ['domain' => 'Acme.Example.com', 'plan' => 'pro']);
        $this->assertSame(['acme'], $captured);
        $this->assertTrue($tenant instanceof Tenant);
        $this->assertSame('pro', $tenant->plan());
        $this->assertTrue($tenant->isActive());

        // domain lowercase ذخیره/یافت می‌شود
        $byDomain = $this->tenants->findByDomain('acme.example.com');
        $this->assertNotNull($byDomain);
        $this->assertSame('acme', $byDomain->identifier());

        // duplicate
        try {
            $this->createTenant('acme');
            $this->fail('duplicate باید خطا می‌داد');
        } catch (TenancyException $e) {
            $this->assertSame('tenancy.duplicate_identifier', $e->messageId);
        }

        // identifier نامعتبر
        try {
            $this->createTenant('../evil');
            $this->fail('identifier نامعتبر باید خطا می‌داد');
        } catch (TenancyException $e) {
            $this->assertSame('tenancy.invalid_identifier', $e->messageId);
        }

        // deactivate
        $this->tenants->deactivate($tenant);
        $fresh = $this->tenants->findByIdentifier('acme');
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->isActive());
        $this->assertSame(0, $this->tenants->countActive());
    }

    public function testStoragePathIsolation(): void
    {
        $a = $this->createTenant('alpha');
        TenantContext::run($a, function (): void {
            $path = $this->tenants->storagePath();
            $this->assertStringContainsString($this->tmp, $path);
            $this->assertStringContainsString('alpha', $path);
        });

        // sanitize
        $this->assertSame($this->tmp . '/files/global', $this->tenants->storagePath('..//'));
    }

    // ─── T-06/T-07: leakage fuzzing + resident cleanup ───────────────────

    public function testCrossTenantLeakFuzzing(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');

        // ۲۰ سفارش برای هر tenant (interleaved — مجموعاً ۴۰)
        for ($i = 0; $i < 40; $i++) {
            TenantContext::run($i % 2 === 0 ? $a : $b, static function () use ($i): void {
                TOrder::create(['title' => 'order-' . $i, 'amount' => $i]);
            });
        }

        // ۱۰۰ خواندن تصادفی — هرگز دادهٔ tenant دیگر دیده نشود
        for ($round = 0; $round < 100; $round++) {
            $tenant = $round % 2 === 0 ? $a : $b;
            TenantContext::run($tenant, static function () use ($tenant, $round): void {
                $orders = TOrder::all();
                TestCase::assertCount(20, $orders);
                foreach ($orders as $order) {
                    TestCase::assertSame($tenant->id(), $order->getAttribute('tenant_id'));
                }
                // find با عنوان متعلق به tenant دیگر → null
                if ($round % 7 === 0) {
                    $probe = TenantContext::identifier() === 'alpha' ? 'order-1' : 'order-0';
                    TestCase::assertNull(TOrder::query()->where('title', $probe)->first());
                }
            });
        }
    }

    public function testContextCleanupAfterManyRuns(): void
    {
        $a = $this->createTenant('alpha');
        // AC-TEN-05 سبک: context پس از هر run نشت نمی‌کند
        for ($i = 0; $i < 1000; $i++) {
            TenantContext::run($a, static function (): void {
                // noop
            });
            if (TenantContext::id() !== null) {
                $this->fail('context در تکرار ' . $i . ' نشت کرد');
            }
        }
        $this->assertNull(TenantContext::id());
    }

    // ─── Queue integration (TEN-SEC-06) ─────────────────────────────────

    public function testJobRestoresTenantContext(): void
    {
        $a = $this->createTenant('alpha');

        $job = TenantContext::run($a, static fn (): TTenantJob => new TTenantJob());
        TenantContext::clear();

        // 🔒 job بدون اجرا با tenant قبلی — context را از id بازیابی می‌کند
        $this->assertSame('alpha', $job->handle());
        $this->assertNull(TenantContext::id());   // پس از اجرا پاک می‌شود
    }

    public function testJobWithoutTenantFails(): void
    {
        TenantContext::clear();
        $job = new TTenantJob();

        $this->expectException(TenantNotResolvedException::class);
        $job->handle();
    }

    // ─── TenantQueryGuard ────────────────────────────────────────────────

    public function testQueryGuardDetectsUnscopedQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM shop_orders WHERE id = ?', 'bindings' => [1]],
            ['sql' => 'SELECT * FROM shop_orders WHERE tenant_id = ? AND id = ?', 'bindings' => [1, 2]],
            ['sql' => 'UPDATE shop_orders SET status = ? WHERE id = ?', 'bindings' => ['paid', 5]],
            ['sql' => 'DELETE FROM shop_orders WHERE tenant_id = ?', 'bindings' => [1]],
            ['sql' => 'SELECT * FROM tvn_tenants WHERE identifier = ?', 'bindings' => ['x']],
            ['sql' => 'INSERT INTO shop_orders (tenant_id, title) VALUES (?, ?)', 'bindings' => [1, 'y']],
        ];

        $violations = TenantQueryGuard::scan($queries, ['tvn_tenants']);

        $this->assertCount(2, $violations);
        $this->assertSame('shop_orders', $violations[0]['table']);
        $this->assertStringContainsString('SELECT', $violations[0]['sql']);
        $this->assertStringContainsString('UPDATE', $violations[1]['sql']);
    }

    // ─── Facade/Bindings ─────────────────────────────────────────────────

    public function testBindingsAndFacade(): void
    {
        $this->assertTrue($this->app->bound('tenant'));
        $this->assertTrue($this->app->bound('tenant.quota'));
        $this->assertTrue($this->app->bound('tenant.provisioner'));
        $this->assertInstanceOf(TenantManager::class, $this->app->make('tenant'));
        $this->assertInstanceOf(QuotaGuard::class, $this->app->make('tenant.quota'));

        // middleware alias
        $aliases = (array) $this->app->make('config')->get('http.middleware.aliases', []);
        $this->assertArrayHasKey('tenant', $aliases);
    }

    public function testFacadeTenantWorks(): void
    {
        $a = $this->createTenant('alpha');
        \Tavaan\Core\Facade\Facades\Tenant::setCurrent($a);
        $this->assertSame('alpha', \Tavaan\Core\Facade\Facades\Tenant::identifier());
        $this->assertSame($a->id(), \Tavaan\Core\Facade\Facades\Tenant::id());
        TenantContext::clear();
    }
}
