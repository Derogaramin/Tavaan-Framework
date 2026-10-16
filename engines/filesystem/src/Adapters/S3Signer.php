<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters;

use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Filesystem
 * S3Signer — امضای AWS Signature Version 4، خودنوشته و بدون SDK (DOC-063 §6).
 *
 * هم برای Authorization header و هم presigned URL (temporaryUrl) استفاده می‌شود.
 * سازگار با S3-compatibleها (Minio/ArvanCloud) با endpoint دلخواه.
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class S3Signer
{
    public const ALGORITHM = 'AWS4-HMAC-SHA256';

    public const EMPTY_PAYLOAD_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public function __construct(
        private readonly string $accessKey,
        #[\SensitiveParameter] private readonly string $secretKey,
        private readonly string $region,
        private readonly string $service = 's3',
    ) {
    }

    /**
     * امضای هدرهای یک درخواست (Authorization header).
     *
     * @param array<string, string> $headers باید شامل `host` باشد
     * @param array<string, string> $query
     *
     * @return array<string, string> هدرهای امضاشده (Authorization/x-amz-date/x-amz-content-sha256 افزوده می‌شود)
     */
    public function signRequest(string $method, string $path, array $query, array $headers, string $payload, ?\DateTimeImmutable $now = null): array
    {
        $now ??= Clock::utcNow();
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');
        $payloadHash = \hash('sha256', $payload);

        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $payloadHash;

        $canonical = $this->canonicalRequest($method, $path, $query, $headers, $payloadHash);
        $scope = $this->scope($dateStamp);
        $stringToSign = self::ALGORITHM . "\n" . $amzDate . "\n" . $scope . "\n" . \hash('sha256', $canonical);

        $signature = \hash_hmac('sha256', $stringToSign, $this->deriveKey($dateStamp));

        $headers['Authorization'] = self::ALGORITHM
            . ' Credential=' . $this->accessKey . '/' . $scope
            . ', SignedHeaders=' . $this->signedHeadersList($headers)
            . ', Signature=' . $signature;

        return $headers;
    }

    /**
     * ساخت presigned URL (برای temporaryUrl).
     *
     * @param array<string, string> $extraQuery
     */
    public function presign(string $method, string $scheme, string $host, string $path, int $expiresSeconds, ?\DateTimeImmutable $now = null, array $extraQuery = []): string
    {
        $now ??= Clock::utcNow();
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');
        $scope = $this->scope($dateStamp);

        $query = \array_merge($extraQuery, [
            'X-Amz-Algorithm' => self::ALGORITHM,
            'X-Amz-Credential' => $this->accessKey . '/' . $scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ]);

        $canonical = $this->canonicalRequest($method, $path, $query, ['host' => $host], 'UNSIGNED-PAYLOAD');
        $stringToSign = self::ALGORITHM . "\n" . $amzDate . "\n" . $scope . "\n" . \hash('sha256', $canonical);
        $signature = \hash_hmac('sha256', $stringToSign, $this->deriveKey($dateStamp));

        $query['X-Amz-Signature'] = $signature;

        return $scheme . '://' . $host . $path . '?' . $this->canonicalQueryString($query);
    }

    // ─── اجزای SigV4 ──────────────────────────────────────────────────────

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     */
    public function canonicalRequest(string $method, string $path, array $query, array $headers, string $payloadHash): string
    {
        $lowerHeaders = [];
        foreach ($headers as $name => $value) {
            $lowerHeaders[\strtolower($name)] = \trim($value);
        }
        \ksort($lowerHeaders);

        $canonicalHeaders = '';
        foreach ($lowerHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }

        return \implode("\n", [
            \strtoupper($method),
            $this->canonicalPath($path),
            $this->canonicalQueryString($query),
            $canonicalHeaders,
            $this->signedHeadersList($lowerHeaders),
            $payloadHash,
        ]);
    }

    /** کلید امضا (DOC-063 §6: deriveKey). */
    public function deriveKey(string $dateStamp): string
    {
        $key = \hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $key = \hash_hmac('sha256', $this->region, $key, true);
        $key = \hash_hmac('sha256', $this->service, $key, true);

        return \hash_hmac('sha256', 'aws4_request', $key, true);
    }

    private function scope(string $dateStamp): string
    {
        return $dateStamp . '/' . $this->region . '/' . $this->service . '/aws4_request';
    }

    /** @param array<string, string> $headers */
    private function signedHeadersList(array $headers): string
    {
        $names = \array_map(\strtolower(...), \array_keys($headers));
        \sort($names);

        return \implode(';', $names);
    }

    /** S3 برخلاف RFC، `/` را در path encode نمی‌کند (double-encode ممنوع). */
    private function canonicalPath(string $path): string
    {
        $path = '/' . \trim($path, '/');
        $segments = \explode('/', $path);
        $encoded = \array_map(static fn (string $s): string => \rawurlencode($s), $segments);

        return \implode('/', $encoded);
    }

    /** @param array<string, string|int> $query */
    private function canonicalQueryString(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[\rawurlencode((string) $key)] = \rawurlencode((string) $value);
        }
        \ksort($pairs);

        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return \implode('&', $parts);
    }
}
