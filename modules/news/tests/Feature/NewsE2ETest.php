<?php

declare(strict_types=1);

namespace Modules\News\Tests\Feature;

use Modules\News\Application\UseCases\ArticleWriteService;
use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول خبرگزاری — سناریوهای DOC-092 §8 (E2E-01..06) + AC-NEWS.
 *
 * مسیر واقعی HTTP درون‌فرایندی + SQLite in-memory + RBAC واقعی (نقش/مجوز DB).
 */
final class NewsE2ETest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*', 'news/*']);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── E2E-01: reporter → submit → editor approve → chief publish ──────

    public function testE2E01_EditorialWorkflow(): void
    {
        $reporter = $this->user('reporter1@news.test', 'خبرنگار', role: 'reporter');
        $editor = $this->user('editor1@news.test', 'ویراستار', role: 'editor');
        $chief = $this->user('chief1@news.test', 'سردبیر', role: 'chief-editor');

        // ۱) خبرنگار خبر می‌سازد
        $this->actingAs($reporter)->post('/admin/news/articles', [
            'title' => 'خبر فوری اقتصادی',
            'lead' => 'لید خبر اقتصادی',
            'body' => "## جزئیات\n\nمتن کامل خبر با **اهمیت** بالا.",
            'tags' => 'اقتصاد، بازار',
        ])->assertRedirect();

        /** @var ArticleRepositoryInterface $repo */
        $repo = $this->app()->make(ArticleRepositoryInterface::class);
        $article = $repo->findBySlug('خبر-فوری-اقتصادی');
        self::assertNotNull($article, 'خبر با slug فارسی ساخته نشد');
        self::assertSame('draft', $article->status()->value);

        // ۲) submit برای بازبینی
        $this->actingAs($reporter)->post('/admin/news/articles/' . $article->id() . '/submit')->assertRedirect();
        self::assertSame('review', $repo->findById($article->id())?->status()->value);

        // ۳) خبرنگار نمی‌تواند approve کند (403)
        $this->actingAs($reporter)->post('/admin/news/articles/' . $article->id() . '/approve')->assertForbidden();

        // ۴) ویراستار approve می‌کند — ولی publish نمی‌تواند (403)
        $this->actingAs($editor)->post('/admin/news/articles/' . $article->id() . '/approve')->assertRedirect();
        self::assertSame('approved', $repo->findById($article->id())?->status()->value);
        self::assertSame($editor->id, $repo->findById($article->id())?->editorId());

        $this->actingAs($editor)->post('/admin/news/articles/' . $article->id() . '/publish')->assertForbidden();

        // ۵) سردبیر منتشر می‌کند
        $this->actingAs($chief)->post('/admin/news/articles/' . $article->id() . '/publish')->assertRedirect();
        $published = $repo->findById($article->id());
        self::assertSame('published', $published?->status()->value);
        self::assertNotNull($published?->publishedAt());

        // ۶) عمومی قابل مشاهده است
        $page = $this->get('/news/' . $article->slug());
        $page->assertOk();
        $page->assertSee('خبر فوری اقتصادی');
    }

    public function testE2E01b_RejectReturnsToReporterWithReason(): void
    {
        $reporter = $this->user('reporter2@news.test', 'خبرنگار', role: 'reporter');
        $editor = $this->user('editor2@news.test', 'ویراستار', role: 'editor');

        $article = $this->createArticle($reporter, 'rejected-news');
        $this->actingAs($reporter)->post('/admin/news/articles/' . $article->id() . '/submit')->assertRedirect();

        $this->actingAs($editor)->post('/admin/news/articles/' . $article->id() . '/reject', [
            'reason' => 'منبع ذکر نشده است',
        ])->assertRedirect();

        /** @var ArticleRepositoryInterface $repo */
        $repo = $this->app()->make(ArticleRepositoryInterface::class);
        $fresh = $repo->findById($article->id());
        self::assertSame('draft', $fresh?->status()->value);
        self::assertSame('منبع ذکر نشده است', $fresh?->rejectReason());

        // خبرنگار خبر ردشدهٔ خود را می‌بیند (ویرایش) اما خبر دیگران را نه
        $this->actingAs($reporter)->get('/admin/news/articles/' . $article->id() . '/edit')->assertOk();
    }

    public function testE2E01c_ReporterSeesOnlyOwnArticles(): void
    {
        $reporterA = $this->user('ra@news.test', 'الف', role: 'reporter');
        $reporterB = $this->user('rb@news.test', 'ب', role: 'reporter');

        $this->createArticle($reporterA, 'mine-news', title: 'خبر اختصاصی الف');
        $this->createArticle($reporterB, 'theirs-news', title: 'خبر اختصاصی ب');

        $page = $this->actingAs($reporterA)->get('/admin/news/articles');
        $page->assertOk();
        $body = $page->content();
        self::assertStringContainsString('خبر اختصاصی الف', $body);
        self::assertStringNotContainsString('خبر اختصاصی ب', $body);
    }

    public function testGuestCannotAccessNewsAdmin(): void
    {
        $this->get('/admin/news/articles')->assertRedirect();
    }

    // ─── E2E-02: زمان‌بندی انتشار خودکار ──────────────────────────────────

    public function testE2E02_ScheduledAutoPublish(): void
    {
        $reporter = $this->user('reporter3@news.test', 'خبرنگار', role: 'reporter');
        $editor = $this->user('editor3@news.test', 'ویراستار', role: 'editor');

        $article = $this->createArticle($reporter, 'scheduled-news');
        $this->actingAs($reporter)->post('/admin/news/articles/' . $article->id() . '/submit')->assertRedirect();

        // approve با scheduled_at گذشته → بلافاصله due است
        $past = (new \DateTimeImmutable('-2 hours', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i');
        $this->actingAs($editor)->post('/admin/news/articles/' . $article->id() . '/approve', [
            'scheduled_at' => $past,
        ])->assertRedirect();

        /** @var ArticleRepositoryInterface $repo */
        $repo = $this->app()->make(ArticleRepositoryInterface::class);
        self::assertSame('approved', $repo->findById($article->id())?->status()->value);

        // هنوز عمومی نیست
        $this->get('/news/scheduled-news')->assertNotFound();

        // اجرای دستور زمان‌بندی
        $exit = $this->runConsole('news:publish-scheduled');
        self::assertSame(0, $exit);

        $fresh = $repo->findById($article->id());
        self::assertSame('published', $fresh?->status()->value);
        $this->get('/news/scheduled-news')->assertOk();

        // approve با زمان آینده → منتشر نمی‌شود
        $future = $this->createArticle($reporter, 'future-news');
        $future->submitForReview();
        $future->approve($editor->id, new \DateTimeImmutable('+3 days', new \DateTimeZone('UTC')));
        $repo->save($future);

        $this->runConsole('news:publish-scheduled');
        self::assertSame('approved', $repo->findById($future->id())?->status()->value);
    }

    // ─── E2E-03: خبر فوری + realtime ──────────────────────────────────────

    public function testE2E03_BreakingNewsBannerAndEndpoint(): void
    {
        $chief = $this->user('chief2@news.test', 'سردبیر', role: 'chief-editor');

        $article = $this->createArticle($chief, 'breaking-news', publish: true, editorId: $chief->id);

        // علامت breaking (toggle)
        $this->actingAs($chief)->post('/admin/news/articles/' . $article->id() . '/breaking')->assertRedirect();

        // بنر در صفحهٔ فهرست
        $page = $this->get('/news');
        $page->assertOk();
        $body = $page->content();
        self::assertStringContainsString('breaking-bar', $body);
        self::assertStringContainsString('breaking-news', $body);

        // اندپوینت JSON خبرهای فوری
        $json = $this->getJson('/news/breaking');
        $json->assertOk();
        $data = $json->json('data');
        self::assertIsArray($data);
        self::assertCount(1, $data);
        self::assertSame('breaking-news', $data[0]['slug']);

        // reporter اجازهٔ breaking ندارد (403)
        $reporter = $this->user('reporter4@news.test', 'خبرنگار', role: 'reporter');
        $this->actingAs($reporter)->post('/admin/news/articles/' . $article->id() . '/breaking')->assertForbidden();
    }

    // ─── E2E-04: کش صفحه + invalidate در ویرایش ──────────────────────────

    public function testE2E04_CacheAndEtag(): void
    {
        $chief = $this->user('chief3@news.test', 'سردبیر', role: 'chief-editor');
        $article = $this->createArticle($chief, 'cached-news', publish: true, editorId: $chief->id);

        // ۱) ETag در پاسخ اول
        $first = $this->get('/news/cached-news');
        $first->assertOk();
        $etag = $first->header('ETag');
        self::assertNotNull($etag);
        self::assertNotSame('', (string) $etag);

        // ۲) درخواست با If-None-Match → 304 بدون بدنه
        $second = $this->get('/news/cached-news', [], ['If-None-Match' => (string) $etag]);
        self::assertSame(304, $second->status());

        // ۳) کش فهرست با نوشتن invalidate می‌شود
        $listBefore = $this->get('/news');
        $listBefore->assertOk();

        $another = $this->createArticle($chief, 'fresh-after-cache', publish: true, editorId: $chief->id);

        $listAfter = $this->get('/news');
        $listAfter->assertOk();
        self::assertStringContainsString('fresh-after-cache', $listAfter->content());
    }

    // ─── E2E-05: RSS + AMP ────────────────────────────────────────────────

    public function testE2E05_RssAndAmp(): void
    {
        $chief = $this->user('chief4@news.test', 'سردبیر', role: 'chief-editor');
        $this->createArticle($chief, 'feed-news', publish: true, editorId: $chief->id, title: 'خبر فید <X>');

        // RSS معتبر
        $rss = $this->get('/news/feed');
        $rss->assertOk();
        self::assertSame('application/rss+xml; charset=UTF-8', $rss->header('Content-Type'));
        $prev = \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($rss->content());
        \libxml_use_internal_errors($prev);
        self::assertNotSame(false, $xml, 'RSS باید XML معتبر باشد');

        $found = false;
        foreach ($xml->channel->item as $item) {
            if ((string) $item->title === 'خبر فید <X>') {
                $found = true;
            }
        }
        self::assertTrue($found);

        // Atom
        $atom = $this->get('/news/feed?format=atom');
        $atom->assertOk();
        $prev = \libxml_use_internal_errors(true);
        self::assertNotSame(false, \simplexml_load_string($atom->content()));
        \libxml_use_internal_errors($prev);

        // AMP — ساختار معتبر
        $amp = $this->get('/news/feed-news/amp');
        $amp->assertOk();
        $ampBody = $amp->content();
        self::assertStringContainsString('<html ⚡', $ampBody);
        self::assertStringContainsString('rel="canonical"', $ampBody);
        self::assertStringContainsString('amp-boilerplate', $ampBody);
        self::assertStringContainsString('cdn.ampproject.org/v0.js', $ampBody);
        self::assertStringContainsString('application/ld+json', $ampBody);
        self::assertStringContainsString('NewsArticle', $ampBody);
    }

    // ─── E2E-06: جست‌وجوی فارسی ───────────────────────────────────────────

    public function testE2E06_PersianSearch(): void
    {
        $chief = $this->user('chief5@news.test', 'سردبیر', role: 'chief-editor');
        $this->createArticle($chief, 'searchable-news', publish: true, editorId: $chief->id,
            title: 'تحلیل بازار بورس تهران', body: 'شاخص کل بورس امروز رشد کرد');
        $this->createArticle($chief, 'unrelated-news', publish: true, editorId: $chief->id,
            title: 'English headline here', body: 'nothing related');

        $page = $this->get('/news?q=' . \rawurlencode('بورس'));
        $page->assertOk();
        $body = $page->content();
        self::assertStringContainsString('تحلیل بازار بورس تهران', $body);
        self::assertStringNotContainsString('English headline here', $body);
    }

    // ─── SEO: JSON-LD + canonical (DOC-092 §7) ────────────────────────────

    public function testSeoJsonLdAndCanonical(): void
    {
        $chief = $this->user('chief6@news.test', 'سردبیر', role: 'chief-editor');
        $this->createArticle($chief, 'seo-news', publish: true, editorId: $chief->id, title: 'خبر سئو');

        $page = $this->get('/news/seo-news');
        $page->assertOk();
        $body = $page->content();

        self::assertStringContainsString('<script type="application/ld+json">', $body);
        self::assertStringContainsString('"@type":"NewsArticle"', \str_replace(' ', '', $body));
        self::assertStringContainsString('rel="canonical"', $body);
        self::assertStringContainsString('<meta name="description"', $body);
        self::assertStringContainsString('property="og:title"', $body);

        // sitemap
        $sitemap = $this->get('/news/sitemap.xml');
        $sitemap->assertOk();
        self::assertStringContainsString('/news/seo-news', $sitemap->content());
    }

    // ─── دستهٔ تو‌در‌تو (شامل زیردسته) ─────────────────────────────────────

    public function testNestedCategoryIncludesChildren(): void
    {
        $chief = $this->user('chief7@news.test', 'سردبیر', role: 'chief-editor');

        /** @var NewsCategoryRepositoryInterface $cats */
        $cats = $this->app()->make(NewsCategoryRepositoryInterface::class);
        $parentId = $cats->save(\Modules\News\Domain\Entities\NewsCategory::create('ورزش', 'varzesh'));
        $childId = $cats->save(\Modules\News\Domain\Entities\NewsCategory::create('فوتبال', 'football', parentId: $parentId));

        $article = $this->createArticle($chief, 'child-cat-news', publish: true, editorId: $chief->id, categoryId: $childId);

        // صفحهٔ دستهٔ والد → خبر زیردسته نمایش داده می‌شود
        $page = $this->get('/news/category/varzesh');
        $page->assertOk();
        self::assertStringContainsString('child-cat-news', $page->content());

        // صفحهٔ خود زیردسته هم
        $this->get('/news/category/football')->assertOk();
    }

    // ─── نظرات با moderation ──────────────────────────────────────────────

    public function testCommentsModerationFlow(): void
    {
        $chief = $this->user('chief8@news.test', 'سردبیر', role: 'chief-editor');
        $article = $this->createArticle($chief, 'commented-news', publish: true, editorId: $chief->id);

        $this->post('/news/commented-news/comments', [
            'author_name' => 'مخبر',
            'author_email' => 'm@news.test',
            'body' => 'خبر دقیقی بود',
        ])->assertRedirect();

        // XSS در نظر — همان جریان مهمان (پیش از actingAs تا گارد آلوده نشود)
        $this->post('/news/commented-news/comments', [
            'author_name' => 'هکر',
            'author_email' => 'h@news.test',
            'body' => '<script>alert(1)</script>',
        ])->assertRedirect();

        // pending → نمایش داده نمی‌شود
        $this->get('/news/commented-news')->assertDontSee('خبر دقیقی بود');

        $row = \Tavaan\Core\Facade\Facades\DB::table('news_comments')->where('body', 'خبر دقیقی بود')->first();
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);

        $xss = \Tavaan\Core\Facade\Facades\DB::table('news_comments')->where('author_email', 'h@news.test')->first();
        self::assertNotNull($xss);

        // approve → نمایش + escape
        $editor = $this->user('editor8@news.test', 'ویراستار', role: 'editor');
        $this->actingAs($editor)->post('/admin/news/comments/' . $row['id'] . '/approve')->assertRedirect();
        $this->actingAs($editor)->post('/admin/news/comments/' . $xss['id'] . '/approve')->assertRedirect();

        $page = $this->get('/news/commented-news');
        $page->assertSee('خبر دقیقی بود');
        self::assertStringNotContainsString('<script>alert(1)</script>', $page->content());
        self::assertStringContainsString('&lt;script&gt;', $page->content());
    }

    // ─── مدیریت دسته‌ها ───────────────────────────────────────────────────

    public function testCategoryManagement(): void
    {
        $chief = $this->user('chief9@news.test', 'سردبیر', role: 'chief-editor');

        $this->actingAs($chief)->post('/admin/news/categories', [
            'title' => 'فرهنگ و هنر',
            'slug' => 'farhang',
        ])->assertRedirect();

        /** @var NewsCategoryRepositoryInterface $cats */
        $cats = $this->app()->make(NewsCategoryRepositoryInterface::class);
        self::assertNotNull($cats->findBySlug('farhang'));

        // reporter اجازهٔ مدیریت دسته ندارد
        $reporter = $this->user('reporter9@news.test', 'خبرنگار', role: 'reporter');
        $this->actingAs($reporter)->post('/admin/news/categories', ['title' => 'هک'])->assertForbidden();
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        $all = [
            'news.article.view', 'news.article.create', 'news.article.update',
            'news.article.delete', 'news.article.submit', 'news.article.review',
            'news.article.approve', 'news.article.publish', 'news.article.breaking',
            'news.category.view', 'news.category.manage',
            'news.comment.view', 'news.comment.approve', 'news.comment.delete',
        ];
        foreach ($all as $key) {
            $exists = \Tavaan\Core\Facade\Facades\DB::table('tvn_permissions')->where('key', $key)->first() !== null;
            if (!$exists) {
                $this->roles->createPermission($key, 'tavaan.news');
            }
        }

        $rolePerms = [
            'reporter' => ['news.article.view', 'news.article.create', 'news.article.submit'],
            'editor' => [
                'news.article.view', 'news.article.create', 'news.article.submit',
                'news.article.update', 'news.article.review', 'news.article.approve',
                'news.comment.view', 'news.comment.approve', 'news.comment.delete',
                'news.category.view',
            ],
            'chief-editor' => $all,
        ];
        foreach ($rolePerms as $roleKey => $perms) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
            foreach ($perms as $perm) {
                $this->roles->grantPermissionToRole($roleKey, $perm);
            }
        }
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    private function createArticle(
        User $author,
        string $slug,
        bool $publish = false,
        ?int $editorId = null,
        string $title = 'عنوان پیش‌فرض',
        string $body = 'بدنهٔ پیش‌فرض خبر برای تست.',
        ?int $categoryId = null,
    ): Article {
        /** @var ArticleWriteService $write */
        $write = $this->app()->make(ArticleWriteService::class);
        $article = $write->create([
            'title' => $title,
            'slug' => $slug,
            'lead' => 'لید پیش‌فرض',
            'body' => $body,
            'category_id' => $categoryId,
        ], $author->id);

        if ($publish) {
            $article->submitForReview();
            $article->approve($editorId ?? $author->id);
            $write->publish($article, $editorId ?? $author->id);
        }

        return $article;
    }

    private function runConsole(string $command): int
    {
        /** @var \Tavaan\Engine\Console\ConsoleKernel $kernel */
        $kernel = $this->app()->make(\Tavaan\Engine\Console\ConsoleKernel::class);

        return $kernel->call($command);
    }
}
