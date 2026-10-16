<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters\S3;

use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;

/**
 * تَوان | Tavaan — Filesystem
 * StreamS3Transport — ارسال HTTP با stream wrapper (بدون curl؛ صفر‌وابستگی).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class StreamS3Transport implements S3TransportInterface
{
    public function __construct(
        private readonly float $timeout = 15.0,
        private readonly bool $verifyTls = true,
    ) {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $headerLines = '';
        foreach ($headers as $name => $value) {
            $headerLines .= $name . ': ' . $value . "\r\n";
        }

        $context = \stream_context_create([
            'http' => [
                'method' => \strtoupper($method),
                'header' => $headerLines,
                'content' => $body ?? '',
                'timeout' => $this->timeout,
                'ignore_errors' => true,   // 🔒 بدنهٔ پاسخ 4xx/5xx هم خوانده شود (XML خطای S3)
                'follow_location' => 0,
            ],
            'ssl' => [
                'verify_peer' => $this->verifyTls,
                'verify_peer_name' => $this->verifyTls,
            ],
        ]);

        $responseBody = @\file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw FilesystemException::transportError('stream request failed: ' . $url);
        }

        $status = 0;
        $parsedHeaders = [];
        foreach ($http_response_header ?? [] as $line) {
            if (\preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                continue;
            }
            if (\str_contains($line, ':')) {
                [$name, $value] = \explode(':', $line, 2);
                $parsedHeaders[\strtolower(\trim($name))] = \trim($value);
            }
        }

        return ['status' => $status, 'headers' => $parsedHeaders, 'body' => $responseBody];
    }
}
