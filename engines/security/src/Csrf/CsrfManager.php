<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Csrf;

use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Engine\Session\Session;

/**
 * تَوان | Tavaan — Security
 * CsrfManager — توکن/فیلد/چرخش/بررسی CSRF (DOC-059 §2.1).
 *
 * 🔒 مقایسه با hash_equals (constant-time).
 * 🔒 Double-Submit Cookie برای APIهای stateless (کوکی رمزنگاری‌شده).
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class CsrfManager
{
    /** @var \Closure(): ?Session */
    private readonly \Closure $sessionResolver;

    public function __construct(
        ?\Closure $sessionResolver = null,
        private readonly ?EncrypterInterface $crypt = null,
    ) {
        $this->sessionResolver = $sessionResolver ?? static fn (): ?Session => null;
    }

    /** توکن فعلی (از session؛ در نبود session → توکن double-submit). */
    public function token(): string
    {
        $session = ($this->sessionResolver)();
        if ($session instanceof Session) {
            return $session->token();
        }

        return '';
    }

    /** `<input type="hidden" name="_token" …>` */
    public function field(): string
    {
        $token = \htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    /** چرخش توکن (پس از login — Session Guard هم regenerateToken می‌کند). */
    public function rotate(): string
    {
        $session = ($this->sessionResolver)();

        return $session instanceof Session ? $session->regenerateToken() : '';
    }

    /** بررسی توکن ارسالی در برابر session. */
    public function verify(string $token): bool
    {
        $sessionToken = $this->token();
        if ($sessionToken === '' || $token === '') {
            return false;
        }

        return \hash_equals($sessionToken, $token);
    }

    /**
     * Double-Submit: ساخت کوکی رمزنگاری‌شده برای API stateless.
     */
    public function doubleSubmitCookieValue(string $token): string
    {
        if ($this->crypt === null) {
            throw new \LogicException('Double-submit CSRF requires the crypt engine.');
        }

        return $this->crypt->encrypt($token, 'csrf-double-submit');
    }

    public function verifyDoubleSubmit(string $cookieValue, string $headerToken): bool
    {
        if ($this->crypt === null) {
            return false;
        }

        try {
            $decrypted = $this->crypt->decrypt($cookieValue, 'csrf-double-submit');
        } catch (\Throwable) {
            return false;   // کوکی دست‌کاری‌شده → بی‌صدا رد
        }

        return $decrypted !== '' && $headerToken !== '' && \hash_equals($decrypted, $headerToken);
    }
}
