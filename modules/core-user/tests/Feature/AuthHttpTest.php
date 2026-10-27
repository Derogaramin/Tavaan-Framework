<?php

declare(strict_types=1);

namespace Modules\CoreUser\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول core-user — احراز هویت وب + مدیریت کاربران در پنل.
 *
 * مسیر واقعی HTTP: MakesHttpRequests درون‌فرایندی + SQLite in-memory +
 * RBAC واقعی (نقش/مجوز DB) + actingAs.
 */
final class AuthHttpTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private UserRepositoryInterface $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*', 'login', 'register', 'profile', 'profile/*']);
        // ثبت‌نام عمومی برای تست فعال شود
        $config->set('core-user.registration.enabled', true);
        $config->set('core-user.registration.default_role', 'user');

        $this->users = $app->make(UserRepositoryInterface::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── ورود ──────────────────────────────────────────────────────────────

    public function testLoginPageRendersForGuest(): void
    {
        $page = $this->get('/login');
        $page->assertOk();
        $page->assertSee('ورود به حساب کاربری');
    }

    public function testLoginWithValidCredentialsRedirects(): void
    {
        $this->users->create(name: 'کاربر آزمایشی', email: 'user1@test.dev', plainPassword: 'secret-1234');

        $response = $this->post('/login', ['email' => 'user1@test.dev', 'password' => 'secret-1234']);
        $response->assertRedirect();
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $this->users->create(name: 'کاربر', email: 'user2@test.dev', plainPassword: 'secret-1234');

        $response = $this->post('/login', ['email' => 'user2@test.dev', 'password' => 'wrong-pass-99']);
        $response->assertRedirect('/login');

        // پیام خطای یکسان (ضد user enumeration) — بدون افشای وجود کاربر
        $page = $this->get('/login');
        $page->assertSee('ایمیل یا رمز عبور نادرست است');
    }

    public function testBannedUserCannotLogin(): void
    {
        $user = $this->users->create(name: 'مسدود', email: 'banned@test.dev', plainPassword: 'secret-1234');
        $this->users->setStatus($user->id, UserStatus::Banned);

        $this->post('/login', ['email' => 'banned@test.dev', 'password' => 'secret-1234'])->assertRedirect('/login');

        $page = $this->get('/login');
        $page->assertSee('این حساب کاربری مسدود شده است');
    }

    public function testGuestCannotAccessProfile(): void
    {
        $this->get('/profile')->assertRedirect();
    }

    // ─── ثبت‌نام ───────────────────────────────────────────────────────────

    public function testRegistrationCreatesUserWithDefaultRole(): void
    {
        $response = $this->post('/register', [
            'name' => 'کاربر جدید',
            'email' => 'new@test.dev',
            'password' => 'secret-1234',
            'password_confirmation' => 'secret-1234',
        ]);
        $response->assertRedirect();

        $snapshot = $this->users->findByEmail('new@test.dev');
        self::assertNotNull($snapshot, 'کاربر ثبت‌نام‌شده در DB نیست');
        self::assertTrue($this->roles->hasRole($snapshot->id, 'user'));
    }

    public function testRegistrationRejectsShortPassword(): void
    {
        $this->post('/register', [
            'name' => 'کاربر',
            'email' => 'short@test.dev',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertRedirect('/register');

        self::assertNull($this->users->findByEmail('short@test.dev'));
    }

    public function testRegistrationRejectsMismatchedConfirmation(): void
    {
        $this->post('/register', [
            'name' => 'کاربر',
            'email' => 'mismatch@test.dev',
            'password' => 'secret-1234',
            'password_confirmation' => 'other-5678',
        ])->assertRedirect('/register');

        self::assertNull($this->users->findByEmail('mismatch@test.dev'));
    }

    // ─── پروفایل ───────────────────────────────────────────────────────────

    public function testProfileUpdateChangesName(): void
    {
        $user = $this->users->create(name: 'نام قبلی', email: 'profile@test.dev', plainPassword: 'secret-1234');

        $this->actingAs($user)->post('/profile', [
            'name' => 'نام جدید',
            'mobile' => '09120000000',
            'bio' => 'برنامه‌نویس',
        ])->assertRedirect('/profile');

        $fresh = $this->users->findUserById($user->id);
        self::assertNotNull($fresh);
        self::assertSame('نام جدید', $fresh->name);
        self::assertSame('09120000000', $fresh->mobile);
    }

    public function testPasswordChangeRequiresCurrentPassword(): void
    {
        $user = $this->users->create(name: 'کاربر', email: 'pwd@test.dev', plainPassword: 'secret-1234');

        // رمز جاری اشتباه → تغییر اعمال نمی‌شود
        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'wrong-one',
            'password' => 'brand-new-99',
            'password_confirmation' => 'brand-new-99',
        ])->assertRedirect('/profile');

        $fresh = $this->users->findUserById($user->id);
        self::assertNotNull($fresh);
        self::assertTrue(\Tavaan\Core\Facade\Facades\Hash::check('secret-1234', $fresh->getAuthPassword()));

        // رمز جاری درست → تغییر اعمال می‌شود
        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'secret-1234',
            'password' => 'brand-new-99',
            'password_confirmation' => 'brand-new-99',
        ])->assertRedirect('/profile');

        $fresh2 = $this->users->findUserById($user->id);
        self::assertNotNull($fresh2);
        self::assertTrue(\Tavaan\Core\Facade\Facades\Hash::check('brand-new-99', $fresh2->getAuthPassword()));
    }

    // ─── پنل مدیریت ────────────────────────────────────────────────────────

    public function testAdminUserIndexRequiresPermission(): void
    {
        // بدون ورود → redirect به login
        $this->get('/admin/users')->assertRedirect();

        // کاربر بدون مجوز → 403
        $plain = $this->makeUser('plain@test.dev', 'کاربر ساده');
        $this->actingAs($plain)->get('/admin/users')->assertForbidden();
    }

    public function testAdminCanListAndCreateUsers(): void
    {
        $admin = $this->makeAdmin('admin1@test.dev');

        $page = $this->actingAs($admin)->get('/admin/users');
        $page->assertOk();
        $page->assertSee('admin1@test.dev');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'کاربر ساخت مدیر',
            'email' => 'created@test.dev',
            'password' => 'secret-1234',
            'status' => 'active',
            'roles' => ['user'],
        ])->assertRedirect();

        $created = $this->users->findByEmail('created@test.dev');
        self::assertNotNull($created);
        self::assertTrue($this->roles->hasRole($created->id, 'user'));
    }

    public function testAdminCannotDeleteSelf(): void
    {
        $admin = $this->makeAdmin('admin2@test.dev');

        $this->actingAs($admin)->delete('/admin/users/' . $admin->id)->assertRedirect('/admin/users');

        self::assertNotNull($this->users->findUserById($admin->id));
    }

    public function testLastActiveAdminProtected(): void
    {
        $admin = $this->makeAdmin('admin3@test.dev');
        $other = $this->makeAdmin('admin4@test.dev');

        // دو مدیر فعال → حذف یکی مجاز است
        $this->actingAs($admin)->delete('/admin/users/' . $other->id)->assertRedirect('/admin/users');
        self::assertNull($this->users->findUserById($other->id));

        // حالا admin آخرین مدیر فعال است → حذف خود (حتی با مجوز) ممنوع
        $this->actingAs($admin)->delete('/admin/users/' . $admin->id)->assertRedirect('/admin/users');
        self::assertNotNull($this->users->findUserById($admin->id));
    }

    public function testAdminChangesUserStatus(): void
    {
        $admin = $this->makeAdmin('admin5@test.dev');
        $target = $this->users->create(name: 'هدف', email: 'target@test.dev', plainPassword: 'secret-1234');

        $this->actingAs($admin)->post('/admin/users/' . $target->id . '/status', ['status' => 'banned'])
            ->assertRedirect('/admin/users');

        $fresh = $this->users->findUserById($target->id);
        self::assertNotNull($fresh);
        self::assertSame(UserStatus::Banned, $fresh->status);
    }

    public function testAdminResetsUserPassword(): void
    {
        $admin = $this->makeAdmin('admin6@test.dev');
        $target = $this->users->create(name: 'هدف', email: 'reset@test.dev', plainPassword: 'secret-1234');

        $this->actingAs($admin)->post('/admin/users/' . $target->id . '/password', ['password' => 'admin-set-99'])
            ->assertRedirect();

        $fresh = $this->users->findUserById($target->id);
        self::assertNotNull($fresh);
        self::assertTrue(\Tavaan\Core\Facade\Facades\Hash::check('admin-set-99', $fresh->getAuthPassword()));
    }

    // ─── کمکی ──────────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        foreach (['user.view', 'user.create', 'user.update', 'user.delete'] as $key) {
            $exists = DB::table('tvn_permissions')->where('key', $key)->exists();
            if (!$exists) {
                $this->roles->createPermission($key, 'tavaan.core-user');
            }
        }

        foreach (['admin', 'user'] as $roleKey) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
        }

        foreach (['user.view', 'user.create', 'user.update', 'user.delete'] as $key) {
            $this->roles->grantPermissionToRole('admin', $key);
        }
    }

    private function makeUser(string $email, string $name): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, 'user');

        return $user;
    }

    private function makeAdmin(string $email): User
    {
        $user = $this->users->create(name: 'مدیر', email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, 'admin');

        return $user;
    }
}
