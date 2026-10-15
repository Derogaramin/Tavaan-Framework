# DOC-052 — موتور Search | جست‌وجو

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-052 |
| Engine ID | `tavaan.engine.search` |
| Namespace | `Tavaan\Engine\Search` |
| اولویت | **P1** — فاز M6 |
| وابستگی | `database`, `event` |
| Facade | `Search` |
| LOC | ۱۵۰۰ |

## ۱. هدف
جست‌وجوی Full-Text با درایورهای بومی (MySQL/PostgreSQL/SQLite FTS5) + یک ایندکس
معکوس خودنوشته (برای جست‌وجوی فارسی و بدون DB)، با Facet، Suggest و Highlight.

## ۲. درایورها
| درایور | موتور | توضیح |
|---|---|---|
| `mysql` | `MATCH … AGAINST` (FULLTEXT + ngram) | پیش‌فرض MySQL |
| `pgsql` | `to_tsvector`/`tsquery` | PostgreSQL |
| `sqlite` | FTS5 | SQLite |
| `native` | ایندکس معکوس خودنوشته | بدون DB، مناسب فارسی |
| `meilisearch` | HTTP adapter (اختیاری) | سرویس خارجی |
| `typesense` | HTTP adapter (اختیاری) | سرویس خارجی |

## ۳. API
```php
// ایندکس
Search::index('products', $product);
Search::delete('products', $product->id);
Search::indexMany('products', $products);

// جست‌وجو
$results = Search::search('products', 'لپ تاپ ایسوس', [
    'fields' => ['title^3','summary','description'],   // boost
    'filters' => ['category_id'=>5, 'price'=>['lte'=>50000000]],
    'facets' => ['category_id','brand'],
    'sort' => ['price'=>'asc'],
    'page' => 1, 'per_page' => 20,
    'highlight' => ['title','summary'],
]);
$results->hits();          // نتایج
$results->total();
$results->facets();        // ['category_id'=>[['value'=>5,'count'=>12], …]]
$results->suggestions();   // «آیا منظور شما…»

// Suggest/Autocomplete
Search::suggest('products', 'لپ', limit: 5);
```

## ۴. Native Inverted Index (خودنوشته)
```php
final class NativeIndex
{
    /** @var array<string, array<string, int>> term → [docId → freq] */
    private array $index = [];

    public function addDocument(string $docId, string $text, float $boost = 1.0): void
    {
        $tokens = $this->tokenize($text);       // نرمال‌سازی فارسی + تفکیک
        foreach ($tokens as $token) {
            $this->index[$token][$docId] = ($this->index[$token][$docId] ?? 0) + 1;
        }
    }

    /** @return list<array{docId:string, score:float}> */
    public function search(string $query, int $limit = 20): array
    {
        $tokens = $this->tokenize($query);
        $scores = [];
        $totalDocs = \count($this->documents);

        foreach ($tokens as $token) {
            if (!isset($this->index[$token])) { continue; }
            $df = \count($this->index[$token]);
            $idf = \log(($totalDocs + 1) / ($df + 0.5));      // BM25 idf
            foreach ($this->index[$token] as $docId => $freq) {
                $scores[$docId] = ($scores[$docId] ?? 0) + $idf * (($freq * 1.2) / ($freq + 1.2 * 0.75));
            }
        }
        \arsort($scores);
        return \array_slice(
            \array_map(fn ($id, $s) => ['docId'=>$id, 'score'=>$s], \array_keys($scores), $scores),
            0, $limit,
        );
    }

    /** نرمال‌سازی و تفکیک واژهٔ فارسی */
    private function tokenize(string $text): array
    {
        $text = Persian::normalize($text);       // ی→ي، ک→ك، حذف نیم‌فاصله/اعراب
        $text = \mb_strtolower($text);
        $text = \preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $tokens = \preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // حذف stop words + stemming ساده
        return \array_values(\array_filter(\array_map($this->stem(...), $tokens),
            fn ($t) => $t !== '' && !\in_array($t, self::STOP_WORDS, true)));
    }
}
```
🔒 **نرمال‌سازی فارسی حیاتی است:** یکسان‌سازی `ي/ی`, `ك/ک`, حذف اعراب، نیم‌فاصله (`‌` → فاصله یا حذف).

### ۴٫۱ Stemming فارسی (ساده)
```php
private function stem(string $word): string
{
    // حذف پسوندهای رایج
    $suffixes = ['ها','های','هایی','تر','ترین','هایم','هایت','اند','یم','ید','م','ت','ش','مان','تان','شان','گر','گری'];
    foreach ($suffixes as $s) {
        if (\mb_strlen($word) > \mb_strlen($s) + 2 && \str_ends_with($word, $s)) {
            return \mb_substr($word, 0, -\mb_strlen($s));
        }
    }
    return $word;
}
```

