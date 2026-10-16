<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters;

use Tavaan\Engine\Filesystem\Adapters\S3\CurlS3Transport;
use Tavaan\Engine\Filesystem\Adapters\S3\S3TransportInterface;
use Tavaan\Engine\Filesystem\Adapters\S3\StreamS3Transport;
use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;

/**
 * تَوان | Tavaan — Filesystem
 * S3Filesystem — درایور S3-compatible (AWS/Minio/ArvanCloud) با signing
 * خودنوشته و **بدون SDK** (DOC-063 §6).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class S3Filesystem extends AbstractFilesystem
{
    private readonly string $bucket;

    private readonly string $region;

    private readonly string $endpoint;

    private readonly bool $pathStyle;

    private readonly S3Signer $signer;

    private readonly S3TransportInterface $transport;

    private readonly string $defaultVisibility;

    /**
     * @param array<string, mixed> $config {key, secret, region, bucket, endpoint, use_path_style, visibility, timeout, verify_tls}
     */
    public function __construct(
        private readonly string $diskName,
        array $config = [],
        ?S3TransportInterface $transport = null,
    ) {
        $this->bucket = (string) ($config['bucket'] ?? '');
        $this->region = (string) ($config['region'] ?? 'us-east-1');
        $this->pathStyle = (bool) ($config['use_path_style'] ?? false);
        $this->defaultVisibility = (string) ($config['visibility'] ?? 'private');

        $endpoint = (string) ($config['endpoint'] ?? '');
        $this->endpoint = $endpoint !== ''
            ? \rtrim($endpoint, '/')
            : 'https://s3.' . $this->region . '.amazonaws.com';

        $this->signer = new S3Signer(
            (string) ($config['key'] ?? ''),
            (string) ($config['secret'] ?? ''),
            $this->region,
        );

        $timeout = (float) ($config['timeout'] ?? 15.0);
        $verifyTls = (bool) ($config['verify_tls'] ?? true);
        $this->transport = $transport
            ?? (CurlS3Transport::available()
                ? new CurlS3Transport($timeout, $verifyTls)
                : new StreamS3Transport($timeout, $verifyTls));
    }

    public function diskName(): string
    {
        return $this->diskName;
    }

    public function signer(): S3Signer
    {
        return $this->signer;
    }

    // ─── عملیات ───────────────────────────────────────────────────────────

    public function get(string $path): ?string
    {
        $response = $this->send('GET', $path);

        if ($response['status'] === 404) {
            return null;
        }
        $this->assertSuccess($response, 'get');

        return $response['body'];
    }

    /** @return resource|null */
    public function readStream(string $path)
    {
        $content = $this->get($path);
        if ($content === null) {
            return null;
        }

        $stream = \fopen('php://temp', 'w+b');
        if ($stream === false) {
            return null;
        }
        \fwrite($stream, $content);
        \rewind($stream);

        return $stream;
    }

    public function put(string $path, string $content): bool
    {
        $headers = ['Content-Type' => self::mimeFromExtension($path), 'Content-Length' => (string) \strlen($content)];
        if ($this->defaultVisibility === 'public') {
            $headers['x-amz-acl'] = 'public-read';
        }

        $response = $this->send('PUT', $path, $headers, $content);
        $this->assertSuccess($response, 'put');

        return true;
    }

    /** @param resource $stream */
    public function writeStream(string $path, $stream): bool
    {
        if (!\is_resource($stream)) {
            throw FilesystemException::operationFailed('writeStream', $path, 'resource نامعتبر');
        }

        $content = '';
        while (!\feof($stream)) {
            $chunk = \fread($stream, 8192);
            if ($chunk === false) {
                break;
            }
            $content .= $chunk;
        }

        return $this->put($path, $content);
    }

    public function exists(string $path): bool
    {
        return $this->head($path) !== null;
    }

    public function size(string $path): int
    {
        $head = $this->headOrFail($path);

        return (int) ($head['content-length'] ?? 0);
    }

    public function lastModified(string $path): int
    {
        $head = $this->headOrFail($path);
        $modified = (string) ($head['last-modified'] ?? '');

        return $modified === '' ? 0 : (\strtotime($modified) ?: 0);
    }

    public function mimeType(string $path): string
    {
        $head = $this->headOrFail($path);

        return (string) ($head['content-type'] ?? self::mimeFromExtension($path));
    }

    /** @param string|list<string> $paths */
    public function delete(string|array $paths): bool
    {
        foreach ((array) $paths as $path) {
            $response = $this->send('DELETE', $path);
            if ($response['status'] >= 300 && $response['status'] !== 404) {
                $this->assertSuccess($response, 'delete');
            }
        }

        return true;
    }

    public function copy(string $from, string $to): bool
    {
        $source = '/' . $this->bucket . '/' . $this->encodeKey($from);
        $response = $this->send('PUT', $to, ['x-amz-copy-source' => $source], '');
        $this->assertSuccess($response, 'copy');

        return true;
    }

    public function move(string $from, string $to): bool
    {
        $this->copy($from, $to);

        return $this->delete($from);
    }

    public function append(string $path, string $content): bool
    {
        return $this->put($path, ($this->get($path) ?? '') . $content);
    }

    public function prepend(string $path, string $content): bool
    {
        return $this->put($path, $content . ($this->get($path) ?? ''));
    }

    /** @return list<string> */
    public function files(string $directory = ''): array
    {
        return $this->list($directory, recursive: false, wantFiles: true);
    }

    /** @return list<string> */
    public function allFiles(string $directory = ''): array
    {
        return $this->list($directory, recursive: true, wantFiles: true);
    }

    /** @return list<string> */
    public function directories(string $directory = ''): array
    {
        return $this->list($directory, recursive: false, wantFiles: false);
    }

    /** @return list<string> */
    public function allDirectories(string $directory = ''): array
    {
        return $this->list($directory, recursive: true, wantFiles: false);
    }

    public function makeDirectory(string $directory): bool
    {
        // 🔒 نشانگر پوشه در S3 = کلید با `/` پایانی — key() ممیز را trim می‌کند
        // پس کلید به‌شکل خام (rawKey) ارسال می‌شود.
        $marker = \rtrim($this->key($directory), '/') . '/';
        $response = $this->send('PUT', $marker, [], '', [], true);
        $this->assertSuccess($response, 'makeDirectory');

        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        foreach ($this->allFiles($directory) as $file) {
            $this->delete($file);
        }

        return true;
    }

    public function visibility(string $path): string
    {
        // ساده‌سازی: ACL عمومی → public
        $response = $this->send('GET', $this->key($path), [], null, ['acl' => '']);
        if ($response['status'] !== 200) {
            return $this->defaultVisibility;
        }

        return \str_contains($response['body'], 'AllUsers') ? 'public' : 'private';
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        $acl = $visibility === 'public' ? 'public-read' : 'private';
        $response = $this->send('PUT', $this->key($path), ['x-amz-acl' => $acl], '', ['acl' => '']);
        $this->assertSuccess($response, 'setVisibility');

        return true;
    }

    public function url(string $path): string
    {
        [$scheme, $host] = $this->schemeHost();

        return $scheme . '://' . $host . $this->resourcePath($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        [$scheme, $host] = $this->schemeHost();
        $seconds = \max(1, $expiration->getTimestamp() - \time());

        return $this->signer->presign('GET', $scheme, $host, $this->resourcePath($path), $seconds);
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * ارسال درخواست امضاشده.
     *
     * @param array<string, string> $headers
     * @param array<string, string> $query
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function send(string $method, string $pathOrKey, array $headers = [], ?string $body = null, array $query = [], bool $rawKey = false): array
    {
        $key = $rawKey ? $pathOrKey : $this->key($pathOrKey);

        [$scheme, $host] = $this->schemeHost();
        $resourcePath = $this->pathStyle
            ? '/' . $this->bucket . '/' . $this->encodeKey($key)
            : '/' . $this->encodeKey($key);

        $signedHeaders = $this->signer->signRequest(
            $method,
            $resourcePath,
            $query,
            \array_merge(['host' => $host], $headers),
            (string) $body,
        );

        $url = $scheme . '://' . $host . $resourcePath . ($query !== [] ? '?' . $this->buildQuery($query) : '');

        $sendHeaders = [];
        foreach ($signedHeaders as $name => $value) {
            if (\strtolower($name) !== 'host') {
                $sendHeaders[$name] = $value;
            }
        }

        return $this->transport->request($method, $url, $sendHeaders, $body);
    }

    /** @return array{0: string, 1: string} [scheme, host] */
    private function schemeHost(): array
    {
        $parts = \parse_url($this->endpoint);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? 's3.amazonaws.com');
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        if (!$this->pathStyle) {
            $host = $this->bucket . '.' . $host;
        }

        return [$scheme, $host];
    }

    private function resourcePath(string $path): string
    {
        $key = $this->key($path);

        return $this->pathStyle
            ? '/' . $this->bucket . '/' . $this->encodeKey($key)
            : '/' . $this->encodeKey($key);
    }

    private function key(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $path = (string) (\preg_replace('#/+#', '/', $path) ?? $path);

        return \trim($path, '/');
    }

    /** هر segment جداگانه encode شود — `/` سالم می‌ماند. */
    private function encodeKey(string $key): string
    {
        return \implode('/', \array_map(\rawurlencode(...), \explode('/', $key)));
    }

    /** @param array<string, string> $query */
    private function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $name => $value) {
            $pairs[] = \rawurlencode($name) . ($value !== '' ? '=' . \rawurlencode($value) : '=');
        }

        return \implode('&', $pairs);
    }

    /** @return array<string, string>|null */
    private function head(string $path): ?array
    {
        $response = $this->send('HEAD', $path);
        if ($response['status'] === 404) {
            return null;
        }
        $this->assertSuccess($response, 'head');

        return $response['headers'];
    }

    /** @return array<string, string> */
    private function headOrFail(string $path): array
    {
        $headers = $this->head($path);
        if ($headers === null) {
            $this->fileNotFound($path);
        }

        return $headers;
    }

    /** @param array{status: int, body: string} $response */
    private function assertSuccess(array $response, string $operation): void
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }

        throw FilesystemException::s3Error($response['status'], $response['body'], $operation);
    }

    /** @return list<string> */
    private function list(string $directory, bool $recursive, bool $wantFiles): array
    {
        $prefix = $this->key($directory);
        $prefix = $prefix === '' ? '' : $prefix . '/';

        $out = [];
        $token = null;

        do {
            $query = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
            if (!$recursive) {
                $query['delimiter'] = '/';
            }
            if ($token !== null) {
                $query['continuation-token'] = $token;
            }

            $response = $this->send('GET', '', [], null, $query);
            $this->assertSuccess($response, 'list');
            $body = $response['body'];

            if ($wantFiles) {
                foreach ($this->extractTag($body, 'Key') as $key) {
                    if ($key !== $prefix && !\str_ends_with($key, '/')) {
                        $out[] = $key;
                    }
                }
            } elseif (!$recursive) {
                // CommonPrefixes = زیرپوشه‌های مستقیم (مسیر کامل نسبت‌به‌ریشه — هم‌قرارداد با local)
                foreach ($this->extractCommonPrefixes($body) as $commonPrefix) {
                    if ($commonPrefix === $prefix || $commonPrefix === '') {
                        continue;
                    }
                    $out[] = \rtrim($commonPrefix, '/');
                }
            } else {
                // 🔒 recursive بدون delimiter: پوشه‌ها از کلیدها مشتق می‌شوند
                foreach ($this->extractTag($body, 'Key') as $key) {
                    if (!\str_starts_with($key, $prefix) || $key === $prefix) {
                        continue;
                    }
                    $segments = \explode('/', \substr($key, \strlen($prefix)));
                    \array_pop($segments);      // نام فایل
                    $accumulated = $prefix;
                    foreach ($segments as $segment) {
                        $accumulated .= $segment . '/';
                        $out[] = \rtrim($accumulated, '/');
                    }
                }
            }

            $isTruncated = \str_contains($body, '<IsTruncated>true</IsTruncated>');
            $next = $isTruncated ? $this->extractTag($body, 'NextContinuationToken') : [];
            $token = $next[0] ?? null;
        } while ($token !== null);

        $out = \array_values(\array_unique($out));
        \sort($out);

        return $out;
    }

    /**
     * استخراج تگ از XML پاسخ S3 — regex روی XML ماشین‌ساز
     * (بدون وابستگی به SimpleXML/namespace registration).
     *
     * @return list<string>
     */
    private function extractTag(string $body, string $tag): array
    {
        \preg_match_all('#<' . $tag . '>([^<]*)</' . $tag . '>#', $body, $matches);

        return \array_map(
            static fn (string $v): string => \htmlspecialchars_decode($v, \ENT_QUOTES | \ENT_XML1),
            $matches[1] ?? [],
        );
    }

    /** @return list<string> */
    private function extractCommonPrefixes(string $body): array
    {
        \preg_match_all('#<CommonPrefixes>\s*<Prefix>([^<]*)</Prefix>\s*</CommonPrefixes>#', $body, $matches);

        return \array_map(
            static fn (string $v): string => \htmlspecialchars_decode($v, \ENT_QUOTES | \ENT_XML1),
            $matches[1] ?? [],
        );
    }
}
