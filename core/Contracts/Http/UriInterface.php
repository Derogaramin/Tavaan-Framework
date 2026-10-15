<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

/**
 * قرارداد URI (RFC 3986) — داخلی، بدون psr/http-message.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
interface UriInterface extends \Stringable
{
    public function scheme(): string;

    public function host(): string;

    public function port(): ?int;

    public function path(): string;

    public function query(): string;

    public function fragment(): string;

    public function userInfo(): string;

    /** @param array<string, mixed> $query */
    public function withQuery(array|string $query): static;

    public function withPath(string $path): static;

    public function withHost(string $host): static;

    public function withScheme(string $scheme): static;
}
