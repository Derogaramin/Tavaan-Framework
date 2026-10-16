<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Context\RequestStack;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Auth\AuthManager;
use Tavaan\Engine\Auth\Exceptions\AccountBannedException;
use Tavaan\Engine\Auth\Exceptions\ThrottledException;
use Tavaan\Engine\Auth\Exceptions\TokenExpiredException;
use Tavaan\Engine\Auth\Exceptions\TokenInvalidException;
use Tavaan\Engine\Auth\Guards\SessionGuard;
use Tavaan\Engine\Auth\Jwt\Base32;
use Tavaan\Engine\Auth\Jwt\JwtEncoder;
use Tavaan\Engine\Auth\Otp\ArrayOtpChannel;
use Tavaan\Engine\Auth\TwoFactor\Totp;
use Tavaan\Engine\Database\ORM\Testing\TSecureUser;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Session\Handlers\ArrayHandler;
use Tavaan\Engine\Session\Session;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Auth — DOC-056 §11 (T-01..T-12).
 */
final class AuthTest extends TestCase
{
    private Application $app;

    private AuthManager $auth;

    private RequestStack $stack;

    private Session $session;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();

        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('auth.providers.users', ['driver' => 'database', 'table' => 'users', 'model' => null]);
        $config->set('auth.jwt', [
            'algorithm' => 'HS256', 'ttl' => 3600, 'refresh_ttl' => 86400,
            'issuer' => 'http://test', 'audience' => 'http://test', 'leeway' => 0,
        ]);
        $config->set('auth.otp', ['length' => 6, 'ttl' => 120, 'channel' => 'array']);
        $config->set('auth.throttle.login', ['max' => 3, 'decay' => 60, 'lock' => 300]);

        $db = $this->app->make('db')->connection();
        $schema = $this->app->make('schema');