## ۵. ایندکس خودکار از رویداد
```php
// EventListener
final class IndexProductListener
{
    public function handle(ProductWasUpdated $e): void
    {
        $product = Product::find($e->aggregateId);
        Search::index('products', $product);
    }
    public function handleDeleted(ProductWasDeleted $e): void
    {
        Search::delete('products', $e->aggregateId);
    }
}
```

## ۶. Full-Text در MySQL (ngram برای فارسی)
```sql
CREATE FULLTEXT INDEX ft_products ON shop_products (title, summary) WITH PARSER ngram;
-- یا برای فارسی، native index بهتر است (ngram برای CJK طراحی شده)
```
```php
// MySQL
SELECT *, MATCH(title, summary) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance
FROM shop_products WHERE MATCH(title, summary) AGAINST (?) ORDER BY relevance DESC
```

## ۷. Facet و Highlight
```php
// Facet: شمارش مقادیر یک فیلد در نتایج
private function computeFacets(array $hits, array $facetFields): array
{
    $facets = [];
    foreach ($facetFields as $field) {
        $counts = [];
        foreach ($hits as $hit) {
            $value = $hit[$field] ?? null;
            if ($value !== null) { $counts[$value] = ($counts[$value] ?? 0) + 1; }
        }
        \arsort($counts);
        $facets[$field] = \array_map(fn ($v, $c) => ['value'=>$v, 'count'=>$c], \array_keys($counts), $counts);
    }
    return $facets;
}

// Highlight: wrap واژه‌های matched
private function highlight(string $text, array $tokens): string
{
    foreach ($tokens as $t) {
        $text = \preg_replace('/(' . \preg_quote($t, '/') . ')/ui', '<mark>$1</mark>', $text) ?? $text;
    }
    return $text;
}
```
🔒 Highlight باید **escape-then-mark** باشد (اول escape، سپس `<mark>` افزودن) تا XSS نشود.

## ۸. پیکربندی
```php
// config/search.php
return [
    'default' => env_string('TVN_SEARCH_DRIVER', 'mysql'),
    'indexes' => [
        'products' => [
            'driver' => 'mysql', 'model' => Product::class,
            'fields' => ['title','summary','description'],
            'searchable' => ['title','summary'],
            'filterable' => ['category_id','price','status'],
            'sortable' => ['price','created_at'],
            'normalizer' => 'persian',
        ],
    ],
    'native' => ['path'=>storage_path('search'), 'persist'=>true],
    'stop_words' => 'fa',
    'min_token_length' => 2,
];
```

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| Injection در query | Prepared (MySQL/PG) / escaped (native) |
| XSS در highlight | escape-then-mark |
| DoS با query بزرگ | max query length + max tokens |
| نشت داده در facet | فقط فیلدهای filterable |

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| Native index ۱۰۰۰۰ سند | ≤ ۵s (یک‌بار) |
| جست‌وجوی native | ≤ ۵ms |
| MySQL FULLTEXT | ≤ ۲۰ms |
| Persist/load ایندکس | ≤ ۲۰۰ms |

## ۱۱. تست
| T-01 | Native index + search فارسی |
| T-02 | نرمال‌سازی ی/ک/نیم‌فاصله |
| T-03 | Stemming |
| T-04 | Facet counting |
| T-05 | Highlight (بدون XSS) |
| T-06 | MySQL/PG/SQLite FTS |
| T-07 | ایندکس خودکار از رویداد |
| T-08 | BM25 ranking |

## ۱۲. معیار پذیرش 🧪
- AC-SEARCH-01..08. AC-SEARCH-09: جست‌وجوی فارسی با نیم‌فاصله/اعراب درست کار می‌کند.

## ۱۳. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Scout | Driver abstraction | native Persian index، بدون Algolia/Meili اجباری |
| Hazelcast/Zend Lucene | Inverted index | ساده‌تر، فارسی‌آگاه |
| Elasticsearch | Facet/Highlight | بدون وابستگی به ES (اختیاری) |

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.search.v1
engine:
  id: tavaan.engine.search
  namespace: Tavaan\Engine\Search
  path: engines/search
  priority: P1
  phase: M6
  depends_on: [database, event]
  facade: {name: Search, accessor: search}
  drivers: [mysql, pgsql, sqlite, native, meilisearch, typesense]
  config_file: config/search.php
  public_api:
    - class: SearchManager
      methods: [index, delete, indexMany, search, suggest, createIndex, dropIndex, flush]
  loc_estimate: 1500
```
