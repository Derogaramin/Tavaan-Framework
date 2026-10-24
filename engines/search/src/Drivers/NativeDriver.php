<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Drivers;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Search\SearchDriverInterface;
use Tavaan\Engine\Search\Exceptions\SearchException;
use Tavaan\Engine\Search\Highlighter;
use Tavaan\Engine\Search\Persian;
use Tavaan\Engine\Search\SearchResult;

/**
 * تَوان | Tavaan — Search
 * NativeDriver — ایندکس معکوس خودنوشته با BM25 (بدون DB، فارسی‌آگاه).
 *
 * ساختار (به ازای هر ایندکس):
 * - docs:    docId → دادهٔ کامل سند
 * - inv:     field → term → [docId → freq]
 * - flen:    field → [docId → تعداد توکن]
 * - display: term → فرم خام (برای suggest)
 *
 * پایستگی: JSON اتمیک در storage/search/{index}.json (اختیاری).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class NativeDriver implements SearchDriverInterface
{
    /** @var array<string, array<string, mixed>> دادهٔ در حافظه به ازای هر ایندکس */
    private array $loaded = [];

    /** @var array<string, bool> */
    private array $dirty = [];

    /**
     * @param array<string, mixed> $config کلید search در config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    // ─── ساختار ایندکس ────────────────────────────────────────────────────

    public function createIndex(string $index): void
    {
        $this->validateName($index);
        if (!isset($this->loaded[$index])) {
            $this->loaded[$index] = ['docs' => [], 'inv' => [], 'flen' => [], 'display' => []];
        }
        $this->persist($index);
    }

    public function dropIndex(string $index): void
    {
        $this->validateName($index);
        unset($this->loaded[$index], $this->dirty[$index]);
        $file = $this->filePath($index);
        if ($file !== null && \is_file($file)) {
            @\unlink($file);
        }
    }

    public function indexExists(string $index): bool
    {
        $this->ensureLoaded($index);

        return isset($this->loaded[$index]);
    }

    public function flush(string $index): void
    {
        $this->ensureLoaded($index);
        if (!isset($this->loaded[$index])) {
            return;
        }
        $this->loaded[$index] = ['docs' => [], 'inv' => [], 'flen' => [], 'display' => []];
        $this->persist($index);
    }

    public function count(string $index): int
    {
        $this->ensureLoaded($index);

        return \count($this->loaded[$index]['docs'] ?? []);
    }

    // ─── ایندکس‌گذاری ────────────────────────────────────────────────────

    public function index(string $index, string $docId, array $data): void
    {
        $this->ensureLoaded($index);
        $this->loaded[$index] ??= ['docs' => [], 'inv' => [], 'flen' => [], 'display' => []];

        $this->removeDoc($index, $docId);

        $store = &$this->loaded[$index];
        $store['docs'][$docId] = $data;

        $minLength = (int) ($this->config['min_token_length'] ?? 2);
        foreach ($this->searchableFields($index, $data) as $field => $boost) {
            $text = $this->stringify($data[$field] ?? '');
            if ($text === '') {
                continue;
            }
            $len = 0;
            foreach (Persian::tokenizeDetailed($text, $minLength) as [$raw, $term]) {
                $len++;
                $store['inv'][$field][$term][$docId] = ($store['inv'][$field][$term][$docId] ?? 0) + 1;
                $store['display'][$term] ??= $raw;
            }
            $store['flen'][$field][$docId] = $len;
        }
        unset($store);

        $this->persist($index);
    }

    public function indexMany(string $index, array $docs): void
    {
        foreach ($docs as $docId => $data) {
            $this->indexNoPersist($index, (string) $docId, (array) $data);
        }
        $this->persist($index);
    }

    public function delete(string $index, string $docId): void
    {
        $this->ensureLoaded($index);
        if (!isset($this->loaded[$index]['docs'][$docId])) {
            return;
        }
        $this->removeDoc($index, $docId);
        $this->persist($index);
    }

    // ─── جست‌وجو ─────────────────────────────────────────────────────────

    public function search(string $index, string $query, array $options = []): SearchResult
    {
        $started = \hrtime(true);
        $this->ensureLoaded($index);

        $maxLen = (int) ($this->config['max_query_length'] ?? 500);
        if (\mb_strlen($query) > $maxLen) {
            throw SearchException::queryTooLong($maxLen);
        }

        $store = $this->loaded[$index] ?? ['docs' => [], 'inv' => [], 'flen' => [], 'display' => []];
        $docs = (array) $store['docs'];
        $inv = (array) $store['inv'];

        $minLength = (int) ($this->config['min_token_length'] ?? 2);
        $maxTokens = (int) ($this->config['max_tokens'] ?? 64);
        $tokens = \array_slice(Persian::tokenize($query, $minLength), 0, $maxTokens);

        // فیلترها → مجموعهٔ مجاز
        $allowed = $this->applyFilters($index, $docs, (array) ($options['filters'] ?? []));

        // بوست فیلدها از options یا config
        $fields = $this->resolveQueryFields($index, (array) ($options['fields'] ?? []), $docs);

        // BM25 (DOC-052 §4)
        $totalDocs = \count($docs);
        $scores = [];
        foreach ($fields as $field => $boost) {
            foreach ($tokens as $token) {
                $postings = $inv[$field][$token] ?? null;
                if ($postings === null || $postings === []) {
                    continue;
                }
                $df = \count($postings);
                $idf = \log(($totalDocs + 1) / ($df + 0.5));
                foreach ($postings as $docId => $freq) {
                    if (!isset($allowed[(string) $docId])) {
                        continue;
                    }
                    $scores[(string) $docId] = ($scores[(string) $docId] ?? 0.0)
                        + $boost * $idf * (($freq * 1.2) / ($freq + 1.2 * 0.75));
                }
            }
        }

        // بدون توکن قابل‌تطبیق اما کوئری خالی/ایست‌واژه → نتیجهٔ خالی (نه همهٔ اسناد)
        if ($tokens === [] && \trim($query) !== '') {
            $scores = [];
        }

        // مرتب‌سازی
        $matched = $this->sortMatched($index, $scores, $docs, (array) ($options['sort'] ?? []));
        $total = \count($matched);

        // Facetها روی کل مجموعهٔ منطبق (پیش از صفحه‌بندی)
        $facets = $this->computeFacets($index, $matched, $docs, (array) ($options['facets'] ?? []));

        // صفحه‌بندی
        $page = \max(1, (int) ($options['page'] ?? 1));
        $perPage = \max(1, \min((int) ($options['per_page'] ?? 20), (int) ($this->config['max_per_page'] ?? 100)));
        $slice = \array_slice($matched, ($page - 1) * $perPage, $perPage);

        // Highlight (escape-then-mark)
        $highlightFields = (array) ($options['highlight'] ?? []);
        $hits = [];
        foreach ($slice as [$docId, $score]) {
            $hit = [
                'id' => (string) $docId,
                'score' => \round((float) $score, 6),
                'document' => $docs[(string) $docId] ?? [],
            ];
            if ($highlightFields !== [] && $tokens !== []) {
                $marked = [];
                foreach ($highlightFields as $hf) {
                    $hf = (string) $hf;
                    $raw = $this->stringify($docs[(string) $docId][$hf] ?? '');
                    if ($raw !== '') {
                        $marked[$hf] = Highlighter::highlight($raw, $tokens);
                    }
                }
                if ($marked !== []) {
                    $hit['highlight'] = $marked;
                }
            }
            $hits[] = $hit;
        }

        // «آیا منظور شما…» وقتی نتیجه‌ای نیست
        $suggestions = [];
        if ($total === 0 && $tokens !== []) {
            $suggestions = $this->suggest($index, $query, 3);
        }

        return new SearchResult(
            hits: $hits,
            total: $total,
            facets: $facets,
            suggestions: $suggestions,
            page: $page,
            perPage: $perPage,
            tookMs: (\hrtime(true) - $started) / 1e6,
        );
    }

    public function suggest(string $index, string $query, int $limit = 5): array
    {
        $this->ensureLoaded($index);
        $store = $this->loaded[$index] ?? null;
        if ($store === null) {
            return [];
        }

        $minLength = (int) ($this->config['min_token_length'] ?? 2);
        $queryTokens = Persian::tokenize($query, $minLength);
        if ($queryTokens === []) {
            $normalized = \mb_strtolower(Persian::normalize($query));
            $queryTokens = $normalized === '' ? [] : [$normalized];
        }

        // df تجمعی هر term در همهٔ فیلدها
        $df = [];
        foreach ((array) $store['inv'] as $fieldInv) {
            foreach ((array) $fieldInv as $term => $postings) {
                $df[(string) $term] = ($df[(string) $term] ?? 0) + \count((array) $postings);
            }
        }

        $candidates = [];
        foreach ($queryTokens as $qt) {
            foreach (\array_keys($df) as $term) {
                // 🔒 کلیدهای عددی (termهای رقمی) پس از json round-trip int
                // می‌شوند — cast به string برای توابع رشته‌ای (M9/forum).
                $term = (string) $term;
                $score = 0;
                if (\str_starts_with($term, $qt)) {
                    $score = 1000 - \mb_strlen($term);        // prefix: قوی‌تر
                } elseif (\str_contains($term, $qt) && \mb_strlen($qt) >= 3) {
                    $score = 500;
                } elseif (\mb_strlen($qt) >= 4 && Persian::mbLevenshtein($qt, $term) <= 1) {
                    $score = 250;
                }
                if ($score > 0) {
                    $candidates[$term] = ($candidates[$term] ?? 0) + $score + (int) ($df[$term] ?? 0);
                }
            }
        }

        \arsort($candidates);
        $display = (array) ($store['display'] ?? []);
        $out = [];
        foreach (\array_slice(\array_keys($candidates), 0, \max(1, $limit)) as $term) {
            $out[] = (string) ($display[$term] ?? $term);
        }

        return $out;
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    private function indexNoPersist(string $index, string $docId, array $data): void
    {
        $this->ensureLoaded($index);
        $this->loaded[$index] ??= ['docs' => [], 'inv' => [], 'flen' => [], 'display' => []];
        $this->removeDoc($index, $docId);

        $store = &$this->loaded[$index];
        $store['docs'][$docId] = $data;

        $minLength = (int) ($this->config['min_token_length'] ?? 2);
        foreach ($this->searchableFields($index, $data) as $field => $boost) {
            $text = $this->stringify($data[$field] ?? '');
            if ($text === '') {
                continue;
            }
            $len = 0;
            foreach (Persian::tokenizeDetailed($text, $minLength) as [$raw, $term]) {
                $len++;
                $store['inv'][$field][$term][$docId] = ($store['inv'][$field][$term][$docId] ?? 0) + 1;
                $store['display'][$term] ??= $raw;
            }
            $store['flen'][$field][$docId] = $len;
        }
        unset($store);
    }

    private function removeDoc(string $index, string $docId): void
    {
        $store = &$this->loaded[$index];
        unset($store['docs'][$docId]);
        foreach ($store['inv'] as $field => $terms) {
            foreach ($terms as $term => $postings) {
                if (isset($postings[$docId])) {
                    unset($this->loaded[$index]['inv'][$field][$term][$docId]);
                    if ($this->loaded[$index]['inv'][$field][$term] === []) {
                        unset($this->loaded[$index]['inv'][$field][$term]);
                    }
                }
            }
        }
        foreach ($store['flen'] as $field => $lens) {
            unset($this->loaded[$index]['flen'][$field][$docId]);
        }
        unset($store);
    }

    /**
     * فیلدهای قابل‌جست‌وجو با بوست — از config ایندکس یا options.
     *
     * @param array<string, mixed> $data نمونهٔ سند (برای کشف فیلدها وقتی config نیست)
     *
     * @return array<string, float> field → boost
     */
    private function searchableFields(string $index, array $data): array
    {
        $indexConfig = $this->indexConfig($index);
        $searchable = (array) ($indexConfig['searchable'] ?? []);
        if ($searchable === []) {
            $searchable = (array) ($indexConfig['fields'] ?? []);
        }
        if ($searchable === []) {
            // بدون config: همهٔ فیلدهای رشته‌ای سند
            $searchable = [];
            foreach ($data as $k => $v) {
                if (\is_string($v) || \is_numeric($v)) {
                    $searchable[] = (string) $k;
                }
            }
        }

        return $this->parseFieldsWithBoost($searchable);
    }

    /**
     * `'title^3'` → ['title' => 3.0]
     *
     * @param list<string> $fields
     *
     * @return array<string, float>
     */
    private function parseFieldsWithBoost(array $fields): array
    {
        $out = [];
        foreach ($fields as $entry) {
            $entry = \trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if (\str_contains($entry, '^')) {
                [$name, $boost] = \explode('^', $entry, 2);
                $boost = (float) $boost;
                $out[\trim($name)] = $boost > 0 ? $boost : 1.0;
            } else {
                $out[$entry] = 1.0;
            }
        }

        return $out;
    }

    /**
     * فیلدهای کوئری (options.fields) با اعتبارسنجی امنیتی:
     * فقط فیلدهای searchable ایندکس (اگر config داشته باشد).
     *
     * @param list<string>         $requested
     * @param array<string, mixed> $docs
     *
     * @return array<string, float>
     */
    private function resolveQueryFields(string $index, array $requested, array $docs): array
    {
        $all = $this->searchableFields($index, $docs === [] ? [] : (array) \reset($docs));
        if ($requested === []) {
            return $all;
        }

        $parsed = $this->parseFieldsWithBoost($requested);
        $out = [];
        foreach ($parsed as $field => $boost) {
            if (isset($all[$field])) {
                // بوست options در بوست config ضرب می‌شود
                $out[$field] = $boost * $all[$field];
            }
            // 🔒 فیلد ناشناخته بی‌سروصدا نادیده گرفته می‌شود (نشت داده نداشته باشیم)
        }

        return $out === [] ? $all : $out;
    }

    /**
     * @param array<string, mixed>         $docs
     * @param array<string, mixed>         $filters
     *
     * @return array<string, true> docId → true
     */
    private function applyFilters(string $index, array $docs, array $filters): array
    {
        $allowed = [];
        foreach (\array_keys($docs) as $docId) {
            $allowed[(string) $docId] = true;
        }
        if ($filters === []) {
            return $allowed;
        }

        $indexConfig = $this->indexConfig($index);
        $filterable = (array) ($indexConfig['filterable'] ?? []);

        foreach ($filters as $field => $condition) {
            $field = (string) $field;
            // 🔒 فقط فیلدهای filterable (اگر config محدود کرده باشد)
            if ($filterable !== [] && !\in_array($field, $filterable, true)) {
                throw SearchException::fieldNotAllowed($field, $index);
            }

            foreach ($allowed as $docId => $_) {
                $value = $docs[(string) $docId][$field] ?? null;
                if (!$this->matchCondition($value, $condition)) {
                    unset($allowed[(string) $docId]);
                }
            }
        }

        return $allowed;
    }

    private function matchCondition(mixed $value, mixed $condition): bool
    {
        if (\is_array($condition)) {
            foreach ($condition as $op => $operand) {
                $ok = match ((string) $op) {
                    'eq' => $this->looseEquals($value, $operand),
                    'ne' => !$this->looseEquals($value, $operand),
                    'lt' => $this->numeric($value) !== null && $this->numeric($value) < (float) $operand,
                    'lte' => $this->numeric($value) !== null && $this->numeric($value) <= (float) $operand,
                    'gt' => $this->numeric($value) !== null && $this->numeric($value) > (float) $operand,
                    'gte' => $this->numeric($value) !== null && $this->numeric($value) >= (float) $operand,
                    'in' => \is_array($operand) && $this->inList($value, $operand),
                    'nin' => \is_array($operand) && !$this->inList($value, $operand),
                    default => false,
                };
                if (!$ok) {
                    return false;
                }
            }

            return true;
        }

        return $this->looseEquals($value, $condition);
    }

    private function looseEquals(mixed $a, mixed $b): bool
    {
        $na = $this->numeric($a);
        $nb = $this->numeric($b);
        if ($na !== null && $nb !== null) {
            return $na === $nb;
        }

        return $a === null && $b === null
            ? true
            : (string) ($a ?? '') === (string) ($b ?? '');
    }

    private function inList(mixed $value, array $list): bool
    {
        foreach ($list as $item) {
            if ($this->looseEquals($value, $item)) {
                return true;
            }
        }

        return false;
    }

    private function numeric(mixed $v): ?float
    {
        if (\is_int($v) || \is_float($v)) {
            return (float) $v;
        }
        if (\is_string($v) && $v !== '' && \is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }

    /**
     * @param array<string, float>   $scores
     * @param array<string, mixed>   $docs
     * @param array<string, string>|list<string> $sort
     *
     * @return list<array{0: string, 1: float}>
     */
    private function sortMatched(string $index, array $scores, array $docs, array $sort): array
    {
        $matched = [];
        foreach ($scores as $docId => $score) {
            if ($score > 0.0) {
                $matched[] = [(string) $docId, (float) $score];
            }
        }

        if ($sort === []) {
            \usort($matched, static fn (array $a, array $b): int => $b[1] <=> $a[1] ?: \strcmp($a[0], $b[0]));

            return $matched;
        }

        $indexConfig = $this->indexConfig($index);
        $sortable = (array) ($indexConfig['sortable'] ?? []);

        // نرمال‌سازی به [field => dir]
        $normalized = [];
        foreach ($sort as $key => $dir) {
            if (\is_int($key)) {
                $normalized[(string) $dir] = 'asc';
            } else {
                $normalized[(string) $key] = \mb_strtolower((string) $dir) === 'desc' ? 'desc' : 'asc';
            }
        }
        foreach (\array_keys($normalized) as $field) {
            if ($sortable !== [] && !\in_array($field, $sortable, true) && $field !== 'score') {
                throw SearchException::fieldNotAllowed($field, $index);
            }
        }

        \usort($matched, static function (array $a, array $b) use ($normalized, $docs): int {
            foreach ($normalized as $field => $dir) {
                if ($field === 'score') {
                    $cmp = $a[1] <=> $b[1];
                } else {
                    $va = $docs[$a[0]][$field] ?? null;
                    $vb = $docs[$b[0]][$field] ?? null;
                    // nullها همیشه آخر
                    if ($va === null && $vb === null) {
                        $cmp = 0;
                    } elseif ($va === null) {
                        return 1;
                    } elseif ($vb === null) {
                        return -1;
                    } elseif (\is_numeric($va) && \is_numeric($vb)) {
                        $cmp = (float) $va <=> (float) $vb;
                    } else {
                        $cmp = \strcmp((string) $va, (string) $vb);
                    }
                }
                if ($cmp !== 0) {
                    return $dir === 'desc' ? -$cmp : $cmp;
                }
            }

            return $b[1] <=> $a[1] ?: \strcmp($a[0], $b[0]);
        });

        return $matched;
    }

    /**
     * @param list<array{0: string, 1: float}> $matched
     * @param array<string, mixed>             $docs
     * @param list<string>                     $facetFields
     *
     * @return array<string, list<array{value: mixed, count: int}>>
     */
    private function computeFacets(string $index, array $matched, array $docs, array $facetFields): array
    {
        if ($facetFields === []) {
            return [];
        }

        $indexConfig = $this->indexConfig($index);
        $filterable = (array) ($indexConfig['filterable'] ?? []);
        $maxFacets = (int) ($this->config['max_facets'] ?? 50);

        $facets = [];
        foreach ($facetFields as $field) {
            $field = (string) $field;
            // 🔒 نشت داده: فقط فیلدهای filterable
            if ($filterable !== [] && !\in_array($field, $filterable, true)) {
                continue;
            }
            $counts = [];
            foreach ($matched as [$docId]) {
                $value = $docs[$docId][$field] ?? null;
                if ($value === null || \is_array($value) || \is_object($value)) {
                    continue;
                }
                $key = \is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            \arsort($counts);
            $list = [];
            foreach (\array_slice($counts, 0, $maxFacets, true) as $value => $count) {
                $list[] = ['value' => \is_numeric($value) ? $value + 0 : (string) $value, 'count' => (int) $count];
            }
            $facets[$field] = $list;
        }

        return $facets;
    }

    /** @return array<string, mixed> */
    private function indexConfig(string $index): array
    {
        $indexes = (array) ($this->config['indexes'] ?? []);

        return (array) ($indexes[$index] ?? []);
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_array($value)) {
            $parts = [];
            foreach ($value as $v) {
                if (\is_scalar($v)) {
                    $parts[] = (string) $v;
                }
            }

            return \implode(' ', $parts);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    private function validateName(string $index): void
    {
        if (\preg_match('/^[A-Za-z0-9_-]{1,64}$/', $index) !== 1) {
            throw SearchException::invalidIndexName($index);
        }
    }

    // ─── پایستگی ──────────────────────────────────────────────────────────

    private function filePath(string $index): ?string
    {
        if (!\is_string($index) || $index === '') {
            return null;
        }
        if (!($this->config['native']['persist'] ?? true)) {
            return null;
        }
        $dir = (string) ($this->config['native']['path'] ?? 'storage/search');

        return \rtrim($dir, '/') . '/' . $index . '.json';
    }

    private function ensureLoaded(string $index): void
    {
        $this->validateName($index);
        if (isset($this->loaded[$index])) {
            return;
        }

        $file = $this->filePath($index);
        if ($file !== null && \is_file($file)) {
            $raw = @\file_get_contents($file);
            if (\is_string($raw) && $raw !== '') {
                try {
                    $decoded = \json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $decoded = null;
                }
                if (\is_array($decoded) && ($decoded['v'] ?? null) === 1) {
                    $this->loaded[$index] = [
                        'docs' => (array) ($decoded['docs'] ?? []),
                        'inv' => (array) ($decoded['inv'] ?? []),
                        'flen' => (array) ($decoded['flen'] ?? []),
                        'display' => (array) ($decoded['display'] ?? []),
                    ];

                    return;
                }
            }
        }

        // فایل موجود نبود — ایندکس «وجود ندارد» مگر createIndex شده باشد.
        // برای سادگی API، ساختار خالی در حافظه می‌سازیم اما روی دیسک نمی‌بریم
        // تا indexExists برای ایندکس‌های هرگز ساخته‌نشده false بماند.
        if ($file !== null) {
            return;
        }
        $this->loaded[$index] = ['docs' => [], 'inv' => [], 'flen' => [], 'display' => []];
    }

    private function persist(string $index): void
    {
        $file = $this->filePath($index);
        if ($file === null || !isset($this->loaded[$index])) {
            return;
        }

        $dir = \dirname($file);
        if (!\is_dir($dir) && !@\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            throw SearchException::persistFailed($file, 'cannot create directory');
        }

        $payload = \json_encode(
            ['v' => 1, ...(array) $this->loaded[$index]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        if ($payload === false) {
            throw SearchException::persistFailed($file, 'json encode failed');
        }

        // 🔒 نوشتن اتمیک: tmp + rename
        $tmp = $file . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (@\file_put_contents($tmp, $payload, LOCK_EX) === false) {
            throw SearchException::persistFailed($file, 'write failed');
        }
        if (!@\rename($tmp, $file)) {
            @\unlink($tmp);
            throw SearchException::persistFailed($file, 'rename failed');
        }
    }
}