        $schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email', 190)->unique();
            $t->string('password');
            $t->string('mobile', 20)->nullable();
            $t->boolean('is_banned')->default(false);
            $t->rememberToken();
            $t->timestamps(3);
        });
        $schema->create('orm_secure_users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email', 190)->unique();
            $t->string('password');
            $t->text('mobile')->nullable();
            $t->rememberToken();
            $t->timestamps(3);
        });

        // جداول auth (مطابق migration موتور)
        $schema->create('password_resets', function (Blueprint $t): void {
            $t->string('email', 190)->primary();
            $t->string('token', 64);
            $t->timestamp('created_at', 3)->nullable();
        });
        $schema->create('personal_access_tokens', function (Blueprint $t): void {
            $t->id();
            $t->string('user_id', 64)->index();
            $t->string('jti', 64)->unique();
            $t->string('family', 64)->index();
            $t->string('type', 16);
            $t->string('scopes', 255)->default('[]');
            $t->boolean('used')->default(false);
            $t->boolean('revoked')->default(false);
            $t->unsignedBigInteger('expires_at')->index();
            $t->timestamp('created_at', 3)->nullable();
        });
        $schema->create('user_devices', function (Blueprint $t): void {
            $t->id();
            $t->string('user_id', 64)->index();
            $t->string('fingerprint', 64);
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->boolean('revoked')->default(false);
            $t->timestamp('last_used_at', 3)->nullable();
            $t->timestamp('created_at', 3)->nullable();
        });
        $schema->create('two_factor_secrets', function (Blueprint $t): void {
            $t->string('user_id', 64)->primary();
            $t->text('secret');
            $t->text('recovery_codes')->nullable();
            $t->timestamp('confirmed_at', 3)->nullable();
            $t->timestamp('created_at', 3)->nullable();
            $t->timestamp('updated_at', 3)->nullable();
        });

        // کاربر نمونه (رمز با hasher موتور)
        $hash = $this->app->make('hash')->make('secret-password-1');
        $db->table('users')->insert([
            'name' => 'Ali', 'email' => 'ali@test.io', 'password' => $hash,
            'mobile' => '09121234567', 'is_banned' => 0,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->stack = $this->app->make(RequestStack::class);
        $this->auth = $this->app->make('auth');

        // درخواست + session آرایه‌ای برای guard وب
        $this->session = new Session(
            id: \bin2hex(\random_bytes(20)),
            name: 'tavaan_session',
            handler: new ArrayHandler(),
        );
        $this->session->start();

        $request = Request::create('/dashboard', 'GET', server: [
            'HTTP_USER_AGENT' => 'TestAgent/1.0',
            'HTTP_ACCEPT_LANGUAGE' => 'fa-IR',
            'REMOTE_ADDR' => '203.0.113.10',
        ])->withAttribute('session', $this->session);

        $this->stack->push($request);
    }

    protected function tearDown(): void
    {
        $this->stack->reset();
        Clock::setTestNow(null);
    }

    // ─── T-01: attempt ────────────────────────────────────────────────────

    public function testAttemptSuccessAndFailure(): void
    {
        $guard = $this->auth->guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);

        $this->assertFalse($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'wrong']));
        $this->assertTrue($this->auth->guest());

        $this->assertTrue($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1']));
        $this->assertTrue($this->auth->check());
        $this->assertSame('ali@test.io', (string) $this->auth->user()?->__get('email'));

        // user وجود ندارد → false (نه exception)
        $this->auth->logout();
        $this->assertFalse($this->auth->attempt(['email' => 'ghost@test.io', 'password' => 'whatever']));
    }

    // ─── T-02: Session Fixation ───────────────────────────────────────────

    public function testSessionRegeneratedOnLogin(): void
    {
        $oldId = $this->session->id();

        $this->assertTrue($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1']));

        $this->assertNotSame($oldId, $this->session->id(), 'session id must regenerate on login (fixation)');
    }

    // ─── T-03: Throttle + Lockout ─────────────────────────────────────────

    public function testThrottleLocksAfterMaxAttempts(): void
    {
        // max=3 (config تست) → تلاش چهارم باید قفل باشد
        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'bad']));
        }

        $this->expectException(ThrottledException::class);
        $this->auth->attempt(['email' => 'ali@test.io', 'password' => 'bad']);
    }

    public function testThrottleClearedOnSuccess(): void
    {
        $this->auth->attempt(['email' => 'ali@test.io', 'password' => 'bad']);
        $this->auth->attempt(['email' => 'ali@test.io', 'password' => 'bad']);

        $this->assertTrue($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1']));

        // پس از موفقیت، شمارنده پاک → تلاش غلط بعدی قفل نیست
        $this->assertFalse($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'bad']));
    }

    // ─── T-04: Remember me ────────────────────────────────────────────────

    public function testRememberMeSetsTokenAndCookie(): void
    {
        $guard = $this->auth->guard('web');
        $this->assertTrue($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1'], remember: true));

        // remember token در DB ذخیره شد
        $row = $this->app->make('db')->table('users')->where('email', 'ali@test.io')->first();
        $this->assertNotNull($row['remember_token']);
        $this->assertSame(64, \strlen((string) $row['remember_token']));

        // کوکی recaller صف شد
        $cookie = $guard->pullRecallerCookie();
        $this->assertNotNull($cookie);
        $this->assertSame('tavaan_remember', $cookie->name);
        $this->assertTrue($cookie->httpOnly);
    }

    // ─── T-05/T-06: JWT ───────────────────────────────────────────────────

    public function testJwtEncodeDecode(): void
    {
        $jwt = $this->auth->jwt();
        $token = $jwt->encode(['sub' => '1', 'scopes' => ['*']]);

        $claims = $jwt->decode($token);
        $this->assertSame('1', $claims['sub']);
        $this->assertSame('http://test', $claims['iss']);
        $this->assertSame('http://test', $claims['aud']);
        $this->assertArrayHasKey('jti', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testJwtAlgNoneRejected(): void
    {
        $jwt = $this->auth->jwt();

        // ساخت دستی توکن با alg=none و امضای خالی
        $header = \rtrim(\strtr(\base64_encode('{"typ":"JWT","alg":"none"}'), '+/', '-_'), '=');
        $payload = \rtrim(\strtr(\base64_encode('{"sub":"1","iss":"http://test","aud":"http://test","exp":9999999999,"jti":"x"}'), '+/', '-_'), '=');
        $forged = $header . '.' . $payload . '.';

        try {
            $jwt->decode($forged);
            self::fail('alg=none must be rejected');
        } catch (TokenInvalidException $e) {
            $this->assertSame('auth.token.algorithm_none', $e->messageId);
        }

        // با امضای جعلی هم رد شود
        $forged2 = $header . '.' . $payload . '.' . \rtrim(\strtr(\base64_encode('fake-sig'), '+/', '-_'), '=');
        $this->expectException(TokenInvalidException::class);
        $jwt->decode($forged2);
    }

    public function testJwtAlgorithmConfusionRejected(): void
    {
        // توکن با header RS256 وقتی پیکربندی HS256 است → رد (بدون تلاش verify)
        $jwt = $this->auth->jwt();

        $header = \rtrim(\strtr(\base64_encode('{"typ":"JWT","alg":"RS256"}'), '+/', '-_'), '=');
        $payload = \rtrim(\strtr(\base64_encode('{"sub":"1","iss":"http://test","aud":"http://test","exp":9999999999,"jti":"x"}'), '+/', '-_'), '=');

        // «امضا» = HMAC با کلید عمومی جعلی (حملهٔ کلاسیک confusion)
        $sig = \hash_hmac('sha256', $header . '.' . $payload, 'some-public-key', true);
        $confused = $header . '.' . $payload . '.' . \rtrim(\strtr(\base64_encode($sig), '+/', '-_'), '=');

        try {
            $jwt->decode($confused);
            self::fail('algorithm confusion must be rejected');
        } catch (TokenInvalidException $e) {
            $this->assertSame('auth.token.algorithm_mismatch', $e->messageId);
        }
    }

    public function testJwtTamperedSignatureRejected(): void
    {
        $jwt = $this->auth->jwt();
        $token = $jwt->encode(['sub' => '1']);

        $parts = \explode('.', $token);
        $parts[2] = \rtrim(\strtr(\base64_encode('tampered-signature'), '+/', '-_'), '=');

        $this->expectException(TokenInvalidException::class);
        $jwt->decode(\implode('.', $parts));
    }

    public function testJwtExpiration(): void
    {
        $jwt = $this->auth->jwt();
        $token = $jwt->encode(['sub' => '1'], ['ttl' => -100]);

        $this->expectException(TokenExpiredException::class);
        $jwt->decode($token);
    }

    public function testTokenGuardBearerFlow(): void
    {
        $pair = $this->auth->token()->issue(new \Tavaan\Engine\Auth\GenericUser(['id' => 1]), ttl: 3600);

        // درخواست جدید با هدر Bearer
        $request = Request::create('/api/me', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $pair->accessToken,
        ]);
        $this->stack->push($request);

        $api = $this->auth->guard('api');
        $user = $api->user();
        $this->assertNotNull($user);
        $this->assertSame(1, (int) $user->getAuthIdentifier());

        // revoke → ۴۰۱
        $this->auth->token()->revoke($pair->tokenId);
        $this->stack->pop();
        $this->stack->push(Request::create('/api/me', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $pair->accessToken]));
        $freshGuard = new \Tavaan\Engine\Auth\Guards\TokenGuard('api', $this->auth->provider('users'), $this->auth->jwt(), fn (): ?string => $pair->accessToken);

        $this->expectException(TokenInvalidException::class);
        $freshGuard->authenticate($pair->accessToken);
    }

    // ─── T-07: Refresh Rotation + Reuse Detection ─────────────────────────

    public function testRefreshRotationAndReuseDetection(): void
    {
        $user = new \Tavaan\Engine\Auth\GenericUser(['id' => 1]);
        $pair1 = $this->auth->token()->issue($user, ttl: 3600);

        // refresh اول → pair جدید در همان family
        $pair2 = $this->auth->token()->refresh($pair1->refreshToken);
        $this->assertSame($pair1->family, $pair2->family);
        $this->assertNotSame($pair1->accessToken, $pair2->accessToken);

        // 🔒 استفادهٔ مجدد از refresh قدیمی → reuse detection + revoke کل family
        $reuseDetected = false;
        $this->app->make(DispatcherInterface::class)->listen('auth.token.reuse_detected', function () use (&$reuseDetected): void {
            $reuseDetected = true;
        });

        try {
            $this->auth->token()->refresh($pair1->refreshToken);
            self::fail('refresh reuse must throw');
        } catch (TokenInvalidException $e) {
            $this->assertSame('auth.token.reuse_detected', $e->messageId);
        }

        $this->assertTrue($reuseDetected);

        // حتی refresh جدید هم باطل شده (کل family)
        try {
            $this->auth->token()->refresh($pair2->refreshToken);
            self::fail('family must be revoked after reuse');
        } catch (TokenInvalidException $e) {
            $this->assertSame('auth.token.reuse_detected', $e->messageId);
        }
    }

    // ─── T-08: OTP ────────────────────────────────────────────────────────

    public function testOtpOneTimeAndExpiry(): void
    {
        $channel = new ArrayOtpChannel();
        $this->auth->setOtpChannel($channel);

        $user = new \Tavaan\Engine\Auth\GenericUser(['id' => 1, 'mobile' => '09121234567']);

        $result = $this->auth->otp()->send($user, 'sms');
        $this->assertSame('0912***567', $result['masked']);   // بدون نشت کامل
        $this->assertSame(120, $result['expires_in']);

        $code = $channel->lastCode();
        $this->assertNotNull($code);
        $this->assertSame(6, \strlen((string) $code));

        // کد غلط
        $this->assertFalse($this->auth->otp()->verify($user, '000000'));

        // کد درست — یک‌بارمصرف
        $this->assertTrue($this->auth->otp()->verify($user, (string) $code));
        $this->assertFalse($this->auth->otp()->verify($user, (string) $code), 'OTP must be single-use');

        // انقضا با Clock fake
        $this->auth->otp()->send($user, 'sms');
        $code2 = $channel->lastCode();
        Clock::setTestNow(new \DateTimeImmutable('+121 seconds'));
        $this->assertFalse($this->auth->otp()->verify($user, (string) $code2));
    }

    // ─── T-09: TOTP با RFC 6238 vectors ───────────────────────────────────

    public function testTotpRfc6238Vectors(): void
    {
        // secret استاندارد RFC: "12345678901234567890" (ASCII) → base32
        $secret = Base32::encode('12345678901234567890');
        $totp = new Totp(period: 30, digits: 8, window: 0);

        // RFC 6238 Appendix B (SHA1)
        $this->assertSame('94287082', $totp->generate($secret, 59));
        $this->assertSame('07081804', $totp->generate($secret, 1111111109));
        $this->assertSame('14050471', $totp->generate($secret, 1111111111));
        $this->assertSame('89005924', $totp->generate($secret, 1234567890));
        $this->assertSame('69279032', $totp->generate($secret, 2000000000));

        // verify در همان زمان
        $this->assertTrue($totp->verify($secret, '94287082', 59));
        $this->assertFalse($totp->verify($secret, '94287082', 59 + 30 * 3));

        // window=1 → ±۳۰ ثانیه
        $totpWindow = new Totp(period: 30, digits: 8, window: 1);
        $this->assertTrue($totpWindow->verify($secret, '94287082', 59 + 30));
        $this->assertTrue($totpWindow->verify($secret, '94287082', 59 - 30));
    }

    public function testTwoFactorEnableVerifyRecovery(): void
    {
        $user = new \Tavaan\Engine\Auth\GenericUser(['id' => 1]);
        $tf = $this->auth->twoFactor();

        $setup = $tf->enable($user);
        $this->assertSame(32, \strlen($setup['secret']));
        $this->assertStringContainsString('otpauth://totp/', $setup['uri']);
        $this->assertCount(8, $setup['recovery_codes']);

        // قبل از confirm غیرفعال است
        $this->assertFalse($tf->isEnabled($user));

        $totp = new Totp();
        $code = $totp->generate($setup['secret']);
        $this->assertTrue($tf->confirm($user, $code));
        $this->assertTrue($tf->isEnabled($user));

        // verify با TOTP جاری
        $this->assertTrue($tf->verifyTotp($user, $totp->generate($setup['secret'])));

        // کد بازیابی یک‌بارمصرف
        $recovery = $setup['recovery_codes'][0];
        $this->assertTrue($tf->verifyTotp($user, $recovery));
        $this->assertFalse($tf->verifyTotp($user, $recovery), 'recovery code must be single-use');
    }

    // ─── T-10: Rehash خودکار ──────────────────────────────────────────────

    public function testAutomaticRehashOnLogin(): void
    {
        // رمز با bcrypt cost قدیمی ذخیره می‌شود
        $oldHash = \password_hash('secret-password-1', PASSWORD_BCRYPT, ['cost' => 10]);
        $db = $this->app->make('db');
        $db->table('users')->where('email', 'ali@test.io')->update(['password' => $oldHash]);

        $this->assertTrue($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1']));

        $row = $db->table('users')->where('email', 'ali@test.io')->first();
        $this->assertNotSame($oldHash, $row['password'], 'password must be rehashed after login');
        $this->assertTrue(\str_starts_with((string) $row['password'], '$argon2id$'), 'rehash must upgrade to argon2id');
        $this->assertTrue(\password_verify('secret-password-1', (string) $row['password']));
    }

    // ─── T-11: پیام یکسان (User Enumeration) ──────────────────────────────

    public function testSameFailureForMissingUserAndWrongPassword(): void
    {
        $events = [];
        $this->app->make(DispatcherInterface::class)->listen('auth.login.failed', function (array $payload) use (&$events): void {
            $events[] = $payload['reason'] ?? '';
        });

        $this->auth->attempt(['email' => 'ghost@test.io', 'password' => 'anything']);
        $this->auth->attempt(['email' => 'ali@test.io', 'password' => 'wrong-password']);

        $this->assertCount(2, $events);
        $this->assertSame($events[0], $events[1], 'failure reason must be identical (no enumeration)');
        $this->assertSame('auth.credentials.invalid', $events[0]);
    }

    // ─── T-12: Device tracking ────────────────────────────────────────────

    public function testDeviceListAndRevoke(): void
    {
        $user = new \Tavaan\Engine\Auth\GenericUser(['id' => 1]);
        $devices = $this->auth->devices();

        $id1 = $devices->record($user, '1.2.3.4', 'Mozilla/5.0 Phone');
        $devices->record($user, '5.6.7.8', 'Mozilla/5.0 Desktop');

        $list = $devices->list($user);
        $this->assertCount(2, $list);

        $this->assertTrue($devices->revoke($id1, $user));
        $this->assertCount(1, $devices->list($user));

        // رکورد تکراری (همان fingerprint) → به‌روزرسانی last_used نه insert جدید
        $devices->record($user, '5.6.7.8', 'Mozilla/5.0 Desktop');
        $this->assertCount(1, $devices->list($user));
    }

    // ─── banned / register / password reset ───────────────────────────────

    public function testBannedUserCannotLogin(): void
    {
        $this->app->make('db')->table('users')->where('email', 'ali@test.io')->update(['is_banned' => 1]);

        $this->expectException(AccountBannedException::class);
        $this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1']);
    }

    public function testRegisterHashesPassword(): void
    {
        $user = $this->auth->register(['name' => 'New', 'email' => 'new@test.io', 'password' => 'my-secure-pass']);

        $row = $this->app->make('db')->table('users')->where('email', 'new@test.io')->first();
        $this->assertNotSame('my-secure-pass', $row['password']);
        $this->assertTrue(\password_verify('my-secure-pass', (string) $row['password']));
        $this->assertSame((string) $user->getAuthIdentifier(), (string) $row['id']);
    }

    public function testPasswordResetFlow(): void
    {
        $capturedToken = null;
        $dispatcher = $this->app->make(DispatcherInterface::class);
        $dispatcher->listen('auth.password.reset_link_sent', function () use (&$capturedToken): void {
            // token از طریق linkSender تست گرفته می‌شود
        });

        // linkSender تست: token را ضبط کن
        $reflection = new \ReflectionProperty(AuthManager::class, 'passwordBroker');
        $broker = new \Tavaan\Engine\Auth\Passwords\PasswordBroker(
            $this->app->make('db'),
            $this->auth->provider('users'),
            $this->app->make('hash'),
            'password_resets',
            3600,
            null,
            null,
            function (string $email, string $token) use (&$capturedToken): void {
                $capturedToken = $token;
            },
        );

        $this->assertSame('auth.reset.sent', $broker->sendResetLink('ali@test.io'));
        $this->assertNotNull($capturedToken);

        // token غلط → رد
        $this->assertSame('auth.reset.invalid_token', $broker->reset('ali@test.io', 'wrong-token', 'new-pass-1', fn (): mixed => null));

        // token درست → رمز عوض شود
        $saved = null;
        $result = $broker->reset('ali@test.io', (string) $capturedToken, 'new-pass-1', function ($user, string $plain) use (&$saved): void {
            $this->app->make('db')->table('users')->where('id', $user->getAuthIdentifier())
                ->update(['password' => $this->app->make('hash')->make($plain)]);
            $saved = $plain;
        });
        $this->assertSame('auth.reset.done', $result);
        $this->assertSame('new-pass-1', $saved);

        // token یک‌بارمصرف
        $this->assertSame('auth.reset.invalid_token', $broker->reset('ali@test.io', (string) $capturedToken, 'x', fn (): mixed => null));

        // ایمیل ناموجود → همان «sent» (ضد enumeration)
        $this->assertSame('auth.reset.sent', $broker->sendResetLink('ghost@test.io'));
        unset($reflection);
    }

    public function testLogoutOtherDevicesRequiresPassword(): void
    {
        $guard = $this->auth->guard('web');
        $this->assertTrue($this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1'], remember: true));

        $this->assertFalse($guard->logoutOtherDevices('wrong-password'));
        $this->assertTrue($guard->logoutOtherDevices('secret-password-1'));
    }

    public function testLoginEventsDispatched(): void
    {
        $events = [];
        $this->app->make(DispatcherInterface::class)->listen('auth.login.succeeded', function (array $p) use (&$events): void {
            $events[] = 'succeeded';
        });
        $this->app->make(DispatcherInterface::class)->listen('auth.logout', function () use (&$events): void {
            $events[] = 'logout';
        });

        $this->auth->attempt(['email' => 'ali@test.io', 'password' => 'secret-password-1']);
        $this->auth->logout();

        $this->assertSame(['succeeded', 'logout'], $events);
    }
}
