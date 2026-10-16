<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Tests\Fixtures;

use Tavaan\Engine\Filesystem\Adapters\S3\S3TransportInterface;

/**
 * Fixture: S3 درون‌حافظه‌ای برای تست درایور بدون سرور واقعی.
 *
 * پاسخ‌ها شکل واقعی S3 دارند (XML با namespace، status codes،
 * ListBucketResult با Contents/CommonPrefixes/IsTruncated).
 */
final class FakeS3Transport implements S3TransportInterface
{
    /** @var array<string, string> key → content */
    private array $objects = [];

    /** @var array<string, string> key → visibility */
    private array $acl = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    public function __construct(private readonly string $bucket = 'test-bucket')
    {
    }

    /** @param array<string, string> $headers */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        $parts = \parse_url($url);
        $path = (string) ($parts['path'] ?? '/');
        $query = [];
        \parse_str((string) ($parts['query'] ?? ''), $query);

        // path-style: /bucket/key → key
        $key = $this->keyFromPath($path);

        if (isset($query['list-type'])) {
            return $this->listResponse((string) ($query['prefix'] ?? ''), $query);
        }

        if (\array_key_exists('acl', $query)) {
            return $this->aclResponse($method, $key, $headers);
        }

        return match ($method) {
            'PUT' => $this->putResponse($key, $headers, (string) $body),
            'GET' => $this->getResponse($key),
            'HEAD' => $this->headResponse($key),
            'DELETE' => $this->deleteResponse($key),
            default => ['status' => 405, 'headers' => [], 'body' => ''],
        };
    }

    /** @return list<string> کلیدهای ذخیره‌شده (برای assert) */
    public function keys(): array
    {
        $keys = \array_keys($this->objects);
        \sort($keys);

        return $keys;
    }

    // ─── پاسخ‌ها ──────────────────────────────────────────────────────────

    /** @param array<string, string> $headers */
    private function putResponse(string $key, array $headers, string $body): array
    {
        if (isset($headers['x-amz-copy-source'])) {
            $source = \rawurldecode(\ltrim((string) $headers['x-amz-copy-source'], '/'));
            $prefix = $this->bucket . '/';
            if (\str_starts_with($source, $prefix)) {
                $source = \substr($source, \strlen($prefix));
            }
            if (!isset($this->objects[$source])) {
                return ['status' => 404, 'headers' => [], 'body' => $this->errorXml('NoSuchKey')];
            }
            $this->objects[$key] = $this->objects[$source];

            return ['status' => 200, 'headers' => [], 'body' => '<CopyObjectResult/>'];
        }

        $this->objects[$key] = $body;
        if (isset($headers['x-amz-acl'])) {
            $this->acl[$key] = (string) $headers['x-amz-acl'];
        }

        return ['status' => 200, 'headers' => [], 'body' => ''];
    }

    private function getResponse(string $key): array
    {
        if (!isset($this->objects[$key])) {
            return ['status' => 404, 'headers' => [], 'body' => $this->errorXml('NoSuchKey')];
        }

        return [
            'status' => 200,
            'headers' => ['content-type' => 'application/octet-stream', 'content-length' => (string) \strlen($this->objects[$key])],
            'body' => $this->objects[$key],
        ];
    }

    private function headResponse(string $key): array
    {
        if (!isset($this->objects[$key])) {
            return ['status' => 404, 'headers' => [], 'body' => ''];
        }

        return [
            'status' => 200,
            'headers' => [
                'content-length' => (string) \strlen($this->objects[$key]),
                'last-modified' => \gmdate('D, d M Y H:i:s', \time()) . ' GMT',
                'content-type' => 'application/octet-stream',
            ],
            'body' => '',
        ];
    }

    private function deleteResponse(string $key): array
    {
        unset($this->objects[$key], $this->acl[$key]);

        return ['status' => 204, 'headers' => [], 'body' => ''];
    }

    /** @param array<string, string> $headers */
    private function aclResponse(string $method, string $key, array $headers): array
    {
        if ($method === 'PUT') {
            $this->acl[$key] = (string) ($headers['x-amz-acl'] ?? 'private');

            return ['status' => 200, 'headers' => [], 'body' => ''];
        }

        $acl = $this->acl[$key] ?? 'private';
        $grant = $acl === 'public-read'
            ? '<Grant><Grantee xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="Group"><URI>http://acs.amazonaws.com/groups/global/AllUsers</URI></Grantee><Permission>READ</Permission></Grant>'
            : '';

        return [
            'status' => isset($this->objects[$key]) ? 200 : 404,
            'headers' => [],
            'body' => '<AccessControlPolicy><Owner><ID>owner</ID></Owner><AccessControlList>' . $grant . '</AccessControlList></AccessControlPolicy>',
        ];
    }

    /** @param array<string, mixed> $query */
    private function listResponse(string $prefix, array $query): array
    {
        $delimiter = isset($query['delimiter']) ? (string) $query['delimiter'] : '';
        $keys = \array_keys($this->objects);
        \sort($keys);

        $contents = '';
        $commonPrefixes = [];
        $count = 0;

        foreach ($keys as $key) {
            if ($prefix !== '' && !\str_starts_with($key, $prefix)) {
                continue;
            }
            $relative = \substr($key, \strlen($prefix));

            if ($delimiter !== '' && \str_contains($relative, $delimiter)) {
                [$first] = \explode($delimiter, $relative, 2);
                $commonPrefixes[$prefix . $first . $delimiter] = true;
                continue;
            }

            $count++;
            $contents .= '<Contents><Key>' . \htmlspecialchars($key, \ENT_XML1) . '</Key><Size>' . \strlen($this->objects[$key]) . '</Size></Contents>';
        }

        $prefixesXml = '';
        foreach (\array_keys($commonPrefixes) as $commonPrefix) {
            $prefixesXml .= '<CommonPrefixes><Prefix>' . \htmlspecialchars((string) $commonPrefix, \ENT_XML1) . '</Prefix></CommonPrefixes>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/">'
            . '<Name>' . $this->bucket . '</Name>'
            . '<Prefix>' . \htmlspecialchars($prefix, \ENT_XML1) . '</Prefix>'
            . '<KeyCount>' . $count . '</KeyCount>'
            . '<MaxKeys>1000</MaxKeys>'
            . '<IsTruncated>false</IsTruncated>'
            . $contents
            . $prefixesXml
            . '</ListBucketResult>';

        return ['status' => 200, 'headers' => ['content-type' => 'application/xml'], 'body' => $xml];
    }

    private function keyFromPath(string $path): string
    {
        $key = \rawurldecode($path);
        $prefix = '/' . $this->bucket . '/';
        if (\str_starts_with($key, $prefix)) {
            return \substr($key, \strlen($prefix));
        }

        return \ltrim($key, '/');
    }

    private function errorXml(string $code): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Error><Code>' . $code . '</Code><Message>' . $code . '</Message></Error>';
    }
}
