<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters\S3;

use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;

/**
 * تَوان | Tavaan — Filesystem
 * CurlS3Transport — ارسال HTTP با ext-curl (در صورت وجود).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class CurlS3Transport implements S3TransportInterface
{
    public function __construct(
        private readonly float $timeout = 15.0,
        private readonly bool $verifyTls = true,
    ) {
    }

    public static function available(): bool
    {
        return \function_exists('curl_init');
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $handle = \curl_init();
        if ($handle === false) {
            throw FilesystemException::transportError('curl_init failed');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        \curl_setopt_array($handle, [
            \CURLOPT_URL => $url,
            \CURLOPT_CUSTOMREQUEST => \strtoupper($method),
            \CURLOPT_HTTPHEADER => $headerLines,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLOPT_TIMEOUT => (int) \ceil($this->timeout),
            \CURLOPT_CONNECTTIMEOUT => (int) \max(1, \ceil($this->timeout / 3)),
            \CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            \CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        if ($body !== null) {
            \curl_setopt($handle, \CURLOPT_POSTFIELDS, $body);
        }

        $response = \curl_exec($handle);
        if ($response === false) {
            $error = \curl_error($handle);
            \curl_close($handle);

            throw FilesystemException::transportError($error);
        }

        $status = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
        $headerSize = (int) \curl_getinfo($handle, \CURLINFO_HEADER_SIZE);
        \curl_close($handle);

        $rawHeaders = \substr((string) $response, 0, $headerSize);
        $responseBody = \substr((string) $response, $headerSize);

        return ['status' => $status, 'headers' => self::parseHeaders($rawHeaders), 'body' => $responseBody];
    }

    /** @return array<string, string> */
    public static function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (\explode("\r\n", $raw) as $line) {
            if (\str_contains($line, ':')) {
                [$name, $value] = \explode(':', $line, 2);
                $headers[\strtolower(\trim($name))] = \trim($value);
            }
        }

        return $headers;
    }
}
