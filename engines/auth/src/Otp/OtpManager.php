<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Otp;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Auth\Exceptions\ThrottledException;
use Tavaan\Engine\Auth\Throttle\LoginThrottle;

/**
 * تَوان | Tavaan — Auth
 * OtpManager — ارسال/تایید OTP یک‌بارمصرف (DOC-056 §7).
 *
 * 🔒 کد ۶ رقمی CSPRNG؛ فقط **هش** آن در cache (TTL ۲ دقیقه).
 * 🔒 یک‌بارمصرف: پس از verify موفق فوراً حذف می‌شود.
 * 🔒 Rate limit: ۵ ارسال در ۵ دقیقه.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class OtpManager
{
    public function __construct(
        private readonly RepositoryInterface $cache,
        private readonly HasherInterface $hasher,
        private readonly OtpChannelInterface $channel,
        private readonly ?LoginThrottle $throttle = null,
        private readonly int $length = 6,
        private readonly int $ttl = 120,
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    /**
     * ارسال OTP — نتیجه شامل هدف mask‌شده است (بدون نشت).
     *
     * @return array{masked: string, expires_in: int}
     */
    public function send(Authenticatable $user, string $channelName = 'sms'): array
    {
        $this->throttle?->assertAllowed('otp:' . $user->getAuthIdentifier());

        $code = $this->generateCode();

        // 🔒 هش سریع برای OTP (bcrypt cost 10 — نه argon سنگین برای verify مکرر)
        $hash = \password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]);
        $this->cache->put($this->key($user), $hash, $this->ttl);

        $sent = $this->channel->send($user, $code, $channelName);
        if (!$sent) {
            $this->cache->forget($this->key($user));

            throw new \RuntimeException('OTP delivery failed on channel: ' . $channelName);
        }

        $this->throttle?->hit('otp:' . $user->getAuthIdentifier());
        $this->events?->dispatch('auth.otp.sent', [
            'identifier' => (string) $user->getAuthIdentifier(),
            'channel' => $channelName,
        ]);

        return ['masked' => $this->maskTarget($user), 'expires_in' => $this->ttl];
    }

    /** تایید OTP (یک‌بارمصرف). */
    public function verify(Authenticatable $user, string $code): bool
    {
        $hash = $this->cache->get($this->key($user));
        if (!\is_string($hash) || $hash === '') {
            return false;   // منقضی یا ارسال‌نشده
        }

        if (\preg_match('/^\d{' . $this->length . '}$/', $code) !== 1) {
            return false;
        }

        $ok = \password_verify($code, $hash);
        if ($ok) {
            $this->cache->forget($this->key($user));   // 🔒 یک‌بارمصرف
        }

        return $ok;
    }

    public function isPending(Authenticatable $user): bool
    {
        return $this->cache->has($this->key($user));
    }

    private function generateCode(): string
    {
        $min = 10 ** ($this->length - 1);
        $max = 10 ** $this->length - 1;

        return (string) \random_int($min, $max);   // 🔒 CSPRNG
    }

    private function key(Authenticatable $user): string
    {
        return 'auth:otp:' . $user->getAuthIdentifier();
    }

    /** mask هدف (موبایل/ایمیل) برای پیام‌ها. */
    private function maskTarget(Authenticatable $user): string
    {
        $target = '';
        if ($user instanceof \Tavaan\Engine\Auth\GenericUser) {
            $target = (string) ($user->mobile ?? $user->email ?? '');
        } elseif (\method_exists($user, 'getAttribute')) {
            $target = (string) ($user->getAttribute('mobile') ?? $user->getAttribute('email') ?? '');
        }

        if (\str_contains($target, '@')) {
            [$local, $domain] = \explode('@', $target, 2);

            return \substr($local, 0, 2) . '***@' . $domain;
        }

        if (\strlen($target) >= 7) {
            return \substr($target, 0, 4) . '***' . \substr($target, -3);
        }

        return '***';
    }
}
