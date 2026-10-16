<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Guards;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Auth\StatefulGuard;
use Tavaan\Core\Contracts\Auth\UserProviderInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Auth\Exceptions\AccountBannedException;
use Tavaan\Engine\Auth\Throttle\LoginThrottle;
use Tavaan\Engine\Http\Cookie\Cookie;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Session\Session;

/**
 * تَوان | Tavaan — Auth
 * SessionGuard — نگهبان نشست وب با Session Hardening (DOC-056 §5).
 *
 * 🔒 Session Fixation: regenerate(destroy) در login.
 * 🔒 Session Hijack: fingerprint (UA + Accept-Language؛ بدون IP کامل).
 * 🔒 Brute Force: LoginThrottle با lockout.
 * 🔒 پیام یکسان برای «کاربر نبود» و «رمز اشتباه» (User Enumeration).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class SessionGuard implements StatefulGuard
{
    private ?Authenticatable $user = null;

    private bool $recallAttempted = false;

    /** کوکی remember صف‌شده برای افزودن به پاسخ (توسط middleware/kernel). */
    private ?Cookie $queuedRecaller = null;

    /** @var \Closure(): ?Session */
    private $sessionResolver;

    /** @var \Closure(): ?Request */
    private $requestResolver;

    /**
     * @param array<string, mixed> $config session_hardening + remember
     */
    public function __construct(
        private readonly string $name,
        private readonly UserProviderInterface $provider,
        private readonly LoginThrottle $throttle,
        ?\Closure $sessionResolver = null,
        ?\Closure $requestResolver = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly array $config = [],
    ) {
        $this->sessionResolver = $sessionResolver ?? static fn (): ?Session => null;
        $this->requestResolver = $requestResolver ?? static fn (): ?Request => null;
    }

    private function session(): ?Session
    {
        $session = ($this->sessionResolver)();

        return $session instanceof Session ? $session : null;
    }

    private function request(): ?Request
    {
        $request = ($this->requestResolver)();

        return $request instanceof Request ? $request : null;
    }

    private function sessionKey(): string
    {
        return 'login_' . $this->name . '_' . \substr(\hash('sha256', static::class), 0, 16);
    }

    // ─── StatefulGuard ────────────────────────────────────────────────────

    public function attempt(array $credentials, bool $remember = false): bool
    {
        $throttleKey = $this->throttleKey($credentials);

        // 🔒 قفل حساب — پیش از هر verify
        $this->throttle->assertAllowed($throttleKey);

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null || !$this->provider->validateCredentials($user, $credentials)) {
            $this->throttle->hit($throttleKey);
            $this->events?->dispatch('auth.login.failed', [
                'identifier' => (string) ($credentials['email'] ?? $credentials['username'] ?? ''),
                'ip' => $this->request()?->ip(),
                'reason' => 'auth.credentials.invalid',   // 🔒 پیام یکسان
            ]);

            return false;
        }

        if ($this->isBanned($user)) {
            throw new AccountBannedException(context: ['identifier' => (string) $user->getAuthIdentifier()]);
        }

        // 🔒 Rehash خودکار (DOC-056 §5.2)
        $this->provider->rehashPasswordIfRequired($user, $credentials);

        $this->login($user, $remember);
        $this->throttle->clear($throttleKey);

        return true;
    }

    public function once(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null || !$this->provider->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->setUser($user);

        return true;
    }

    public function validate(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $session = $this->session();

        if ($session !== null) {
            // 🔒 Session Fixation — بازتولید ID با نابودی دادهٔ قدیم
            if ((bool) ($this->config['session_hardening']['regenerate_on_login'] ?? true)) {
                $session->regenerate(destroy: true);
            }

            $session->put($this->sessionKey(), $user->getAuthIdentifier());
            $session->regenerateToken();   // 🔒 چرخش توکن CSRF هنگام login

            if ((bool) ($this->config['session_hardening']['fingerprint'] ?? true)) {
                $session->put($this->fingerprintKey(), $this->fingerprint());
            }
        }

        if ($remember) {
            $this->ensureRememberToken($user);
        }

        $this->setUser($user);

        $this->events?->dispatch('auth.login.succeeded', [
            'identifier' => (string) $user->getAuthIdentifier(),
            'ip' => $this->request()?->ip(),
            'remember' => $remember,
        ]);
    }

    public function loginUsingId(mixed $id, bool $remember = false): ?Authenticatable
    {
        $user = $this->provider->retrieveById($id);
        if ($user === null) {
            return null;
        }

        $this->login($user, $remember);

        return $user;
    }

    public function logout(): void
    {
        $user = $this->user;
        $session = $this->session();

        if ($session !== null) {
            $session->forget($this->sessionKey());
            $session->forget($this->fingerprintKey());
            $session->regenerateToken();
        }

        $this->user = null;

        if ($user !== null) {
            $this->events?->dispatch('auth.logout', ['identifier' => (string) $user->getAuthIdentifier()]);
        }
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): int|string|null
    {
        $user = $this->user();

        return $user?->getAuthIdentifier();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $session = $this->session();

        // 1) از نشست
        if ($session !== null) {
            // 🔒 fingerprint mismatch → خروج اجباری (Hijack)
            if ((bool) ($this->config['session_hardening']['fingerprint'] ?? true)) {
                $stored = $session->get($this->fingerprintKey());
                if (\is_string($stored) && $stored !== '' && !\hash_equals($stored, $this->fingerprint())) {
                    $session->forget($this->sessionKey());
                    $session->forget($this->fingerprintKey());
                    $this->events?->dispatch('auth.fingerprint_mismatch', ['ip' => $this->request()?->ip()]);

                    return null;
                }
            }

            $id = $session->get($this->sessionKey());
            if ($id !== null) {
                $user = $this->provider->retrieveById($id);
                if ($user !== null && !$this->isBanned($user)) {
                    return $this->user = $user;
                }
                $session->forget($this->sessionKey());
            }
        }

        // 2) از کوکی remember (یک‌بار)
        if (!$this->recallAttempted) {
            $this->recallAttempted = true;
            $user = $this->userFromRecaller();
            if ($user !== null) {
                $this->login($user, remember: true);

                return $user;
            }
        }

        return null;
    }

    public function userOrFail(): Authenticatable
    {
        $user = $this->user();
        if ($user === null) {
            throw new \Tavaan\Core\Exceptions\Auth\AuthenticationException();
        }

        return $user;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    // ─── remember me ──────────────────────────────────────────────────────

    private function ensureRememberToken(Authenticatable $user): void
    {
        $token = $user->getRememberToken();
        if ($token === null || $token === '') {
            $token = \bin2hex(\random_bytes(32));
            $this->provider->updateRememberToken($user, $token);
        }

        // کوکی recaller برای صف‌شدن در پاسخ
        $this->queuedRecaller = $this->recallerCookie($user, $token);
    }

    /** کوکی remember صف‌شده را برمی‌دارد (برای افزودن به Response). */
    public function pullRecallerCookie(): ?Cookie
    {
        $cookie = $this->queuedRecaller;
        $this->queuedRecaller = null;

        return $cookie;
    }

    private function recallerCookie(Authenticatable $user, string $token): Cookie
    {
        $name = (string) ($this->config['remember']['cookie'] ?? 'tavaan_remember');
        $duration = (int) ($this->config['remember']['duration'] ?? 2592000);
        $value = $user->getAuthIdentifier() . '|' . $token . '|' . \hash('sha256', $user->getAuthPassword());

        return new Cookie($name, $value, \time() + $duration, '/', null, false, true, 'Lax');
    }

    private function userFromRecaller(): ?Authenticatable
    {
        $request = $this->request();
        if ($request === null) {
            return null;
        }

        $name = (string) ($this->config['remember']['cookie'] ?? 'tavaan_remember');
        $recaller = $request->cookie($name);
        if (!\is_string($recaller) || \substr_count($recaller, '|') !== 2) {
            return null;
        }

        [$id, $token, $passwordHash] = \explode('|', $recaller, 3);

        $user = $this->provider->retrieveByToken($id, $token);
        if ($user === null) {
            return null;
        }

        // 🔒 اعتبارسنجی هش رمز در recaller (کوکی دست‌ساز رد شود)
        if (!\hash_equals(\hash('sha256', $user->getAuthPassword()), $passwordHash)) {
            return null;
        }

        return $this->isBanned($user) ? null : $user;
    }

    /** خروج دستگاه‌های دیگر (با تغییر remember token + رویداد). */
    public function logoutOtherDevices(#[\SensitiveParameter] string $currentPassword): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // 🔒 تایید رمز جاری قبل از این کار حساس
        if (!$this->provider->validateCredentials($user, ['password' => $currentPassword])) {
            return false;
        }

        $newToken = \bin2hex(\random_bytes(32));
        $this->provider->updateRememberToken($user, $newToken);

        $this->events?->dispatch('auth.other_devices_logout', ['identifier' => (string) $user->getAuthIdentifier()]);

        return true;
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function fingerprintKey(): string
    {
        return '_guard_fingerprint_' . $this->name;
    }

    /** 🔒 UA + Accept-Language (بدون IP — موبایل IP عوض می‌کند؛ DOC-056 §5.1). */
    private function fingerprint(): string
    {
        $request = $this->request();

        return \hash('sha256', \implode('|', [
            $request?->userAgent() ?? '',
            $request?->header('Accept-Language') ?? '',
        ]));
    }

    /** کلید throttle: شناسه + IP (hash). */
    private function throttleKey(array $credentials): string
    {
        $identifier = \strtolower((string) ($credentials['email'] ?? $credentials['username'] ?? $credentials['mobile'] ?? ''));

        return \hash('sha256', $identifier . '|' . ($this->request()?->ip() ?? 'cli'));
    }

    private function isBanned(Authenticatable $user): bool
    {
        if (\method_exists($user, 'isBanned')) {
            return (bool) $user->isBanned();
        }

        return false;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function provider(): UserProviderInterface
    {
        return $this->provider;
    }
}
