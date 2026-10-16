<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Drivers;

use Tavaan\Core\Contracts\Search\SearchDriverInterface;
use Tavaan\Engine\Search\Exceptions\SearchException;
use Tavaan\Engine\Search\SearchResult;

/**
 * تَوان | Tavaan — Search
 * TypesenseDriver — آداپتر HTTP سرویس Typesense (اختیاری).
 *
 * transport قابل تزریق برای تست (مانند MeilisearchDriver).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class TypesenseDriver implements SearchDriverInterface
{
    private string $host;

    private string $key;

    /**
     * @param array<string, mixed> $config کلید search.typesense
     * @param \Closure(string, string, ?array): array{status: int, body: array<mixed>}|null $transport
     */
    public function __construct(
        array $config = [],
        private readonly ?\Closure $transport = null,
        private readonly float $timeout = 5.0,
    ) {
        $this->host = \rtrim((string) ($config['host'] ?? 'http://127.0.0.1:8108'), '/');
        $this->key = (string) ($config['key'] ?? '');
        if ($this->host === '') {
            throw SearchException::driverUnavailable('typesense', 'host is not configured');
        }
    }

    public function createIndex(string $index): void
    {
        $this->request('POST', '/collections', [
            'name' => $index,
            'fields' => [['name' => '.*', 'type' => 'auto']],
        ]);
    }

    public function dropIndex(string $index): void
    {
        $this->request('DELETE', '/collections/' . $this->enc($index), null);
    }

    public function indexExists(string $index): bool
    {
        try {
            $res = $this->request('GET', '/collections/' . $this->enc($index), null);

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
            $this->request(
                'POST',
                '/collections/' . $this->enc($index) . '/documents?action=upsert',
                $documents,
            );
        }
    }

    public function delete(string $index, string $docId): void
    {
        $this->request('DELETE', '/collections/' . $this->enc($index) . '/documents/' . $this->enc($docId), null);
    }

    public function flush(string $index): void
    {
        $this->request('DELETE', '/collections/' . $this->enc($index) . '/documents', null);
    }

    public function count(string $index): int
    {
        $res = $this->request('GET', '/collections/' . $this->enc($index), null);

        return (int) ($res['body']['num_documents'] ?? 0);
    }

    public function search(string $index, string $query, array $options = []): SearchResult
    {
        $started = \hrtime(true);

        $page = \max(1, (int) ($options['page'] ?? 1));
        $perPage = \max(1, (int) ($options['per_page'] ?? 20));

        $params = [
            'q' => $query,
            'query_by' => \implode(',', $this->queryBy($index, (array) ($options['fields'] ?? []))),
            'page' => $page,
            'per_page' => $perPage,
        ];
        if (!empty($options['facets'])) {
            $params['facet_by'] = \implode(',', (array) $options['facets']);
        }
        if (!empty($options['filters'])) {
            $filter = $this->buildFilter((array) $options['filters']);
            if ($filter !== '') {
                $params['filter_by'] = $filter;
            }
        }
        if (!empty($options['sort'])) {
            $sorts = [];
            foreach ((array) $options['sort'] as $k => $v) {
                $sorts[] = \is_int($k) ? $v . ':asc' : $k . ':' . $v;
            }
            $params['sort_by'] = \implode(',', $sorts);
        }
        if (!empty($options['highlight'])) {
            $params['highlight_fields'] = \implode(',', (array) $options['highlight']);
            $params['highlight_start_tag'] = '<mark>';
            $params['highlight_end_tag'] = '</mark>';
        }

        $res = $this->request('GET', '/collections/' . $this->enc($index) . '/documents/search?' . \http_build_query($params), null);
        $payload = (array) $res['body'];

        $hits = [];
        foreach ((array) ($payload['hits'] ?? []) as $entry) {
            $entry = (array) $entry;
            $doc = (array) ($entry['document'] ?? []);
            $hit = [
                'id' => (string) ($doc['id'] ?? ''),
                'score' => isset($entry['text_match']) ? (float) $entry['text_match'] : 1.0,
                'document' => $doc,
            ];
            $highlight = [];
            foreach ((array) ($entry['highlights'] ?? []) as $hl) {
                $hl = (array) $hl;
                if (isset($hl['field'], $hl['snippet'])) {
                    $highlight[(string) $hl['field']] = (string) $hl['snippet'];
                }
            }
            if ($highlight !== []) {
                $hit['highlight'] = $highlight;
            }
            $hits[] = $hit;
        }

        $facets = [];
        foreach ((array) ($payload['facet_counts'] ?? []) as $facet) {
            $facet = (array) $facet;
            $field = (string) ($facet['field_name'] ?? '');
            $list = [];
            foreach ((array) ($facet['counts'] ?? []) as $count) {
                $count = (array) $count;
                $v = $count['value'] ?? null;
                $list[] = ['value' => \is_numeric($v) ? $v + 0 : (string) $v, 'count' => (int) ($count['count'] ?? 0)];
            }
            if ($field !== '') {
                $facets[$field] = $list;
            }
        }

        return new SearchResult(
            hits: $hits,
            total: (int) ($payload['found'] ?? \count($hits)),
            facets: $facets,
            suggestions: [],
            page: $page,
            perPage: $perPage,
            tookMs: (\hrtime(true) - $started) / 1e6,
        );
    }

    public function suggest(string $index, string $query, int $limit = 5): array
    {
        $res = $this->request(
            'GET',
            '/collections/' . $this->enc($index) . '/documents/search?'
                . \http_build_query(['q' => $query . '*', 'query_by' => '*', 'per_page' => \max(1, $limit)]),
            null,
        );
        $out = [];
        foreach ((array) ($res['body']['hits'] ?? []) as $entry) {
            $doc = (array) (((array) $entry)['document'] ?? []);
            if (isset($doc['id'])) {
                $out[] = (string) $doc['id'];
            }
        }

        return \array_slice($out, 0, \max(1, $limit));
    }

    /**
     * @param list<string> $requested
     *
     * @return list<string>
     */
    private function queryBy(string $index, array $requested): array
    {
        if ($requested !== []) {
            $out = [];
            foreach ($requested as $entry) {
                $entry = (string) $entry;
                $out[] = \str_contains($entry, '^') ? (string) \explode('^', $entry, 2)[0] : $entry;
            }

            return $out;
        }

        return ['*'];
    }

    /** @param array<string, mixed> $filters */
    private function buildFilter(array $filters): string
    {
        $parts = [];
        foreach ($filters as $field => $condition) {
            $field = (string) $field;
            if (\is_array($condition)) {
                foreach ($condition as $op => $operand) {
                    $symbol = match ((string) $op) {
                        'eq' => ':', 'ne' => ':!=', 'lt' => ':<', 'lte' => ':<=', 'gt' => ':>', 'gte' => ':>=',
                        default => null,
                    };
                    if ($symbol !== null) {
                        $parts[] = $field . $symbol . (\is_string($operand) ? '`' . \str_replace('`', '', $operand) . '`' : $operand);
                    } elseif ((string) $op === 'in' && \is_array($operand)) {
                        $parts[] = $field . ':[' . \implode(',', \array_map(
                            static fn (mixed $v): string => \is_string($v) ? '`' . \str_replace('`', '', $v) . '`' : (string) $v,
                            $operand,
                        )) . ']';
                    }
                }
            } else {
                $parts[] = $field . ':' . (\is_string($condition) ? '`' . \str_replace('`', '', $condition) . '`' : $condition);
            }
        }

        return \implode(' && ', $parts);
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

        $separator = \str_contains($path, '?') ? '&' : '?';
        $url = $this->host . $path . ($this->key !== '' ? $separator . 'X-TYPESENSE-API-KEY=' . \rawurlencode($this->key) : '');

        $context = \stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
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
            throw SearchException::driverUnavailable('typesense', 'HTTP request failed');
        }
        $decoded = \json_decode($raw, true);

        return ['status' => $status, 'body' => \is_array($decoded) ? $decoded : []];
    }

    private function enc(string $segment): string
    {
        return \rawurlencode($segment);
    }
}
