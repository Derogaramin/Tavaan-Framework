<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Rbac\Gate;
use Tavaan\Engine\Rbac\PermissionSync;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Engine\Rbac\Scopes\OwnershipScope;
use Tavaan\Engine\Rbac\Testing\OrderFixture;
use Tavaan\Engine\Rbac\Testing\OrderPolicyFixture;
use Tavaan\Engine\Rbac\Testing\RbacTestUser;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور RBAC — DOC-057 §11 (T-01..T-11).
 */
final class RbacTest extends TestCase
{
    private Application $app;

    private RoleManager $roles;

    private Gate $gate;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();

        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');

        $schema = $this->app->make('schema');
        $this->createRbacTables($schema);
        $schema->create('orders', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('status')->default('paid');
            $t->integer('total')->default(0);
            $t->timestamps(3);
        });

        $this->roles = $this->app->make(RoleManager::class);
        $this->gate = $this->app->make('gate');
    }

    private function createRbacTables(object $schema): void
    {
        $schema->create('tvn_permissions', function (Blueprint $t): void {
            $t->id();
            $t->string('module', 64);
            $t->string('key', 190)->unique();
            $t->string('title_fa', 190)->nullable();
            $t->string('title_en', 190)->nullable();
            $t->string('group_id', 64)->nullable();
            $t->timestamp('created_at', 3)->nullable();
        });
        $schema->create('tvn_roles', function (Blueprint $t): void {
            $t->id();
            $t->string('key', 64)->unique();
            $t->string('name', 190);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->boolean('is_system')->default(false);
            $t->string('scope', 32)->default('global');
            $t->timestamp('created_at', 3)->nullable();
        });
        $schema->create('tvn_role_user', function (Blueprint $t): void {
            $t->unsignedBigInteger('role_id');
            $t->string('user_id', 64);
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->timestamp('expires_at', 3)->nullable();
            $t->unsignedBigInteger('granted_by')->nullable();
            $t->index(['user_id', 'role_id'], 'idx_role_user');
        });
        $schema->create('tvn_permission_role', function (Blueprint $t): void {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });
        $schema->create('tvn_delegations', function (Blueprint $t): void {
            $t->id();
            $t->string('from_user_id', 64);
            $t->string('to_user_id', 64)->index();
            $t->text('permissions');
            $t->timestamp('starts_at', 3);
            $t->timestamp('ends_at', 3);
            $t->string('reason')->nullable();
            $t->timestamp('created_at', 3)->nullable();
        });
    }

    private function user(int $id = 1): RbacTestUser
    {
        return new RbacTestUser(['id' => $id, 'email' => 'u' . $id . '@test.io']);
    }

    private function seedBasicRbac(): void
    {
        $this->roles->create('user', 'کاربر');
        $this->roles->create('editor', 'ویرایشگر', parentKey: 'user');
        $this->roles->create('shop-manager', 'مدیر فروشگاه', parentKey: 'editor');
        $this->roles->create('super-admin', 'ابریدمین', isSystem: true);

        $this->roles->createPermission('content.edit', 'content');
        $this->roles->createPermission('shop.order.view', 'shop');
        $this->roles->createPermission('shop.order.refund', 'shop');
        $this->roles->createPermission('shop.product.create', 'shop');

        $this->roles->grantPermissionToRole('user', 'content.edit');
        $this->roles->grantPermissionToRole('editor', 'shop.order.view');
        $this->roles->grantPermissionToRole('shop-manager', 'shop.order.refund');
    }

    // ─── T-01/T-02: assign + hasRole + hasPermission ──────────────────────

    public function testAssignRoleAndHasPermission(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(1);

        $this->assertFalse($user->hasRole('editor'));

        $user->assignRole('editor');
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasAnyRole(['admin', 'editor']));

        // T-02: مجوز از نقش
        $this->assertTrue($user->hasPermission('shop.order.view'));
        $this->assertFalse($user->hasPermission('shop.order.refund'));
    }

    // ─── T-03: سلسله‌مراتب ────────────────────────────────────────────────

    public function testHierarchyInheritsParentPermissions(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(2);
        $user->assignRole('shop-manager');

        // shop-manager ← editor ← user
        $this->assertTrue($user->hasPermission('shop.order.refund'));   // مستقیم
        $this->assertTrue($user->hasPermission('shop.order.view'));      // از editor
        $this->assertTrue($user->hasPermission('content.edit'));         // از user (جد)
        $this->assertFalse($user->hasPermission('shop.product.create')); // داده نشده
    }

    // ─── T-04: Policy ─────────────────────────────────────────────────────

    public function testPolicyForModel(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(3);
        $user->assignRole('editor');

        $this->gate->policy(OrderFixture::class, OrderPolicyFixture::class);

        // order متعلق به user 3 → view مجاز (مالکیت) حتی بدون permission
        $ownOrder = new OrderFixture();
        $ownOrder->setRawAttributes(['id' => 1, 'user_id' => 3, 'status' => 'paid', 'total' => 100]);
        $this->assertTrue($this->gate->forUser($user)->allows('view', $ownOrder));

        // order دیگران → نیازمند shop.order.view (که editor دارد)
        $otherOrder = new OrderFixture();
        $otherOrder->setRawAttributes(['id' => 2, 'user_id' => 99, 'status' => 'paid', 'total' => 100]);
        $this->assertTrue($this->gate->forUser($user)->allows('view', $otherOrder));

        // refund → policy: فقط shop-manager + status=paid
        $this->assertFalse($this->gate->forUser($user)->allows('refund', $otherOrder));

        $manager = $this->user(4);
        $manager->assignRole('shop-manager');
        $this->assertTrue($this->gate->forUser($manager)->allows('refund', $otherOrder));

        // status غیر paid → refund رد
        $draftOrder = new OrderFixture();
        $draftOrder->setRawAttributes(['id' => 3, 'user_id' => 99, 'status' => 'draft', 'total' => 10]);
        $this->assertFalse($this->gate->forUser($manager)->allows('refund', $draftOrder));
    }

    // ─── T-05: before برای super-admin ────────────────────────────────────

    public function testSuperAdminBypassesGate(): void
    {
        $this->seedBasicRbac();
        $admin = $this->user(5);
        $admin->assignRole('super-admin');

        // بدون هیچ define/policy/permission → مجاز (bypasses_gate)
        $this->assertTrue($this->gate->forUser($admin)->allows('anything.at.all'));
        $this->assertTrue($admin->isSuperAdmin());

        // before callback سفارشی
        $this->gate->before(fn ($user, string $ability): ?bool => $ability === 'explicit-deny' ? false : null);
        $this->assertFalse($this->gate->forUser($admin)->allows('explicit-deny'));
    }

    // ─── T-06: Ownership Scope ────────────────────────────────────────────

    public function testOwnershipScopeNoLeak(): void
    {
        $db = $this->app->make('db')->connection();
        $db->table('orders')->insert([
            ['user_id' => 1, 'status' => 'paid', 'total' => 100],
            ['user_id' => 1, 'status' => 'paid', 'total' => 200],
            ['user_id' => 2, 'status' => 'paid', 'total' => 300],
        ]);

        $scope = new OwnershipScope('user_id', '*.view-all', fn () => $this->user(1));

        $query = $db->table('orders');
        $model = new OrderFixture();
        $scope->apply($query, $model);

        $rows = $query->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(1, (int) $row['user_id']);   // نشت = ۰
        }

        // کاربر ناشناس → هیچ رکوردی
        $anonScope = new OwnershipScope('user_id', '*.view-all', fn () => null);
        $q2 = $db->table('orders');
        $anonScope->apply($q2, $model);
        $this->assertCount(0, $q2->get());

        // ادمین با view-all → همه
        $this->seedBasicRbac();
        $this->roles->createPermission('*.view-all', 'system');
        $this->roles->grantPermissionToRole('super-admin', '*.view-all');
        $adminUser = $this->user(9);
        $adminUser->assignRole('super-admin');
        $adminScope = new OwnershipScope('user_id', '*.view-all', fn () => $adminUser);
        $q3 = $db->table('orders');
        $adminScope->apply($q3, $model);
        $this->assertCount(3, $q3->get());
    }

    // ─── T-07: Wildcard ───────────────────────────────────────────────────

    public function testWildcardPermission(): void
    {
        $this->seedBasicRbac();
        $this->roles->create('shop-staff', 'کارمند فروشگاه');
        $this->roles->createPermission('shop.*', 'shop');
        $this->roles->grantPermissionToRole('shop-staff', 'shop.*');

        $staff = $this->user(6);
        $staff->assignRole('shop-staff');

        $this->assertTrue($staff->hasPermission('shop.order.view'));
        $this->assertTrue($staff->hasPermission('shop.order.refund'));
        $this->assertTrue($staff->hasPermission('shop.product.create'));
        $this->assertFalse($staff->hasPermission('content.edit'));
        $this->assertFalse($staff->hasPermission('finance.pay'));
    }

    // ─── T-08: Delegation ─────────────────────────────────────────────────

    public function testTemporaryDelegation(): void
    {
        $this->seedBasicRbac();
        $delegate = $this->user(7);

        // فعال
        $this->roles->delegate(1, 7, ['shop.order.refund'], new \DateTimeImmutable('-1 hour'), new \DateTimeImmutable('+2 days'));
        $this->assertTrue($delegate->hasPermission('shop.order.refund'));

        // منقضی
        Clock::setTestNow(new \DateTimeImmutable('+3 days'));
        $this->app->make(\Tavaan\Engine\Rbac\PermissionResolver::class)->invalidate(7);
        $this->assertFalse($delegate->hasPermission('shop.order.refund'));
        Clock::setTestNow(null);

        // سقف max_duration
        $this->expectException(\InvalidArgumentException::class);
        $this->roles->delegate(1, 7, ['x'], new \DateTimeImmutable(), new \DateTimeImmutable('+91 days'));
    }

    // ─── T-09: Sync از manifest ───────────────────────────────────────────

    public function testPermissionSync(): void
    {
        /** @var PermissionSync $sync */
        $sync = $this->app->make(PermissionSync::class);

        $result = $sync->sync('shop', ['shop.order.view', 'shop.order.create']);
        $this->assertSame(['shop.order.view', 'shop.order.create'], $result['added']);
        $this->assertSame([], $result['removed']);

        // sync دوم با حذف یکی
        $result2 = $sync->sync('shop', ['shop.order.view', 'shop.order.refund']);
        $this->assertSame(['shop.order.refund'], $result2['added']);
        $this->assertSame(['shop.order.create'], $result2['removed']);

        // idempotent
        $result3 = $sync->sync('shop', ['shop.order.view', 'shop.order.refund']);
        $this->assertSame([], $result3['added']);
        $this->assertSame([], $result3['removed']);
    }

    // ─── T-10: Cache invalidation ─────────────────────────────────────────

    public function testCacheInvalidatedOnRoleChange(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(8);

        $this->assertFalse($user->hasPermission('shop.order.refund'));   // resolve + cache

        $user->assignRole('shop-manager');   // invalidate
        $this->assertTrue($user->hasPermission('shop.order.refund'));

        $user->removeRole('shop-manager');
        $this->assertFalse($user->hasPermission('shop.order.refund'));
    }

    public function testTemporaryRoleExpiration(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(10);

        $this->roles->assign($user, 'shop-manager', expiresAt: new \DateTimeImmutable('+1 hour'));
        $this->assertTrue($user->hasRole('shop-manager'));

        Clock::setTestNow(new \DateTimeImmutable('+2 hours'));
        $this->app->make(\Tavaan\Engine\Rbac\PermissionResolver::class)->flushMemo();
        $this->assertFalse($user->hasRole('shop-manager'));
        Clock::setTestNow(null);
    }

    // ─── T-11: authorize → 403 ────────────────────────────────────────────

    public function testAuthorizeThrows403(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(11);

        try {
            $this->gate->forUser($user)->authorize('shop.order.refund');
            self::fail('expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame('authorization.denied', $e->messageId);
        }

        $user->assignRole('shop-manager');
        $response = $this->gate->forUser($user)->authorize('shop.order.refund');
        $this->assertTrue($response->allowed());
    }

    // ─── define / check / any ─────────────────────────────────────────────

    public function testDefineCheckAny(): void
    {
        $this->seedBasicRbac();
        $user = $this->user(12);
        $user->assignRole('editor');

        $this->gate->define('custom.ability', fn ($u): bool => (int) $u->getAuthIdentifier() === 12);

        $g = $this->gate->forUser($user);
        $this->assertTrue($g->allows('custom.ability'));
        $this->assertTrue($g->check(['content.edit', 'custom.ability']));        // همه
        $this->assertFalse($g->check(['content.edit', 'shop.order.refund']));
        $this->assertTrue($g->any(['shop.order.refund', 'custom.ability']));     // حداقل یکی
        $this->assertTrue($g->denies('shop.order.refund'));
    }
}
