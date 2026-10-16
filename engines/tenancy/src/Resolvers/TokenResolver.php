<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Resolvers;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;

/**
 * تَوان | Tavaan — Tenancy
 * TokenResolver — تشخیص tenant از claim توکن (JWT).
 *
 * ترتیب منابع:
 * 1. attribute درخواست (`tenant_id`) که middleware احراز هویت ست کرده
 * 2. claim در payload توکن Bearer — ⚠️ فقط وقتی middleware احراز
 *    امضا پیش‌تر اجرا شده باشد (این resolver خودش اعتبارسنجی نمی‌کند؛
 *    در زنجیره پس از auth قرار می‌گیرد).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TokenResolver implements TenantResolverInterface
{
    public function __construct(
        private readonly string $claim = 'tenant_id',
        private readonly bool $decodeUnverified = false,
    ) {
    }

    public function resolve(RequestInterface $request): ?string
    {
        // ۱) attribute ست‌شده توسط auth
        if (\method_exists($request, 'attribute')) {
            $fromAttribute = $request->attribute($this->claim) ?? $request->attribute('tenant_id');
            if (\is_string($fromAttribute) && $fromAttribute !== '' && \is_numeric($fromAttribute)) {
                return $fromAttribute;
            }
            if (\is_int($fromAttribute)) {
                return (string) $fromAttribute;
            }
        }

        // ۲) payload توکن (اختیاری — فقط وقتی صریحاً فعال شده باشد)
        if (!$this->decodeUnverified || !\method_exists($request, 'bearerToken')) {
            return null;
        }
        $token = $request->bearerToken();
        if (!\is_string($token) || $token === '') {
            return null;
        }

        $parts = \explode('.', $token);
        if (\count($parts) !== 3) {
            return null;
        }
        $payload = \json_decode($this->base64UrlDecode($parts[1]), true);
        if (!\is_array($payload)) {
            return null;
        }

        // 🔒 exp گذشته → نادیده (کمین minimum)
        if (isset($payload['exp']) && \is_numeric($payload['exp']) && (int) $payload['exp'] < \time()) {
            return null;
        }

        $value = $payload[$this->claim] ?? null;
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_string($value) && \preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = \strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= \str_repeat('=', 4 - $remainder);
        }

        return (string) \base64_decode(\strtr($data, '-_', '+/'), true);
    }
}
