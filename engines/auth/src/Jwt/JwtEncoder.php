<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Jwt;

use Tavaan\Engine\Auth\Exceptions\TokenExpiredException;
use Tavaan\Engine\Auth\Exceptions\TokenInvalidException;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Auth
 * JwtEncoder — JWT خودنوشته با **Algorithm Pinning** (DOC-056 §6).
 *
 * 🔒 الگوریتم از config پین می‌شود؛ هرگز از header پذیرفته نمی‌شود.
 * 🔒 `alg=none` همیشه رد می‌شود.
 * 🔒 Key Confusion: کلید HMAC هرگز از کلید RSA عمومی ساخته نمی‌شود
 *    (RS256 فقط با openssl verify؛ HS* فقط با secret).
 * 🔒 exp/nbf/iat/iss/aud/jti الزامی و بررسی می‌شوند (leeway قابل تنظیم).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class JwtEncoder
{
    /** الگوریتم‌های مجاز (allowlist) */
    private const ALLOWED = ['HS256', 'HS384', 'HS512', 'RS256', 'RS384', 'RS512', 'EdDSA'];

    /**
     * @param array<string, mixed> $config jwt config (algorithm/ttl/issuer/audience/leeway/keys)
     * @param callable(string): bool|null $revocationCheck بررسی jti (blacklist)
     */
    public function __construct(
        private readonly string $secret,
        private readonly array $config = [],
        private $revocationCheck = null,
    ) {
        $this->assertAllowedAlgorithm($this->algorithm());
    }

    public function algorithm(): string
    {
        return (string) ($this->config['algorithm'] ?? 'HS256');
    }

    public function ttl(): int
    {
        return (int) ($this->config['ttl'] ?? 3600);
    }

    public function refreshTtl(): int
    {
        return (int) ($this->config['refresh_ttl'] ?? 1209600);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $overrides iss/aud/exp/ttl و …
     */
    public function encode(array $payload, array $overrides = []): string
    {
        $algorithm = $this->algorithm();
        $this->assertAllowedAlgorithm($algorithm);

        $now = Clock::timestamp();
        $ttl = (int) ($overrides['ttl'] ?? $this->ttl());

        $header = ['typ' => 'JWT', 'alg' => $algorithm, 'kid' => (string) ($overrides['kid'] ?? 'v1')];

        $claims = $payload + [
            'iss' => (string) ($overrides['iss'] ?? $this->config['issuer'] ?? ''),
            'aud' => (string) ($overrides['aud'] ?? $this->config['audience'] ?? ''),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => Ulid::generate()->toString(),
        ];
        // overrides صریح بر payload اولویت دارند
        foreach (['iss', 'aud', 'iat', 'nbf', 'exp', 'jti'] as $claim) {
            if (isset($overrides[$claim])) {
                $claims[$claim] = $overrides[$claim];
            }
        }

        $segment = Base64Url::encode($this->json($header)) . '.' . Base64Url::encode($this->json($claims));

        return $segment . '.' . Base64Url::encode($this->sign($segment, $algorithm));
    }

    /**
     * رمزگشایی + اعتبارسنجی کامل.
     *
     * @return array<string, mixed> claims
     *
     * @throws TokenInvalidException|TokenExpiredException
     */
    public function decode(string $jwt, bool $verifyClaims = true): array
    {
        $parts = \explode('.', $jwt);
        if (\count($parts) !== 3) {
            throw new TokenInvalidException('auth.token.malformed');
        }

        [$h, $p, $s] = $parts;

        $header = $this->jsonDecode(Base64Url::decode($h));
        $alg = (string) ($header['alg'] ?? 'none');

        // 🔒 alg=none همیشه رد
        if (\strtolower($alg) === 'none' || $alg === '') {
            throw new TokenInvalidException('auth.token.algorithm_none');
        }

        // 🔒 الگوریتم پین‌شده — از header نپذیر (Algorithm Confusion)
        if ($alg !== $this->algorithm()) {
            throw new TokenInvalidException('auth.token.algorithm_mismatch');
        }
        $this->assertAllowedAlgorithm($alg);

        if (!$this->verify($h . '.' . $p, Base64Url::decode($s), $alg)) {
            throw new TokenInvalidException('auth.token.signature_invalid');
        }

        $payload = $this->jsonDecode(Base64Url::decode($p));

        if (!$verifyClaims) {
            return $payload;
        }

        $this->assertClaims($payload);

        return $payload;
    }

    /**
     * فقط اعتبارسنجی claimها (برای refresh توکن منقضی‌شدهٔ access).
     *
     * @param array<string, mixed> $payload
     */
    public function assertClaims(array $payload, bool $allowExpired = false): void
    {
        $now = Clock::timestamp();
        $leeway = (int) ($this->config['leeway'] ?? 30);

        $exp = (int) ($payload['exp'] ?? 0);
        if (!$allowExpired && $exp !== 0 && $exp + $leeway < $now) {
            throw new TokenExpiredException();
        }

        $nbf = (int) ($payload['nbf'] ?? 0);
        if ($nbf !== 0 && $nbf - $leeway > $now) {
            throw new TokenInvalidException('auth.token.not_yet_valid');
        }

        $issuer = (string) ($this->config['issuer'] ?? '');
        if ($issuer !== '' && (string) ($payload['iss'] ?? '') !== $issuer) {
            throw new TokenInvalidException('auth.token.issuer_mismatch');
        }

        $audience = (string) ($this->config['audience'] ?? '');
        if ($audience !== '' && (string) ($payload['aud'] ?? '') !== $audience) {
            throw new TokenInvalidException('auth.token.audience_mismatch');
        }

        $jti = $payload['jti'] ?? null;
        if ($jti === null || !\is_string($jti) || $jti === '') {
            throw new TokenInvalidException('auth.token.missing_jti');
        }

        if ($this->revocationCheck !== null && ($this->revocationCheck)($jti)) {
            throw new TokenInvalidException('auth.token.revoked');
        }
    }

    public function setRevocationCheck(?callable $check): void
    {
        $this->revocationCheck = $check === null ? null : \Closure::fromCallable($check);
    }

    // ─── امضا ─────────────────────────────────────────────────────────────

    private function sign(string $data, string $algorithm): string
    {
        if (\str_starts_with($algorithm, 'HS')) {
            $algo = match ($algorithm) {
                'HS256' => 'sha256',
                'HS384' => 'sha384',
                'HS512' => 'sha512',
                default => throw new TokenInvalidException('auth.token.algorithm_unsupported'),
            };

            return \hash_hmac($algo, $data, $this->secret, true);
        }

        if (\str_starts_with($algorithm, 'RS')) {
            $key = $this->privateKey();

            return $this->opensslSign($data, $key, $algorithm);
        }

        if ($algorithm === 'EdDSA') {
            $key = $this->eddsaPrivateKey();

            return \sodium_crypto_sign_detached($data, $key);
        }

        throw new TokenInvalidException('auth.token.algorithm_unsupported');
    }

    private function verify(string $data, string $signature, string $algorithm): bool
    {
        if (\str_starts_with($algorithm, 'HS')) {
            $expected = $this->sign($data, $algorithm);

            return \hash_equals($expected, $signature);
        }

        if (\str_starts_with($algorithm, 'RS')) {
            $publicKey = $this->publicKey();
            if ($publicKey === null) {
                return false;
            }

            $digest = match ($algorithm) {
                'RS256' => OPENSSL_ALGO_SHA256,
                'RS384' => OPENSSL_ALGO_SHA384,
                'RS512' => OPENSSL_ALGO_SHA512,
                default => throw new TokenInvalidException('auth.token.algorithm_unsupported'),
            };

            // 🔒 openssl_verify با کلید عمومی — هرگز secret به‌عنوان کلید RSA استفاده نمی‌شود
            return \openssl_verify($data, $signature, $publicKey, $digest) === 1;
        }

        if ($algorithm === 'EdDSA') {
            $publicKey = $this->eddsaPublicKey();
            if ($publicKey === null) {
                return false;
            }

            try {
                return \sodium_crypto_sign_verify_detached($signature, $data, $publicKey);
            } catch (\SodiumException) {
                return false;
            }
        }

        return false;
    }

    private function assertAllowedAlgorithm(string $algorithm): void
    {
        if (!\in_array($algorithm, self::ALLOWED, true)) {
            throw new TokenInvalidException('auth.token.algorithm_unsupported', ['alg' => $algorithm]);
        }
    }

    // ─── کلیدها ───────────────────────────────────────────────────────────

    /** @return \OpenSSLAsymmetricKey|resource */
    private function privateKey(): mixed
    {
        $path = (string) ($this->config['keys']['private_key'] ?? '');
        if ($path === '' || !\is_file($path)) {
            throw new TokenInvalidException('auth.token.key_missing', [], 'مسیر کلید خصوصی RS تنظیم نشده است.');
        }

        $key = \openssl_pkey_get_private(
            (string) \file_get_contents($path),
            (string) ($this->config['keys']['passphrase'] ?? ''),
        );
        if ($key === false) {
            throw new TokenInvalidException('auth.token.key_invalid');
        }

        return $key;
    }

    /** @return \OpenSSLAsymmetricKey|resource|null */
    private function publicKey(): mixed
    {
        $path = (string) ($this->config['keys']['public_key'] ?? '');
        if ($path === '' || !\is_file($path)) {
            return null;
        }

        return \openssl_pkey_get_public((string) \file_get_contents($path)) ?: null;
    }

    private function eddsaPrivateKey(): string
    {
        $path = (string) ($this->config['keys']['private_key'] ?? '');
        if ($path === '' || !\is_file($path)) {
            throw new TokenInvalidException('auth.token.key_missing');
        }

        $key = (string) \file_get_contents($path);
        if (\strlen($key) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return $key;
        }

        throw new TokenInvalidException('auth.token.key_invalid', [], 'کلید EdDSA باید ۶۴ بایت خام باشد.');
    }

    private function eddsaPublicKey(): ?string
    {
        $path = (string) ($this->config['keys']['public_key'] ?? '');
        if ($path === '' || !\is_file($path)) {
            return null;
        }

        $key = (string) \file_get_contents($path);

        return \strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $key : null;
    }

    /**
     * امضای RSA (بدون کلید خصوصی PEM → از secret کلید ساخته نمی‌شود).
     *
     * @param \OpenSSLAsymmetricKey|resource $key
     */
    private function opensslSign(string $data, mixed $key, string $algorithm): string
    {
        $digest = match ($algorithm) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => throw new TokenInvalidException('auth.token.algorithm_unsupported'),
        };

        $signature = '';
        if (\openssl_sign($data, $signature, $key, $digest) !== true) {
            throw new TokenInvalidException('auth.token.sign_failed');
        }

        return $signature;
    }

    // ─── JSON ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function jsonDecode(string $json): array
    {
        try {
            $decoded = \json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TokenInvalidException('auth.token.malformed');
        }

        if (!\is_array($decoded)) {
            throw new TokenInvalidException('auth.token.malformed');
        }

        return $decoded;
    }
}
