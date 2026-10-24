<?php

declare(strict_types=1);

namespace Modules\CoreUser\Tests\Feature;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Hash;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست ماژول core-user — repository روی SQLite in-memory + قراردادها.
 *
 * مسیر: setUpDatabase → migration ماژول (users) → CRUD + mapper + Notifiable.
 */
final class UserRepositoryTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->users = $this->app()->make(DatabaseUserRepository::class);
    }

    public function testCreate_HashesPasswordAndReadsBack(): void
    {
        $user = $this->users->create(name: 'مدیر', email: 'Admin@Blog.test', plainPassword: 'secret-1234');

        self::assertGreaterThan(0, $user->id);
        self::assertSame('admin@blog.test', $user->email);          // lowercase نرمال شد
        self::assertSame(UserStatus::Active, $user->status);
        self::assertTrue(Hash::check('secret-1234', $user->getAuthPassword()));
        self::assertNotSame('secret-1234', $user->getAuthPassword());
    }

    public function testCreate_DuplicateEmailRejected(): void
    {
        $this->users->create(name: 'یک', email: 'dup@blog.test', plainPassword: 'secret-1234');

        $this->expectException(ValidationException::class);
        $this->users->create(name: 'دو', email: 'dup@blog.test', plainPassword: 'another-5678');
    }

    public function testReader_FindByIdEmailAndBatch(): void
    {
        $a = $this->users->create(name: 'الف', email: 'a@blog.test', plainPassword: 'secret-1234');
        $b = $this->users->create(name: 'ب', email: 'b@blog.test', plainPassword: 'secret-1234');

        self::assertSame('الف', $this->users->findById($a->id)?->name);
        self::assertSame('ب', $this->users->findByEmail('B@blog.test')?->name);

        $map = $this->users->findByIds([(string) $a->id, (string) $b->id, '99999']);
        self::assertCount(2, $map);
        self::assertSame('الف', $map[(string) $a->id]->displayName());
        self::assertSame([], $this->users->findByIds([]));
    }

    public function testReader_DisplayNameFallback(): void
    {
        self::assertSame('کاربر', $this->users->displayNameOf(4242));
        $u = $this->users->create(name: 'نمایشی', email: 'd@blog.test', plainPassword: 'secret-1234');
        self::assertSame('نمایشی', $this->users->displayNameOf($u->id));
    }

    public function testReader_BoundAsContractSingleton(): void
    {
        $reader = $this->app()->make(UserReaderInterface::class);
        self::assertInstanceOf(DatabaseUserRepository::class, $reader);
        self::assertSame($reader, $this->app()->make('users.reader'));
    }

    public function testUpdatePassword_AndTouchLastLogin(): void
    {
        $user = $this->users->create(name: 'رمز', email: 'p@blog.test', plainPassword: 'old-secret-1');
        self::assertTrue($this->users->updatePassword($user->id, 'new-secret-2'));

        $fresh = $this->users->findUserById($user->id);
        self::assertNotNull($fresh);
        self::assertTrue(Hash::check('new-secret-2', $fresh->getAuthPassword()));

        $this->users->touchLastLogin($user->id);
        self::assertNotNull($this->users->findUserById($user->id)?->lastLoginAt);
    }

    public function testUserEntity_Contracts(): void
    {
        $user = $this->users->create(name: 'قرارداد', email: 'c@blog.test', plainPassword: 'secret-1234', mobile: '09120000000');

        // Authenticatable
        self::assertSame('id', $user->getAuthIdentifierName());
        self::assertSame($user->id, $user->getAuthIdentifier());
        self::assertSame('remember_token', $user->getRememberTokenName());
        self::assertNull($user->getRememberToken());
        $user->setRememberToken('tok-1');
        self::assertSame('tok-1', $user->getRememberToken());

        // Notifiable
        self::assertSame('c@blog.test', $user->routeNotificationFor('mail'));
        self::assertSame('09120000000', $user->routeNotificationFor('sms'));
        self::assertSame($user->id, $user->routeNotificationFor('database'));
        self::assertNull($user->routeNotificationFor('telegram'));
        self::assertSame($user->id, $user->getNotificationKey());

        // دامنه
        self::assertTrue($user->isActive());
        self::assertFalse($user->hasVerifiedEmail());
        self::assertSame('قرارداد', $user->displayName());
    }

    public function testUserEntity_FromRowMapsAllFields(): void
    {
        $user = User::fromRow([
            'id' => 7,
            'name' => 'ردیفی',
            'email' => 'row@blog.test',
            'mobile' => '09121111111',
            'status' => 'banned',
            'password' => 'hash-x',
            'bio' => 'زندگی‌نامه',
            'avatar' => '01HZX0000000000000000000AA',
            'email_verified_at' => '2026-10-01 10:00:00.000',
            'remember_token' => 'rt',
            'created_at' => '2026-10-01 09:00:00.000',
        ]);

        self::assertSame(7, $user->id);
        self::assertSame(UserStatus::Banned, $user->status);
        self::assertFalse($user->isActive());
        self::assertTrue($user->hasVerifiedEmail());
        self::assertSame('زندگی‌نامه', $user->bio);
        self::assertSame('hash-x', $user->getAuthPassword());
        self::assertSame('rt', $user->getRememberToken());
    }

    public function testUserStatus_Labels(): void
    {
        self::assertSame('فعال', UserStatus::Active->labelFa());
        self::assertSame('مسدود', UserStatus::Banned->labelFa());
        self::assertTrue(UserStatus::Active->canLogin());
        self::assertFalse(UserStatus::Inactive->canLogin());
    }
}
