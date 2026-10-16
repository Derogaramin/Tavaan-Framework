<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth;

/**
 * تَوان | Tavaan — Auth
 * TokenPair — جفت توکن دسترسی/تازه‌سازی (value object).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final readonly class TokenPair
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenId,
        public string $family,
        public int $expiresIn,
        public array $scopes = ['*'],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'token_type'    => 'Bearer',
            'access_token'  => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in'    => $this->expiresIn,
            'jti'           => $this->tokenId,
        ];
    }
}
