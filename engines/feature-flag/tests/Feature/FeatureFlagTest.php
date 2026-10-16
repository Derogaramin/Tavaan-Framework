<?php

declare(strict_types=1);

namespace Tavaan\Engine\FeatureFlag\Tests\Feature;

require_once __DIR__ . '/../Fixtures/FeatureFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\FeatureFlag\FeatureManager;
use Tavaan\Engine\FeatureFlag\ScopedFeature;
use Tavaan\Engine\FeatureFlag\Tests\Fixtures\FTenant;
use Tavaan\Engine\FeatureFlag\Tests\Fixtures\FUser;
use Tavaan\Engine\Tenancy\TenantContext;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور FeatureFlag — DOC-075 §8 (T-01..T-06).
 */
final class FeatureFlagTest extends TestCase
{
    private Application $app;

    /** @var \Tavaan\Core\Contracts\Cache\RepositoryInterface */
    private mixed $cache;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        TenantContext::clear();

        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('cache.default', 'array');
        $this->cache = $this->app->make('cache')->store('array');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Clock::setTestNow(null);
    }

    /** @param array<string, mixed> $config */
    private function manager(array $config = [], string $env = 'production', ?string $ip = null): FeatureManager
    {
        return new FeatureManager($this->cache, [
            'driver' => 'config',
            'cache_ttl' => 60,
            'version' => 'v1',
            'features' => [],
            ...$config,
        ], $env, $ip);
    }

    // ─── T-01: enabled/disabled ──────────────────────────────────────────

    public function testEnabledFromConfig(): void
    {
        $m = $this->manager(['features' => [
            'shop.gift-card' => ['enabled' => true, 'default' => true],
            'shop.beta' => ['enabled' => false],
            'shop.rules-only' => ['enabled' => true, 'default' => false, 'rules' => []],
        ]]);

        $this->assertTrue($m->enabled('shop.gift-card'));
        $this->assertFalse($m->enabled('shop.beta'));          // کلید خاموش → سخت‌خاموش
        $this->assertFalse($m->enabled('shop.rules-only'));   // بدون rule منطبق → default
        $this->assertFalse($m->enabled('never-defined'));     // تعریف‌نشده → false
    }

    public function testRuntimeDefineOverridesConfig(): void
    {
        $m = $this->manager(['features' => ['f' => ['enabled' => true, 'default' => false]]]);
        $this->assertFalse($m->enabled('f'));

        $m->define('f', ['enabled' => true, 'default' => true]);
        $this->assertTrue($m->enabled('f'));
    }

    // ─── T-02: role/user/tenant targeting ────────────────────────────────

    public function testRoleTargeting(): void
    {
        $m = $this->manager(['features' => [
            'beta' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'role', 'value' => ['beta-tester']],
            ]],
        ]]);

        $beta = new FUser(1, ['beta-tester']);
        $normal = new FUser(2, ['customer']);

        $this->assertTrue($m->enabled('beta', $beta));
        $this->assertFalse($m->enabled('beta', $normal));
        $this->assertFalse($m->enabled('beta'));          // guest
    }

    public function testPermissionTargeting(): void
    {
        $m = $this->manager(['features' => [
            'admin-panel' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'permission', 'value' => ['shop.stock.view']],
            ]],
        ]]);

        $this->assertTrue($m->enabled('admin-panel', new FUser(1, [], ['shop.stock.view'])));
        $this->assertFalse($m->enabled('admin-panel', new FUser(2)));
    }

    public function testUserIdTargeting(): void
    {
        $m = $this->manager(['features' => [
            'vip' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'user_id', 'value' => [42, 43]],
            ]],
        ]]);

        $this->assertTrue($m->enabled('vip', new FUser(42)));
        $this->assertTrue($m->enabled('vip', new FUser(43)));
        $this->assertFalse($m->enabled('vip', new FUser(44)));
    }

    public function testTenantTargeting(): void
    {
        $m = $this->manager(['features' => [
            'multi-currency' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'tenant', 'value' => ['acme', 'globex']],
            ]],
        ]]);

        TenantContext::set(new FTenant(1, 'acme'));
        $this->assertTrue($m->enabled('multi-currency'));

        TenantContext::set(new FTenant(2, 'other'));
        $this->assertFalse($m->enabled('multi-currency'));

        TenantContext::clear();
        $this->assertFalse($m->enabled('multi-currency'));
    }

    public function testEnvAndIpTargeting(): void
    {
        $m = $this->manager([
            'features' => [
                'debug-tools' => ['enabled' => true, 'default' => false, 'rules' => [
                    ['type' => 'env', 'value' => ['local', 'testing']],
                ]],
                'office-only' => ['enabled' => true, 'default' => false, 'rules' => [
                    ['type' => 'ip', 'value' => ['10.0.0.5']],
                ]],
            ],
        ], env: 'local', ip: '10.0.0.5');

        $this->assertTrue($m->enabled('debug-tools'));
        $this->assertTrue($m->enabled('office-only'));

        $prod = $this->manager(['features' => [
            'debug-tools' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'env', 'value' => ['local']],
            ]],
        ]], env: 'production');
        $this->assertFalse($prod->enabled('debug-tools'));
    }

    // ─── T-03: percentage پایدار ─────────────────────────────────────────

    public function testPercentageIsStablePerUser(): void
    {
        $m = $this->manager(['features' => [
            'rollout' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'percentage', 'value' => 25],
            ]],
        ]]);

        $user = new FUser(1234);
        $first = $m->enabled('rollout', $user);
        for ($i = 0; $i < 50; $i++) {
            $this->assertSame($first, $m->enabled('rollout', $user), 'نتیجه باید در تکرارها پایدار بماند');
        }

        // کاربر دیگر ممکن است نتیجهٔ متفاوت داشته باشد اما خودش پایدار است
        $user2 = new FUser(9999);
        $second = $m->enabled('rollout', $user2);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($second, $m->enabled('rollout', $user2));
        }
    }

    public function testPercentageDistributionIsRoughlyCorrect(): void
    {
        $m = $this->manager(['features' => [
            'rollout' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'percentage', 'value' => 25],
            ]],
        ]]);

        $in = 0;
        $total = 500;
        for ($i = 1; $i <= $total; $i++) {
            if ($m->enabled('rollout', new FUser($i))) {
                $in++;
            }
        }

        // ۲۵٪ ± تلورانس
        $this->assertTrue($in > $total * 0.15, "in=$in");
        $this->assertTrue($in < $total * 0.35, "in=$in");
    }

    public function testPercentageBoundaries(): void
    {
        $m = $this->manager(['features' => [
            'zero' => ['enabled' => true, 'default' => false, 'rules' => [['type' => 'percentage', 'value' => 0]]],
            'hundred' => ['enabled' => true, 'default' => false, 'rules' => [['type' => 'percentage', 'value' => 100]]],
        ]]);

        $this->assertFalse($m->enabled('zero', new FUser(1)));
        $this->assertTrue($m->enabled('hundred', new FUser(1)));
        // guest با percentage → seed=anon پایدار
        $this->assertTrue($m->enabled('hundred'));
    }

    public function testPercentageHelperDefinesRule(): void
    {
        $m = $this->manager();
        $m->percentage('new-checkout', 25);

        $user = new FUser(77);
        $first = $m->enabled('new-checkout', $user);
        $this->assertSame($first, $m->enabled('new-checkout', $user));
    }

    public function testIndependentFlagsHaveIndependentBuckets(): void
    {
        // 🔒 همان کاربر در دو flag ۵۰٪ نباید لزوماً هم‌عضو باشد
        $m = $this->manager(['features' => [
            'flag-a' => ['enabled' => true, 'default' => false, 'rules' => [['type' => 'percentage', 'value' => 50]]],
            'flag-b' => ['enabled' => true, 'default' => false, 'rules' => [['type' => 'percentage', 'value' => 50]]],
        ]]);

        $both = 0;
        $total = 400;
        for ($i = 1; $i <= $total; $i++) {
            $user = new FUser($i);
            if ($m->enabled('flag-a', $user) && $m->enabled('flag-b', $user)) {
                $both++;
            }
        }
        // اگر bucketها correlated بودند ≈۲۰۰ می‌شد؛ مستقل ≈۱۰۰
        $this->assertTrue($both < $total * 0.38, "both=$both — bucketها باید مستقل باشند");
    }

    // ─── T-04: Kill Switch ───────────────────────────────────────────────

    public function testKillSwitchOverridesEverything(): void
    {
        $m = $this->manager(['features' => [
            'shop.gift-card' => ['enabled' => true, 'default' => true],
        ]]);

        $this->assertTrue($m->enabled('shop.gift-card'));

        $m->kill('shop.gift-card');
        $this->assertTrue($m->isKilled('shop.gift-card'));
        $this->assertFalse($m->enabled('shop.gift-card'));
        $this->assertFalse($m->enabled('shop.gift-card', new FUser(1)));

        $m->revive('shop.gift-card');
        $this->assertFalse($m->isKilled('shop.gift-card'));
        $this->assertTrue($m->enabled('shop.gift-card'));
    }

    public function testKillAffectsValueToo(): void
    {
        $m = $this->manager(['features' => [
            'theme.color' => ['enabled' => true, 'default' => true, 'value' => 'red'],
        ]]);

        $this->assertSame('red', $m->value('theme.color', 'blue'));
        $m->kill('theme.color');
        $this->assertSame('blue', $m->value('theme.color', 'blue'));   // کشته → default
    }

    // ─── value ───────────────────────────────────────────────────────────

    public function testValueResolution(): void
    {
        $m = $this->manager(['features' => [
            'theme.color' => ['enabled' => true, 'default' => true, 'value' => 'red'],
            'no-value' => ['enabled' => true, 'default' => true],
            'disabled' => ['enabled' => false, 'value' => 'x'],
        ]]);

        $this->assertSame('red', $m->value('theme.color'));
        $this->assertSame('fallback', $m->value('no-value', 'fallback'));
        $this->assertSame('fallback', $m->value('disabled', 'fallback'));
        $this->assertNull($m->value('undefined'));
        $this->assertSame(['a' => 1], $m->value('undefined', ['a' => 1]));
    }

    // ─── T-05: A/B variant پایدار ────────────────────────────────────────

    public function testExperimentIsStableAndSpreads(): void
    {
        $m = $this->manager();

        $user = new FUser(42);
        $variant = $m->experiment('checkout-redesign', ['A', 'B'], $user);
        $this->assertNotNull($variant);
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame($variant, $m->experiment('checkout-redesign', ['A', 'B'], $user));
        }

        // پخش بین variantها
        $counts = ['A' => 0, 'B' => 0];
        for ($i = 1; $i <= 400; $i++) {
            $v = $m->experiment('checkout-redesign', ['A', 'B'], new FUser($i));
            $counts[(string) $v]++;
        }
        $this->assertTrue($counts['A'] > 100, 'A=' . $counts['A']);
        $this->assertTrue($counts['B'] > 100, 'B=' . $counts['B']);
    }

    public function testExperimentEdgeCases(): void
    {
        $m = $this->manager();
        $this->assertNull($m->experiment('x', [], new FUser(1)));
        $this->assertSame('only', $m->experiment('x', ['only'], new FUser(1)));

        // guest → seed=anon اما پایدار
        $v1 = $m->experiment('e', ['A', 'B', 'C']);
        $this->assertNotNull($v1);
        $this->assertSame($v1, $m->experiment('e', ['A', 'B', 'C']));
    }

    // ─── T-06: cache ─────────────────────────────────────────────────────

    public function testScopedForUser(): void
    {
        $m = $this->manager(['features' => [
            'vip' => ['enabled' => true, 'default' => false, 'rules' => [['type' => 'user_id', 'value' => [7]]]],
            'theme.color' => ['enabled' => true, 'default' => true, 'value' => 'gold'],
        ]]);

        $scoped = $m->for(new FUser(7));
        $this->assertInstanceOf(ScopedFeature::class, $scoped);
        $this->assertTrue($scoped->enabled('vip'));
        $this->assertSame('gold', $scoped->value('theme.color'));
        $this->assertNotNull($scoped->experiment('e', ['A', 'B']));

        $this->assertFalse($m->for(new FUser(8))->enabled('vip'));
    }

    public function testDatabaseDriverPersistsAndReads(): void
    {
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->app->make('schema')->create('tvn_features', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 190)->unique();
            $t->boolean('enabled')->default(true);
            $t->boolean('default_value')->default(false);
            $t->text('rules')->nullable();
            $t->text('value')->nullable();
            $t->timestamps(3);
        });

        // manager اول با driver=database تعریف را persist می‌کند
        $m1 = new FeatureManager($this->cache, [
            'driver' => 'database', 'cache_ttl' => 60, 'version' => 'v1', 'table' => 'tvn_features', 'features' => [],
        ], 'production', null);
        $m1->define('shop.gift-card', [
            'enabled' => true,
            'default' => false,
            'rules' => [['type' => 'user_id', 'value' => [42]]],
            'value' => 'silver',
        ]);

        // رکورد در DB است
        $row = $this->app->make('db')->table('tvn_features')->where('name', 'shop.gift-card')->first();
        $this->assertNotNull($row);

        // manager دوم (بدون runtime) از DB+cache می‌خواند
        $m2 = new FeatureManager($this->cache, [
            'driver' => 'database', 'cache_ttl' => 60, 'version' => 'v1', 'table' => 'tvn_features', 'features' => [],
        ], 'production', null);
        $this->assertTrue($m2->enabled('shop.gift-card', new FUser(42)));
        $this->assertFalse($m2->enabled('shop.gift-card', new FUser(43)));
        $this->assertSame('silver', $m2->value('shop.gift-card'));
    }

    public function testCacheVersionInvalidatesDefinitions(): void
    {
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->app->make('schema')->create('tvn_features', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 190)->unique();
            $t->boolean('enabled')->default(true);
            $t->boolean('default_value')->default(false);
            $t->text('rules')->nullable();
            $t->text('value')->nullable();
            $t->timestamps(3);
        });
        $db = $this->app->make('db');
        $db->table('tvn_features')->insert([
            'name' => 'f1', 'enabled' => 1, 'default_value' => 1, 'rules' => null, 'value' => null,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => null,
        ]);

        $config = ['driver' => 'database', 'cache_ttl' => 60, 'version' => 'v1', 'table' => 'tvn_features', 'features' => []];
        $m1 = new FeatureManager($this->cache, $config, 'production', null);
        $this->assertTrue($m1->enabled('f1'));   // → cache می‌شود

        // DB مستقیم تغییر می‌کند اما cache کهنه است
        $db->table('tvn_features')->where('name', 'f1')->update(['default_value' => 0]);
        $m1b = new FeatureManager($this->cache, $config, 'production', null);
        $this->assertTrue($m1b->enabled('f1'));   // هنوز مقدار کش‌شده

        // 🔒 تغییر version → کلید cache عوض می‌شود → مقدار تازه
        $config['version'] = 'v2';
        $m2 = new FeatureManager($this->cache, $config, 'production', null);
        $this->assertFalse($m2->enabled('f1'));

        // flushCache هم راه دیگر
        $config['version'] = 'v1';
        $m1c = new FeatureManager($this->cache, $config, 'production', null);
        $m1c->flushCache('f1');
        $this->assertFalse($m1c->enabled('f1'));
    }

    // ─── Facade/bindings ─────────────────────────────────────────────────

    public function testFacadeAndBindings(): void
    {
        $this->assertTrue($this->app->bound('feature'));
        $this->assertInstanceOf(FeatureManager::class, $this->app->make('feature'));

        \Tavaan\Core\Facade\Facades\Feature::define('facade.flag', ['enabled' => true, 'default' => true]);
        $this->assertTrue(\Tavaan\Core\Facade\Facades\Feature::enabled('facade.flag'));
        $this->assertContains('facade.flag', \Tavaan\Core\Facade\Facades\Feature::known());
    }

    public function testUnknownRuleTypeIsIgnored(): void
    {
        $m = $this->manager(['features' => [
            'f' => ['enabled' => true, 'default' => false, 'rules' => [
                ['type' => 'moon-phase', 'value' => 'full'],
            ]],
        ]]);
        $this->assertFalse($m->enabled('f', new FUser(1)));
    }
}
