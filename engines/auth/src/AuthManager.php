<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth;

use Tavaan\Core\Context\RequestStack;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Auth\GuardInterface;
use Tavaan\Core\Contracts\Auth\StatefulGuard;
use Tavaan\Core\Contracts\Auth\UserProviderInterface;
use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Auth\Devices\DeviceManager;
use Tavaan\Engine\Auth\Guards\SessionGuard;
use Tavaan\Engine\Auth\Guards\TokenGuard;
use Tavaan\Engine\Auth\Jwt\JwtEncoder;
use Tavaan\Engine\Auth\Otp\ArrayOtpChannel;
use Tavaan\Engine\Auth\Otp\LogOtpChannel;
use Tavaan\Engine\Auth\Otp\OtpChannelInterface;
use Tavaan\Engine\Auth\Otp\OtpManager;
use Tavaan\Engine\Auth\Passwords\PasswordBroker;
use Tavaan\Engine\Auth\Providers\DatabaseUserProvider;
use Tavaan\Engine\Auth\Throttle\LoginThrottle;
use Tavaan\Engine\Auth\TwoFactor\TwoFactorManager;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Session\Session;

/**
 * تَوان | Tavaan — Auth
 * AuthManager — مدیریت چند Guard (web/api/admin) + سرویس‌های Token/OTP/2FA/Device
 * و ثبت‌نام/بازیابی رمز (DOC-056 §3/§4).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class AuthManager
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];

    /** @var array<string, UserProviderInterface> */
    private array $providers = [];

    private ?TokenManager $tokenManager = null;

    private ?OtpManager $otpManager = null;

    private ?TwoFactorManager $twoFactorManager = null;

    private ?DeviceManager $deviceManager = null;

    private ?PasswordBroker $passwordBroker = null;

    private ?JwtEncoder $jwtEncoder = null;

    private ?OtpChannelInterface $otpChannel = null;

    public function __construct(
        private readonly ?RepositoryInterface $config = null,
        private readonly ?ConnectionInterface $db = null,
        private readonly ?HasherInterface $hasher = null,
        private readonly ?EncrypterInterface $crypt = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly ?CacheRepository $cache = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    // ─── Guard / Provider ─────────────────────────────────────────────────

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= $this->getDefaultGuard();

        return $this->guards[$name] ??= $this->buildGuard($name);
    }

    public function getDefaultGuard(): string
    {
        return (string) ($this->config?->get('auth.defaults.guard', 'web') ?? 'web');
    }

    public function setDefaultGuard(string $name): void
    {
        $this->guards = [];
        $this->config?->set('auth.defaults.guard', $name);
    }

    public function provider(?string $name = null): UserProviderInterface
    {
        $name ??= (string) ($this->config?->get('auth.providers.default', 'users') ?? 'users');

        return $this->providers[$name] ??= $this->buildProvider($name);
    }

    private function buildGuard(string $name): GuardInterface
    {
        $config = (array) ($this->config?->get('auth.guards.' . $name, []) ?? []);
        if ($config === []) {
            throw new \InvalidArgumentException(\sprintf('Auth guard [%s] is not configured.', $name));
        }

        $provider = $this->provider((string) ($config['provider'] ?? 'users'));

        return match ((string) ($config['driver'] ?? 'session')) {
            'session' => new SessionGuard(
                $name,
                $provider,
                $this->loginThrottle(),
                $this->sessionResolver(),
                $this->requestResolver(),
                $this->events,
                (array) ($this->config?->get('auth', []) ?? []),
            ),
            'jwt', 'token' => new TokenGuard(
                $name,
                $provider,
                $this->jwt(),
                $this->bearerResolver(),
            ),
            default => throw new \InvalidArgumentException(\sprintf('Unknown auth driver [%s].', (string) $config['driver'])),
        };
    }

    private function buildProvider(string $name): UserProviderInterface
    {
        $config = (array) ($this->config?->get('auth.providers.' . $name, []) ?? []);
        if ($config === [] || $this->db === null || $this->hasher === null) {
            throw new \LogicException(\sprintf('User provider [%s] requires database + hash engines.', $name));
        }

        return new DatabaseUserProvider($this->db, $this->hasher, $config);
    }

    // ─── resolverهای درخواست/نشست ─────────────────────────────────────────

    private function currentRequest(): ?Request
    {
        $request = $this->requestStack?->current();

        return $request instanceof Request ? $request : null;
    }

    private function sessionResolver(): \Closure
    {
        return function (): ?Session {
            $session = $this->currentRequest()?->session();
            if ($session instanceof Session) {
                return $session;
            }

            // 🔒 fallback: StartSessionMiddleware نشست را به یک clone از
            // request با attribute می‌بندد و همان clone را در pipeline جلو
            // می‌فرستد، ولی RequestStack درخواست اصلی (بدون attribute) را
            // نگه می‌دارد → نشستِ scope-شدهٔ container تنها منبع قابل‌اتکا است.
            if ($this->container !== null && $this->container->bound('session')) {
                $scoped = $this->container->make('session');

                return $scoped instanceof Session ? $scoped : null;
            }

            return null;
        };
    }

    private function requestResolver(): \Closure
    {
        return fn (): ?Request => $this->currentRequest();
    }

    private function bearerResolver(): \Closure
    {
        return function (): ?string {
            $request = $this->currentRequest();
            if ($request === null) {
                return null;
            }

            $token = $request->bearerToken();
            if ($token !== null && $token !== '') {
                return $token;
            }

            $header = $request->header('Authorization');

            return \is_string($header) && \str_starts_with($header, 'Bearer ') ? \substr($header, 7) : null;
        };
    }

    // ─── JWT / Token ──────────────────────────────────────────────────────

    public function jwt(): JwtEncoder
    {
        return $this->jwtEncoder ??= new JwtEncoder(
            (string) ($this->config?->get('app.key', '') ?? ''),
            (array) ($this->config?->get('auth.jwt', []) ?? []),
            fn (string $jti): bool => $this->token()->isKnownRevoked($jti),
        );
    }

    public function token(): TokenManager
    {
        if ($this->tokenManager !== null) {
            return $this->tokenManager;
        }
        if ($this->db === null || $this->crypt === null) {
            throw new \LogicException('TokenManager requires database + crypt engines.');
        }

        return $this->tokenManager = new TokenManager(
            $this->jwt(),
            $this->crypt,
            $this->db,
            (string) ($this->config?->get('auth.tokens.table', 'personal_access_tokens') ?? 'personal_access_tokens'),
            $this->events,
        );
    }

    // ─── OTP / 2FA / Device ───────────────────────────────────────────────

    public function setOtpChannel(OtpChannelInterface $channel): void
    {
        $this->otpChannel = $channel;
        $this->otpManager = null;
    }

    public function otp(): OtpManager
    {
        if ($this->otpManager !== null) {
            return $this->otpManager;
        }
        if ($this->cache === null || $this->hasher === null) {
            throw new \LogicException('OtpManager requires cache + hash engines.');
        }

        $channel = $this->otpChannel ?? match ((string) ($this->config?->get('auth.otp.channel', 'log') ?? 'log')) {
            'array' => new ArrayOtpChannel(),
            default => new LogOtpChannel(),
        };

        $otpThrottle = new LoginThrottle(
            $this->cache,
            (int) ($this->config?->get('auth.throttle.otp.max', 5) ?? 5),
            (int) ($this->config?->get('auth.throttle.otp.decay', 300) ?? 300),
            0,
            'auth:throttle:',
        );

        return $this->otpManager = new OtpManager(
            $this->cache,
            $this->hasher,
            $channel,
            $otpThrottle,
            (int) ($this->config?->get('auth.otp.length', 6) ?? 6),
            (int) ($this->config?->get('auth.otp.ttl', 120) ?? 120),
            $this->events,
        );
    }

    public function twoFactor(): TwoFactorManager
    {
        if ($this->twoFactorManager !== null) {
            return $this->twoFactorManager;
        }
        if ($this->db === null || $this->crypt === null) {
            throw new \LogicException('TwoFactorManager requires database + crypt engines.');
        }

        return $this->twoFactorManager = new TwoFactorManager(
            $this->db,
            $this->crypt,
            (string) ($this->config?->get('auth.two_factor.table', 'two_factor_secrets') ?? 'two_factor_secrets'),
            new TwoFactor\Totp(
                (int) ($this->config?->get('auth.two_factor.period', 30) ?? 30),
                (int) ($this->config?->get('auth.two_factor.digits', 6) ?? 6),
                (int) ($this->config?->get('auth.two_factor.window', 1) ?? 1),
            ),
            (int) ($this->config?->get('auth.two_factor.recovery_codes', 8) ?? 8),
        );
    }

    public function devices(): DeviceManager
    {
        if ($this->deviceManager !== null) {
            return $this->deviceManager;
        }
        if ($this->db === null) {
            throw new \LogicException('DeviceManager requires the database engine.');
        }

        return $this->deviceManager = new DeviceManager(
            $this->db,
            (string) ($this->config?->get('auth.devices.table', 'user_devices') ?? 'user_devices'),
        );
    }

    // ─── ثبت‌نام / بازیابی رمز ─────────────────────────────────────────────

    /**
     * ثبت‌نام کاربر جدید (رمز هش می‌شود).
     *
     * @param array<string, mixed> $data
     */
    public function register(array $data): Authenticatable
    {
        $provider = $this->provider();
        if (!$provider instanceof DatabaseUserProvider) {
            throw new \LogicException('register() requires a DatabaseUserProvider.');
        }

        $password = (string) ($data['password'] ?? '');
        if ($password === '' || $this->hasher === null) {
            throw new \InvalidArgumentException('register() requires a password and the hash engine.');
        }

        $data['password'] = $this->hasher->make($password);
        unset($data['password_confirmation']);

        return $provider->create($data);
    }

    public function sendPasswordResetLink(string $email): string
    {
        return $this->passwords()->sendResetLink($email);
    }

    /**
     * @param \Closure(Authenticatable, string): void|null $callback ذخیرهٔ رمز (پیش‌فرض: provider->rehash)
     */
    public function resetPassword(string $email, string $token, #[\SensitiveParameter] string $newPassword, ?\Closure $callback = null): string
    {
        $callback ??= function (Authenticatable $user, string $plain) use (&$saved): void {
            if ($this->db !== null && $this->hasher !== null) {
                $table = (string) ($this->config?->get('auth.providers.users.table', 'users') ?? 'users');
                $this->db->table($table)
                    ->where('id', $user->getAuthIdentifier())
                    ->update(['password' => $this->hasher->make($plain)]);
            }
            $saved = true;
        };

        return $this->passwords()->reset($email, $token, $newPassword, $callback);
    }

    public function passwords(): PasswordBroker
    {
        if ($this->passwordBroker !== null) {
            return $this->passwordBroker;
        }
        if ($this->db === null || $this->hasher === null) {
            throw new \LogicException('PasswordBroker requires database + hash engines.');
        }

        $throttle = new LoginThrottle(
            $this->cache ?? throw new \LogicException('PasswordBroker requires the cache engine.'),
            (int) ($this->config?->get('auth.throttle.reset.max', 3) ?? 3),
            (int) ($this->config?->get('auth.throttle.reset.decay', 3600) ?? 3600),
            0,
        );

        return $this->passwordBroker = new PasswordBroker(
            $this->db,
            $this->provider(),
            $this->hasher,
            (string) ($this->config?->get('auth.passwords.users.table', 'password_resets') ?? 'password_resets'),
            (int) ($this->config?->get('auth.passwords.users.expire', 3600) ?? 3600),
            $throttle,
            $this->events,
        );
    }

    private function loginThrottle(): LoginThrottle
    {
        if ($this->cache === null) {
            throw new \LogicException('SessionGuard throttling requires the cache engine.');
        }

        return new LoginThrottle(
            $this->cache,
            (int) ($this->config?->get('auth.throttle.login.max', 5) ?? 5),
            (int) ($this->config?->get('auth.throttle.login.decay', 60) ?? 60),
            (int) ($this->config?->get('auth.throttle.login.lock', 900) ?? 900),
        );
    }

    // ─── forwarding به guard پیش‌فرض ──────────────────────────────────────

    public function user(): ?Authenticatable
    {
        return $this->guard()->user();
    }

    public function id(): int|string|null
    {
        return $this->guard()->id();
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function guest(): bool
    {
        return $this->guard()->guest();
    }

    public function userOrFail(): Authenticatable
    {
        $guard = $this->guard();
        if ($guard instanceof SessionGuard) {
            return $guard->userOrFail();
        }

        $user = $guard->user();
        if ($user === null) {
            throw new \Tavaan\Core\Exceptions\Auth\AuthenticationException(guards: [$this->getDefaultGuard()]);
        }

        return $user;
    }

    /** @param array<string, mixed> $credentials */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $guard = $this->guard();
        if (!$guard instanceof StatefulGuard) {
            throw new \LogicException('attempt() requires a stateful guard.');
        }

        return $guard->attempt($credentials, $remember);
    }

    /** @param array<string, mixed> $credentials */
    public function once(array $credentials): bool
    {
        $guard = $this->guard();
        if (!$guard instanceof StatefulGuard) {
            throw new \LogicException('once() requires a stateful guard.');
        }

        return $guard->once($credentials);
    }

    /** @param array<string, mixed> $credentials */
    public function validate(array $credentials): bool
    {
        return $this->guard()->validate($credentials);
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $guard = $this->guard();
        if (!$guard instanceof StatefulGuard) {
            throw new \LogicException('login() requires a stateful guard.');
        }

        $guard->login($user, $remember);
    }

    public function loginUsingId(mixed $id, bool $remember = false): ?Authenticatable
    {
        $guard = $this->guard();
        if (!$guard instanceof StatefulGuard) {
            throw new \LogicException('loginUsingId() requires a stateful guard.');
        }

        return $guard->loginUsingId($id, $remember);
    }

    public function logout(): void
    {
        $guard = $this->guard();
        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->guard()->{$method}(...$arguments);
    }
}
