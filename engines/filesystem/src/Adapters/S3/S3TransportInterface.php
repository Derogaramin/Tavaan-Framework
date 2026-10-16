<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters\S3;

/**
 * تَوان | Tavaan — Filesystem
 * S3TransportInterface — لایهٔ ارسال HTTP برای S3 (قابل Mock در تست).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
interface S3TransportInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array;
}
