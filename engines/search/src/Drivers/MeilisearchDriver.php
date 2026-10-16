<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Drivers;

use Tavaan\Core\Contracts\Search\SearchDriverInterface;
use Tavaan\Engine\Search\Exceptions\SearchException;
use Tavaan\Engine\Search\SearchResult;

/**
 * تَوان | Tavaan — Search
 * MeilisearchDriver — آداپتر HTTP سرویس Meilisearch (اختیاری).
 *
 * transport پیش‌فرض با stream context (بدون curl)؛ برای تست قابل تزریق:
 * `fn (string $method, string $path, ?array $body): array{status:int, body:array}`.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class MeilisearchDriver implements SearchDriverInterface
{
    private string $host;

    private string $key;

    /**
     * @param array<string, mixed>                         $config کلید search.meilisearch
     * @param \Closure(string, string, ?array): array{status: int, body: array<mixed>}|null $transport
     */
    public function __construct(
        array $config = [],
        private readonly ?\Closure $transport = null,
        private readonly float $timeout = 5.0,
    ) {
        $this->host = \rtrim((string) ($config['host'] ?? 'http://127.0.0.1:7700'), '/');
        $this->key = (string) ($config['key'] ?? '');
        if ($this->host === '') {
            throw SearchException::driverUnavailable('meilisearch', 'host is not configured');
        }
    }

    public function createIndex(string $index): void
    {
        $this->request('POST', '/indexes', ['uid' => $index, 'primaryKey' => 'id']);
    }

    public function dropIndex(string $index): void
    {
        $this->request('DELETE', '/indexes/' . $this->enc($index), null);
    }

    public function indexExists(string $index): bool
    {
        try {
            $res = $this->request('GET', '/indexes/' . $this->enc($index), null);

            return $res['status'] < 400;
        } catch (\Throwable) {
            return false;
        }
    }

    public function index(string $index, string $docId, array $data): void
    {
        $this->indexMany($index, [$docId => $data]);
    }

    public function indexMany(string $index, array $docs): void
    {
        $documents = [];
        foreach ($docs as $docId => $data) {
            $documents[] = ['id' => (string) $docId, ...(array) $data];
        }
        if ($documents !== []) {
            $this->request('POST', '/indexes/' . $this->enc($index) . '/documents', $documents);
        }
    }

    public function delete(string $index, string $docId): void
    {
        $this->request('DELETE', '/indexes/' . $this->enc($index) . '/documents/' . $this->enc($docId), null);
    }

    public function flush(string $index): void
    {
        $this->request('DELETE', '/indexes/' . $this->enc($index) . '/documents', null);
    }

    public function count(string $index): int
    {
        $res = $this->request('GET', '/indexes/' . $this->enc($index) . '/stats', null);

        return (int) ($res['body']['numberOfDocuments'] ?? 0);
    }

    public function search(string $index, string $query, array $options = []): SearchResult
    {
        $started = \hrtime(true);

        $page = \max(1, (int) ($options['page'] ?? 1));
        $perPage = \max(1, (int) ($options['per_page'] ?? 20));

        $body = [
            'q' => $query,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
        if (!empty($options['facets'])) {
            $body['facets'] = \array_values((array) $options['facets']);
        }
        if (!empty($options['filters'])) {
            $body['filter'] = $this->buildMeiliFilter((array) $options['filters']);
        }
        if (!empty($options['sort'])) {
            $sorts = [];
            foreach ((array) $options['sort'] as $k => $v) {
                $sorts[] = \is_int($k) ? $v . ':asc' : $k . ':' . $v;
            }
            $body['sort'] = $sorts;
        }
        if (!empty($options['highlight'])) {
            $body['attributesToHighlight'] = \array_values((array) $options['highlight']);
            $body['highlightPreTag'] = '<mark>';
            $body['highlightPostTag'] = '</mark>';
        }

        $res = $this->request('POST', '/indexes/' . $this->enc($index) . '/search', $body);
        $payload = (array) $res['body'];

        $hits = [];
        foreach ((array) ($payload['hits'] ?? []) as $raw) {
            $raw = (array) $raw;
            $formatted = (array) ($raw['_formatted'] ?? []);
            $document = $raw;
            unset($document['_formatted'], $document['_matchesPosition']);
            $hit = [
                'id' => (string) ($raw['id'] ?? ''),
                'score' => 1.0,   // Meili ترتیب را برمی‌گرداند نه score عددی
                'document' => $document,
            ];
            if ($formatted !== []) {
                $highlight = [];
                foreach ((array) ($options['highlight'] ?? []) as $hf) {
                    if (isset($formatted[(string) $hf])) {
                        $highlight[(string) $hf] = (string) $formatted[(string) $hf];
                    }
                }
                if ($highlight !== []) {
                    $hit['highlight'] = $highlight;
                }
            }
            $hits[] = $hit;
        }

        $facets = [];
        foreach ((array) ($payload['facetDistribution'] ?? []) as $field => $counts) {
            $list = [];
            foreach ((array) $counts as $value => $count) {
                $list[] = ['value' => \is_numeric($value) ? $value + 0 : (string) $value, 'count' => (int) $count];
            }
            $facets[(string) $field] = $list;
        }

        return new SearchResult(
            hits: $hits,
            total: (int) ($payload['estimatedTotalHits'] ?? \count($hits)),
            facets: $facets,
            suggestions: [],
            page: $page,
            perPage: $perPage,
            tookMs: (\hrtime(true) - $started) / 1e6,
        );
    }

    public function suggest(string $index, string $query, int $limit = 5): array
    {
        // Meili API اختصاصی suggest ندارد — از جست‌وجوی prefix استفاده می‌شود
        $res = $this->request('POST', '/indexes/' . $this->enc($index) . '/search', [
            'q' => $query,
            'limit' => \max(1, $limit),
            'attributesToRetrieve' => ['id'],
        ]);
        $out = [];
        foreach ((array) ($res['body']['hits'] ?? []) as $hit) {
            $out[] = (string) ((array) $hit)['id'];
        }

        return \array_slice($out, 0, \max(1, $limit));
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<mixed>
     */
    private function buildMeiliFilter(array $filters): array
    {
        $parts = [];
        foreach ($filters as $field => $condition) {
            $field = (string) $field;
            if (\is_array($condition)) {
                foreach ($condition as $op => $operand) {
                    $symbol = match ((string) $op) {
                        'eq' => '=', 'ne' => '!=', 'lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>=',
                        default => null,
                    };
                    if ($symbol !== null) {
                        $parts[] = $field . ' ' . $symbol . ' ' . (\is_string($operand) ? '"' . $operand . '"' : $operand);
                    } elseif ((string) $op === 'in' && \is_array($operand)) {
                        $parts[] = $field . ' IN [' . \implode(', ', \array_map(
                            static fn (mixed $v): string => \is_string($v) ? '"' . $v . '"' : (string) $v,
                            $operand,
                        )) . ']';
                    }
                }
            } else {
                $parts[] = $field . ' = ' . (\is_string($condition) ? '"' . $condition . '"' : $condition);
            }
        }

        return $parts;
    }

    /**
     * @param array<mixed>|null $body
     *
     * @return array{status: int, body: array<mixed>}
     */
    private function request(string $method, string $path, ?array $body): array
    {
        if ($this->transport !== null) {
            $res = ($this->transport)($method, $path, $body);

            return ['status' => (int) ($res['status'] ?? 0), 'body' => (array) ($res['body'] ?? [])];
        }

        $url = $this->host . $path;
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
        if ($this->key !== '') {
            $headers .= 'Authorization: Bearer ' . $this->key . "\r\n";
        }

        $context = \stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $body === null ? '' : (string) \json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @\file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (\preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        if ($raw === false) {
            throw SearchException::driverUnavailable('meilisearch', 'HTTP request to ' . $url . ' failed');
        }
        $decoded = \json_decode($raw, true);

        return ['status' => $status, 'body' => \is_array($decoded) ? $decoded : []];
    }

    private function enc(string $segment): string
    {
        return \rawurlencode($segment);
    }
}
