<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Tests\Feature;

require_once __DIR__ . '/../Fixtures/SearchItem.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Search\Drivers\MeilisearchDriver;
use Tavaan\Engine\Search\Drivers\NativeDriver;
use Tavaan\Engine\Search\Drivers\TypesenseDriver;
use Tavaan\Engine\Search\Exceptions\SearchException;
use Tavaan\Engine\Search\Highlighter;
use Tavaan\Engine\Search\Persian;
use Tavaan\Engine\Search\SearchManager;
use Tavaan\Engine\Search\SearchResult;
use Tavaan\Engine\Search\Tests\Fixtures\SearchItem;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Search — DOC-052 §11 (T-01..T-08).
 */
final class SearchTest extends TestCase
{
    private Application $app;

    private string $tmp;

    private SearchManager $search;

    /** @var array<string, mixed> */
    private array $config;

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-search-test-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp, 0775, true);

        $this->config = [
            'default' => 'native',
            'auto_index' => false,
            'indexes' => [
                'products' => [
                    'driver' => 'native',
                    'searchable' => ['title^3', 'summary'],
                    'filterable' => ['category_id', 'price', 'brand'],
                    'sortable' => ['price', 'category_id'],
                ],
            ],
            'native' => ['path' => $this->tmp, 'persist' => true],
            'min_token_length' => 2,
            'max_query_length' => 500,
            'max_tokens' => 64,
        ];

        $this->app = \tavaan_test_app();
        $this->search = new SearchManager($this->app, $this->config);
    }

    protected function tearDown(): void
    {
        Model::flushTestState();
        $this->rrmdir($this->tmp);
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }

    private function seedProducts(): void
    {
        $this->search->indexMany('products', [
            '1' => ['id' => '1', 'title' => 'لپ تاپ ایسوس VivoBook', 'summary' => 'لپ‌تاپ سبک برای دانشجویان', 'category_id' => 5, 'price' => 32000000, 'brand' => 'asus'],
            '2' => ['id' => '2', 'title' => 'لپ تاپ لنوو IdeaPad', 'summary' => 'گزینهٔ اقتصادی لنوو', 'category_id' => 5, 'price' => 21000000, 'brand' => 'lenovo'],
            '3' => ['id' => '3', 'title' => 'گوشی سامسونگ گلکسی', 'summary' => 'پرچم‌دار سامسونگ با دوربین عالی', 'category_id' => 7, 'price' => 45000000, 'brand' => 'samsung'],
        ]);
    }

    // ─── T-01: Native index + search فارسی ───────────────────────────────

    public function testNativeIndexAndPersianSearch(): void
    {
        $this->seedProducts();

        $result = $this->search->search('products', 'لپ تاپ ایسوس');
        $this->assertTrue($result instanceof SearchResult);
        $this->assertSame(2, $result->total());      // دو لپ‌تاپ
        $this->assertSame('1', $result->hits()[0]['id']);   // ایسوس با boost عنوان اول است
        $this->assertGreaterThan(0.0, (float) $result->hits()[0]['score']);

        // جست‌وجوی فارسی بدون تطبیق → خالی
        $empty = $this->search->search('products', 'یخچال فریزر');
        $this->assertSame(0, $empty->total());
        $this->assertTrue($empty->isEmpty());
    }

    public function testSearchWithMultiWordQueryRanksByTermFrequency(): void
    {
        $this->search->indexMany('products', [
            '1' => ['id' => '1', 'title' => 'کفش ورزشی مردانه نایک', 'summary' => 'کفش دویدن'],
            '2' => ['id' => '2', 'title' => 'کفش چرم', 'summary' => 'کفش ورزشی مردانه راحت برای پیاده‌روی و ورزش'],
        ]);

        $result = $this->search->search('products', 'کفش ورزشی مردانه');
        $this->assertSame(2, $result->total());
        // عنوان doc1 هر سه واژه را دارد (boost ×3) → رتبهٔ اول
        $this->assertSame('1', $result->hits()[0]['id']);
    }

    // ─── T-02: نرمال‌سازی ی/ک/نیم‌فاصله/اعراب ────────────────────────────

    public function testPersianNormalization(): void
    {
        // ی ↔ ي و ک ↔ ك
        $this->assertSame(Persian::normalize('کتاب'), Persian::normalize('كتاب'));
        $this->assertSame(Persian::normalize('دانشگاه'), Persian::normalize('دانشگاه'));

        // نیم‌فاصله حذف می‌شود
        $this->assertSame('ميشود', Persian::normalize('می‌شود'));
        $this->assertSame(Persian::normalize('کتابها'), Persian::normalize('کتاب‌ها'));

        // اعراب حذف می‌شود (و ک→ك طبق قرارداد DOC-052)
        $this->assertSame('كتاب', Persian::normalize('کِتابٌ'));

        // ارقام فارسی/عربی → لاتین
        $this->assertSame('1234567890', Persian::normalize('۱۲۳۴۵۶۷۸۹۰'));
        $this->assertSame('1234567890', Persian::normalize('١٢٣٤٥٦٧٨٩٠'));

        // ة → ه و آ → ا
        $this->assertSame('مدرسه', Persian::normalize('مدرسة'));
        $this->assertSame(Persian::normalize('اب'), Persian::normalize('آب'));
    }

    public function testSearchMatchesAcrossYehKafAndZwnjVariants(): void
    {
        $this->search->index('products', ['id' => '1', 'title' => 'کتاب‌های برنامه‌نویسی', 'summary' => '']);

        // ی/ک عربی در کوئری باید فارسی ایندکس‌شده را پیدا کند
        $r1 = $this->search->search('products', 'كتاب برنامه نويسي');
        $this->assertSame(1, $r1->total());

        // اعراب‌گذاری در کوئری
        $r2 = $this->search->search('products', 'کِتاب');
        $this->assertSame(1, $r2->total());
    }

    // ─── T-03: Stemming ──────────────────────────────────────────────────

    public function testPersianStemming(): void
    {
        $this->assertSame('کتاب', Persian::stem('کتابها'));
        // در خط لولهٔ واقعی، normalize پیش از stem اجرا می‌شود (نیم‌فاصله حذف، ک→ك)
        $this->assertSame(Persian::normalize('کتاب'), Persian::stem(Persian::normalize('کتاب‌ها')));
        $this->assertSame('دانشجو', Persian::stem('دانشجوتر'));   // پسوند «تر»

        // ریشهٔ کوتاه نباید over-stem شود
        $this->assertSame('نان', Persian::stem('نان'));
        $this->assertSame('گل', Persian::stem('گل'));
    }

    public function testSearchMatchesStemmedVariants(): void
    {
        $this->search->index('products', ['id' => '1', 'title' => 'کتابهای آموزشی', 'summary' => '']);

        // «کتاب» باید «کتابهای» را پیدا کند (هر دو به یک ریشه stem می‌شوند)
        $result = $this->search->search('products', 'کتاب');
        $this->assertSame(1, $result->total());

        $result2 = $this->search->search('products', 'کتاب‌ها');
        $this->assertSame(1, $result2->total());
    }

    public function testStopWordsAreRemoved(): void
    {
        $tokens = Persian::tokenize('این و آن از برای با کتاب است');
        $this->assertContains(Persian::normalize('کتاب'), $tokens);
        $this->assertFalse(\in_array('این', $tokens, true));
        $this->assertFalse(\in_array('است', $tokens, true));
        $this->assertFalse(\in_array('برای', $tokens, true));
    }

    // ─── T-08: BM25 ranking ──────────────────────────────────────────────

    public function testBm25IdfFavorsRareTerms(): void
    {
        // «لپتاپ» در همه، «کمیاب» فقط در یکی
        $docs = [];
        for ($i = 1; $i <= 5; $i++) {
            $docs[(string) $i] = ['id' => (string) $i, 'title' => 'لپ تاپ شماره ' . $i, 'summary' => ''];
        }
        $docs['3']['title'] .= ' کمیاب';
        $this->search->indexMany('products', $docs);

        $result = $this->search->search('products', 'لپ تاپ کمیاب');
        $this->assertSame('3', $result->hits()[0]['id']);   // واژهٔ نادر idf بالاتر دارد
    }

    public function testFieldBoostAffectsRanking(): void
    {
        $this->search->indexMany('products', [
            '1' => ['id' => '1', 'title' => 'دوربین', 'summary' => 'لپ تاپ دارای دوربین'],   // در summary
            '2' => ['id' => '2', 'title' => 'لپ تاپ', 'summary' => 'دوربین ندارد'],           // در title (boost ×3)
        ]);

        $result = $this->search->search('products', 'لپ تاپ');
        $this->assertSame('2', $result->hits()[0]['id']);
        $this->assertGreaterThan(
            (float) $result->hits()[1]['score'],
            (float) $result->hits()[0]['score'],
        );
    }

    // ─── T-04: Facet counting ────────────────────────────────────────────

    public function testFacetsCountMatchedDocuments(): void
    {
        $this->seedProducts();

        $result = $this->search->search('products', 'لپ تاپ', ['facets' => ['brand', 'category_id']]);
        $facets = $result->facets();

        $this->assertArrayHasKey('brand', $facets);
        $brands = [];
        foreach ($facets['brand'] as $f) {
            $brands[(string) $f['value']] = (int) $f['count'];
        }
        $this->assertSame(1, $brands['asus']);
        $this->assertSame(1, $brands['lenovo']);
        $this->assertFalse(isset($brands['samsung']));   // در نتایج «لپ تاپ» نیست

        $this->assertSame(1, \count($facets['category_id']));
        $this->assertSame(2, $facets['category_id'][0]['count']);
    }

    public function testFacetsOnlyAllowedForFilterableFields(): void
    {
        $this->seedProducts();
        // 🔒 summary در filterable نیست — facet نباید نشت دهد
        $result = $this->search->search('products', 'لپ تاپ', ['facets' => ['summary']]);
        $this->assertSame([], $result->facets());
    }

    // ─── Filters ─────────────────────────────────────────────────────────

    public function testFiltersApplyOperators(): void
    {
        $this->seedProducts();

        $r1 = $this->search->search('products', 'لپ تاپ', ['filters' => ['price' => ['lte' => 25000000]]]);
        $this->assertSame(1, $r1->total());
        $this->assertSame('2', $r1->hits()[0]['id']);

        $r2 = $this->search->search('products', 'لپ تاپ', ['filters' => ['brand' => 'asus']]);
        $this->assertSame(1, $r2->total());

        $r3 = $this->search->search('products', 'لپ تاپ', ['filters' => ['brand' => ['in' => ['lenovo', 'asus']]]]);
        $this->assertSame(2, $r3->total());

        $r4 = $this->search->search('products', 'گوشی', ['filters' => ['price' => ['gte' => 40000000]]]);
        $this->assertSame(1, $r4->total());
    }

    public function testFilterOnNonFilterableFieldThrows(): void
    {
        $this->seedProducts();
        $this->expectException(SearchException::class);
        $this->expectExceptionMessageId('search.field_not_allowed');
        $this->search->search('products', 'لپ تاپ', ['filters' => ['title' => 'x']]);
    }

    // ─── Sort + Pagination ───────────────────────────────────────────────

    public function testSortByField(): void
    {
        $this->seedProducts();

        $asc = $this->search->search('products', 'لپ تاپ', ['sort' => ['price' => 'asc']]);
        $this->assertSame('2', $asc->hits()[0]['id']);   // 21M اول
        $this->assertSame('1', $asc->hits()[1]['id']);

        $desc = $this->search->search('products', 'لپ تاپ', ['sort' => ['price' => 'desc']]);
        $this->assertSame('1', $desc->hits()[0]['id']);
    }

    public function testPagination(): void
    {
        $docs = [];
        for ($i = 1; $i <= 10; $i++) {
            $docs[(string) $i] = ['id' => (string) $i, 'title' => 'کتاب شماره ' . $i, 'summary' => '', 'price' => $i];
        }
        $this->search->indexMany('products', $docs);

        $page1 = $this->search->search('products', 'کتاب', ['page' => 1, 'per_page' => 4, 'sort' => ['price' => 'asc']]);
        $this->assertSame(10, $page1->total());
        $this->assertCount(4, $page1->hits());
        $this->assertSame('1', $page1->hits()[0]['id']);
        $this->assertSame(3, $page1->lastPage());

        $page3 = $this->search->search('products', 'کتاب', ['page' => 3, 'per_page' => 4, 'sort' => ['price' => 'asc']]);
        $this->assertCount(2, $page3->hits());
        $this->assertSame('9', $page3->hits()[0]['id']);
    }

    // ─── T-05: Highlight (بدون XSS) ─────────────────────────────────────

    public function testHighlightWrapsMatchedWords(): void
    {
        $this->search->index('products', ['id' => '1', 'title' => 'لپ تاپ ایسوس', 'summary' => 'لپ‌تاپ ایسوس عالی']);

        $result = $this->search->search('products', 'ایسوس', ['highlight' => ['title']]);
        $highlight = $result->hits()[0]['highlight']['title'] ?? '';
        $this->assertStringContainsString('<mark>', $highlight);
        $this->assertStringContainsString('ایسوس</mark>', $highlight);
    }

    public function testHighlightEscapesBeforeMarking(): void
    {
        // 🔒 escape-then-mark: اسکریپت در داده نباید اجراشدنی خارج شود
        $this->search->index('products', [
            'id' => '1',
            'title' => 'لپ تاپ <script>alert(1)</script>',
            'summary' => '',
        ]);

        $result = $this->search->search('products', 'لپ تاپ', ['highlight' => ['title']]);
        $highlight = (string) ($result->hits()[0]['highlight']['title'] ?? '');

        $this->assertFalse(\str_contains($highlight, '<script>'));
        $this->assertStringContainsString('&lt;script&gt;', $highlight);
        // خود <mark> ما سالم است
        $this->assertStringContainsString('<mark>', $highlight);
    }

    public function testHighlighterRejectsArbitraryTags(): void
    {
        $out = Highlighter::highlight('کتاب', Persian::tokenize('کتاب'), '<script>x</script>', '</script>');
        $this->assertFalse(\str_contains($out, '<script>x</script>'));
        $this->assertStringContainsString('<mark>کتاب</mark>', $out);
    }

    // ─── Suggest / did-you-mean ──────────────────────────────────────────

    public function testSuggestPrefix(): void
    {
        $this->seedProducts();
        $suggestions = $this->search->suggest('products', 'لپ', 5);
        $this->assertNotEmpty($suggestions);
        $this->assertContains('لپ', $suggestions);
    }

    public function testDidYouMeanOnNoResults(): void
    {
        $this->seedProducts();
        // «سامسونق» غلط املایی «سامسونگ» (فاصلهٔ ۱)
        $result = $this->search->search('products', 'سامسونق');
        $this->assertSame(0, $result->total());
        $this->assertNotEmpty($result->suggestions());
        $this->assertContains('سامسونگ', $result->suggestions());
    }

    // ─── T-06: SQLite FTS5 ───────────────────────────────────────────────

    public function testSqliteFtsDriver(): void
    {
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);

        $config = $this->config;
        $config['indexes']['products']['driver'] = 'sqlite';
        $search = new SearchManager($this->app, $config);

        try {
            $search->createIndex('products');
        } catch (SearchException $e) {
            $this->assertSame('search.driver_unavailable', $e->messageId());
            $this->assertTrue(true);   // FTS5 در این build نیست — skip
            return;
        }

        $search->indexMany('products', [
            '1' => ['id' => '1', 'title' => 'لپ تاپ ایسوس', 'summary' => 'سبک و باریک', 'category_id' => 5, 'price' => 32000000, 'brand' => 'asus'],
            '2' => ['id' => '2', 'title' => 'گوشی سامسونگ', 'summary' => 'پرچم دار', 'category_id' => 7, 'price' => 45000000, 'brand' => 'samsung'],
        ]);

        $this->assertSame(2, $search->count('products'));
        $this->assertTrue($search->indexExists('products'));

        $result = $search->search('products', 'لپ تاپ', [
            'facets' => ['brand'],
            'highlight' => ['title'],
        ]);
        $this->assertSame(1, $result->total());
        $this->assertSame('1', $result->hits()[0]['id']);
        $this->assertSame('لپ تاپ ایسوس', $result->hits()[0]['document']['title']);
        $this->assertArrayHasKey('brand', $result->facets());

        // فیلتر عددی
        $filtered = $search->search('products', 'گوشی', ['filters' => ['price' => ['gte' => 50000000]]]);
        $this->assertSame(0, $filtered->total());

        // حذف
        $search->delete('products', '1');
        $this->assertSame(1, $search->count('products'));

        // flush
        $search->flush('products');
        $this->assertSame(0, $search->count('products'));

        $search->dropIndex('products');
        $this->assertFalse($search->indexExists('products'));
    }

    // ─── T-07: ایندکس خودکار از رویداد ───────────────────────────────────

    public function testAutoIndexThroughModelEvents(): void
    {
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->app->make('config')->set('search', $this->config);
        $this->app->make('config')->set('search.auto_index', true);

        // manager اپ — listenerهای boot از آن استفاده می‌کنند
        /** @var SearchManager $appSearch */
        $appSearch = $this->app->make('search');

        $this->app->make('schema')->create('search_items', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->text('summary')->nullable();
            $t->unsignedInteger('category_id')->nullable();
            $t->unsignedBigInteger('price')->default(0);
            $t->string('status')->default('active');
            $t->timestamps(3);
        });

        $item = SearchItem::create([
            'title' => 'ماوس بی‌سیم لاجیتک',
            'summary' => 'ماوس ارگونومیک',
            'category_id' => 3,
            'price' => 850000,
            'status' => 'active',
        ]);

        // رویداد model.saved باید ایندکس کرده باشد
        $result = $appSearch->search('items', 'ماوس لاجیتک');
        $this->assertSame(1, $result->total());
        $this->assertSame((string) $item->getKey(), $result->hits()[0]['id']);

        // حذف → model.deleted → حذف از ایندکس
        $item->delete();
        $result2 = $appSearch->search('items', 'ماوس');
        $this->assertSame(0, $result2->total());
    }

    // ─── پایستگی ─────────────────────────────────────────────────────────

    public function testNativeIndexPersistsAndReloads(): void
    {
        $this->seedProducts();
        $this->assertNotEmpty(\glob($this->tmp . '/products.json') ?: []);

        // manager جدید با همان path — باید از دیسک بازیابد
        $reloaded = new SearchManager($this->app, $this->config);
        $result = $reloaded->search('products', 'ایسوس');
        $this->assertSame(1, $result->total());
        $this->assertSame(3, $reloaded->count('products'));
    }

    public function testNativeDriverWithoutPersistIsMemoryOnly(): void
    {
        $config = $this->config;
        $config['native']['persist'] = false;
        $search = new SearchManager($this->app, $config);

        $search->index('products', ['id' => '1', 'title' => 'تست حافظه', 'summary' => '']);
        $this->assertSame(1, $search->search('products', 'حافظه')->total());
        $this->assertEmpty(\glob($this->tmp . '/products.json') ?: []);
    }

    // ─── امنیت / DoS ─────────────────────────────────────────────────────

    public function testQueryTooLongThrows(): void
    {
        $this->expectException(SearchException::class);
        $this->expectExceptionMessageId('search.query_too_long');
        $this->search->search('products', \str_repeat('کتاب ', 200));
    }

    public function testInvalidIndexNameThrows(): void
    {
        $this->expectException(SearchException::class);
        $this->expectExceptionMessageId('search.invalid_index_name');
        $this->search->createIndex('../../etc/passwd');
    }

    public function testUnknownDriverThrows(): void
    {
        $config = $this->config;
        $config['default'] = 'elasticsearch';
        unset($config['indexes']);
        $search = new SearchManager($this->app, $config);

        $this->expectException(SearchException::class);
        $this->expectExceptionMessageId('search.unknown_driver');
        $search->search('products', 'x');
    }

    public function testMaxTokensCapsQuery(): void
    {
        $this->search->index('products', ['id' => '1', 'title' => 'باب بابک', 'summary' => '']);
        // ۱۰۰ توکن (۴۰۰ کاراکتر < max_query_length) — باید تا ۶۴ توکن محدود شود و hang نکند
        $result = $this->search->search('products', \rtrim(\str_repeat('باب ', 100)));
        $this->assertTrue($result instanceof SearchResult);
        $this->assertSame(1, $result->total());
    }

    // ─── HTTP adapters (با transport ساختگی) ─────────────────────────────

    public function testMeilisearchAdapter(): void
    {
        $requests = [];
        $transport = static function (string $method, string $path, ?array $body) use (&$requests): array {
            $requests[] = ['method' => $method, 'path' => $path, 'body' => $body];

            if ($method === 'POST' && \str_ends_with($path, '/search')) {
                return ['status' => 200, 'body' => [
                    'hits' => [
                        ['id' => '9', 'title' => 'Meili hit', '_formatted' => ['title' => 'Meili <mark>hit</mark>']],
                    ],
                    'estimatedTotalHits' => 1,
                    'facetDistribution' => ['brand' => ['asus' => 3]],
                ]];
            }

            return ['status' => 200, 'body' => ['taskUid' => 1]];
        };

        $driver = new MeilisearchDriver(['host' => 'http://meili:7700', 'key' => 'k'], $transport);
        $driver->createIndex('products');
        $driver->indexMany('products', ['9' => ['title' => 'Meili hit']]);
        $result = $driver->search('products', 'hit', ['facets' => ['brand'], 'highlight' => ['title']]);

        $this->assertSame(1, $result->total());
        $this->assertSame('9', $result->hits()[0]['id']);
        $this->assertSame('Meili <mark>hit</mark>', $result->hits()[0]['highlight']['title']);
        $this->assertSame(3, $result->facets()['brand'][0]['count']);

        // بررسی مسیرها
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertSame('/indexes', $requests[0]['path']);
        $this->assertSame(['uid' => 'products', 'primaryKey' => 'id'], $requests[0]['body']);
        $this->assertStringContainsString('/indexes/products/documents', $requests[1]['path']);
    }

    public function testTypesenseAdapter(): void
    {
        $requests = [];
        $transport = static function (string $method, string $path, ?array $body) use (&$requests): array {
            $requests[] = ['method' => $method, 'path' => $path];

            if (\str_contains($path, '/search?')) {
                return ['status' => 200, 'body' => [
                    'found' => 1,
                    'hits' => [[
                        'document' => ['id' => '7', 'title' => 'Typesense doc'],
                        'text_match' => 12.5,
                        'highlights' => [['field' => 'title', 'snippet' => 'Typesense <mark>doc</mark>']],
                    ]],
                    'facet_counts' => [['field_name' => 'brand', 'counts' => [['value' => 'asus', 'count' => 2]]]],
                ]];
            }

            return ['status' => 200, 'body' => []];
        };

        $driver = new TypesenseDriver(['host' => 'http://ts:8108', 'key' => 'k'], $transport);
        $driver->createIndex('products');
        $driver->indexMany('products', ['7' => ['title' => 'Typesense doc']]);
        $result = $driver->search('products', 'doc', ['facets' => ['brand'], 'highlight' => ['title']]);

        $this->assertSame(1, $result->total());
        $this->assertSame('7', $result->hits()[0]['id']);
        $this->assertEqualsWithDelta(12.5, (float) $result->hits()[0]['score'], 0.001);
        $this->assertSame(2, $result->facets()['brand'][0]['count']);
        $this->assertStringContainsString('action=upsert', $requests[1]['path']);
    }

    // ─── extend ──────────────────────────────────────────────────────────

    public function testExtendRegistersCustomDriver(): void
    {
        $search = new SearchManager($this->app, ['default' => 'mock', 'native' => $this->config['native']]);
        $search->extend('mock', fn (): NativeDriver => new NativeDriver(['native' => ['persist' => false]]));

        $search->createIndex('products');
        $search->index('products', ['id' => '1', 'title' => 'درایور سفارشی', 'summary' => '']);
        $this->assertSame(1, $search->search('products', 'سفارشی')->total());
    }

    // ─── Facade ──────────────────────────────────────────────────────────

    public function testFacadeResolves(): void
    {
        $this->assertTrue($this->app->bound('search'));
        $this->assertInstanceOf(SearchManager::class, $this->app->make('search'));
        $this->assertInstanceOf(
            \Tavaan\Core\Contracts\Search\SearchManagerInterface::class,
            $this->app->make('search'),
        );
    }

    public function testNativeDriverDirectApi(): void
    {
        $driver = new NativeDriver($this->config);
        $driver->createIndex('direct');
        $this->assertTrue($driver->indexExists('direct'));

        $driver->index('direct', 'a', ['title' => 'سلام دنیا', 'body' => 'متن آزمایشی']);
        $this->assertSame(1, $driver->count('direct'));

        $r = $driver->search('direct', 'دنیا');
        $this->assertSame(1, $r->total());

        $driver->delete('direct', 'a');
        $this->assertSame(0, $driver->count('direct'));

        $driver->dropIndex('direct');
        $this->assertFalse($driver->indexExists('direct'));
    }
}
