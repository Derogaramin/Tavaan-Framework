<?php

declare(strict_types=1);

namespace Modules\Blog\Tests\Feature;

use Modules\Blog\Domain\Entities\Post;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Blog\Application\UseCases\PostWriteService;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول وبلاگ — سناریوهای DOC-091 §7 (E2E-01..05) + AC-BLOG-01..04.
 *
 * مسیر واقعی HTTP: MakesHttpRequests درون‌فرایندی + SQLite in-memory +
 * RBAC واقعی (نقش/مجوز DB) + actingAs — بدون I/O خارجی.
 */
final class BlogE2ETest extends TestCase
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
        $config->set('session.csrf.except', ['admin/*', 'blog/*']);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── E2E-01: نویسنده مقاله می‌سازد → editor تأیید → منتشر ────────────

    public function testE2E01_AuthorCreatesEditorPublishes(): void
    {
        $author = $this->user('author1@test.dev', 'نویسنده', role: 'author');

        // ۱) نویسنده مقاله می‌سازد
        $response = $this->actingAs($author)->post('/admin/blog/posts', [
            'title' => 'مقالهٔ آزمایشی',
            'body' => "## عنوان دوم\n\nمتن **آزمایشی** با محتوای کافی.",
            'tags' => 'تست، توان',
            'status' => 'draft',
        ]);
        $response->assertRedirect();

        /** @var PostRepositoryInterface $repo */
        $repo = $this->app()->make(PostRepositoryInterface::class);
        $post = $repo->findBySlug('مقاله-آزمایشی');
        self::assertNotNull($post, 'مقاله با slug فارسی ساخته نشد');
        self::assertSame('draft', $post->status()->value);
        self::assertStringContainsString('<strong>آزمایشی</strong>', (string) $post->body());
        self::assertCount(2, $this->app()->make(\Modules\Blog\Domain\Repositories\TagRepositoryInterface::class)->forPost($post->id()));

        // ۲) مقالهٔ draft برای خوانندهٔ عمومی 404 است
        $this->get('/blog/' . $post->slug())->assertNotFound();

        // ۳) نویسنده اجازهٔ انتشار ندارد (403)
        $this->actingAs($author)->post('/admin/blog/posts/' . $post->id() . '/publish')->assertForbidden();

        // ۴) editor تأیید و منتشر می‌کند
        $editor = $this->user('editor1@test.dev', 'ویراستار', role: 'editor');
        $this->actingAs($editor)->post('/admin/blog/posts/' . $post->id() . '/publish')->assertRedirect();

        $fresh = $repo->findById($post->id());
        self::assertNotNull($fresh);
        self::assertSame('published', $fresh->status()->value);
        self::assertNotNull($fresh->publishedAt());

        // ۵) مقاله منتشرشده عمومی可见 است
        $page = $this->get('/blog/' . $post->slug());
        $page->assertOk();
        $page->assertSee('مقالهٔ آزمایشی');
    }

    public function testE2E01b_AuthorCannotEditOthersPost(): void
    {
        $authorA = $this->user('a@test.dev', 'الف', role: 'author');
        $authorB = $this->user('b@test.dev', 'ب', role: 'author');

        $post = $this->createPost($authorA, 'post-by-a');

        // نویسندهٔ B مقالهٔ A را نمی‌بیند (فهرست فقط مقالات خود) و ویرایشش 403 است
        $this->actingAs($authorB)->post('/admin/blog/posts/' . $post->id(), [
            'title' => 'هک',
            '_method' => 'POST',
        ])->assertForbidden();

        // نویسندهٔ A مقالهٔ خودش را ویرایش می‌کند
        $this->actingAs($authorA)->post('/admin/blog/posts/' . $post->id(), [
            'title' => 'عنوان ویرایش‌شده',
            'body' => 'بدنهٔ جدید',
        ])->assertRedirect();

        /** @var PostRepositoryInterface $repo */
        $repo = $this->app()->make(PostRepositoryInterface::class);
        self::assertSame('عنوان ویرایش‌شده', $repo->findById($post->id())?->title());
    }

    public function testGuestCannotAccessAdmin(): void
    {
        // بدون ورود → redirect به login (AuthenticateMiddleware)
        $this->get('/admin/blog/posts')->assertRedirect();
    }

    // ─── E2E-02: خواننده نظر می‌گذارد → moderation → نمایش ────────────────

    public function testE2E02_ReaderCommentsThenModeration(): void
    {
        $author = $this->user('author2@test.dev', 'نویسنده', role: 'author');
        $post = $this->createPost($author, 'commentable-post', publish: true);

        // ۱) خوانده (مهمان) نظر می‌گذارد
        $this->post('/blog/' . $post->slug() . '/comments', [
            'author_name' => 'خوانندهٔ مهمان',
            'author_email' => 'guest@test.dev',
            'body' => 'نظر بسیار خوبی بود!',
        ])->assertRedirect();

        // ۲) نظر pending است و نمایش داده نمی‌شود
        $page = $this->get('/blog/' . $post->slug());
        $page->assertOk();
        $page->assertDontSee('نظر بسیار خوبی بود');

        $comment = $this->findCommentByBody('نظر بسیار خوبی بود!');
        self::assertNotNull($comment);
        self::assertSame('pending', $comment['status']);

        // ۳) moderation — editor تأیید می‌کند
        $editor = $this->user('editor2@test.dev', 'ویراستار', role: 'editor');
        $this->actingAs($editor)->post('/admin/blog/comments/' . $comment['id'] . '/approve')->assertRedirect();

        // ۴) نظر تأییدشده نمایش داده می‌شود
        $page2 = $this->get('/blog/' . $post->slug());
        $page2->assertOk();
        $page2->assertSee('نظر بسیار خوبی بود');
        $page2->assertSee('خوانندهٔ مهمان');
    }

    public function testE2E02b_XssInCommentNeutralized(): void
    {
        // AC-BLOG-03: XSS در نظرات با escape دفع می‌شود
        $author = $this->user('author3@test.dev', 'نویسنده', role: 'author');
        $post = $this->createPost($author, 'xss-post', publish: true);

        $this->post('/blog/' . $post->slug() . '/comments', [
            'author_name' => '<script>bad</script>',
            'author_email' => 'xss@test.dev',
            'body' => '<script>alert(1)</script><img src=x onerror=alert(2)>',
        ])->assertRedirect();

        $comment = $this->findCommentByBody('<script>alert(1)</script><img src=x onerror=alert(2)>');
        self::assertNotNull($comment);

        // approve و بررسی رندر
        $editor = $this->user('editor3@test.dev', 'ویراستار', role: 'editor');
        $this->actingAs($editor)->post('/admin/blog/comments/' . $comment['id'] . '/approve')->assertRedirect();

        $page = $this->get('/blog/' . $post->slug());
        $page->assertOk();
        $body = $page->content();
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringNotContainsString('<img src=x', $body);
        self::assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testE2E02c_NestedCommentThread(): void
    {
        $author = $this->user('author4@test.dev', 'نویسنده', role: 'author');
        $post = $this->createPost($author, 'thread-post', publish: true);

        $this->post('/blog/' . $post->slug() . '/comments', [
            'author_name' => 'اولی', 'author_email' => 'one@test.dev', 'body' => 'نظر ریشه',
        ])->assertRedirect();
        $root = $this->findCommentByBody('نظر ریشه');
        self::assertNotNull($root);

        // پاسخ تو‌در‌تو
        $this->post('/blog/' . $post->slug() . '/comments', [
            'author_name' => 'دومی', 'author_email' => 'two@test.dev',
            'body' => 'پاسخ به ریشه', 'parent_id' => $root['id'],
        ])->assertRedirect();

        // والد نامعتبر → رد
        $this->post('/blog/' . $post->slug() . '/comments', [
            'author_name' => 'سومی', 'author_email' => 'three@test.dev',
            'body' => 'پاسخ نامعتبر', 'parent_id' => '01HZXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertRedirect();   // با flash خطا برمی‌گردد

        $editor = $this->user('editor4@test.dev', 'ویراستار', role: 'editor');
        foreach ([$root['id'], $this->findCommentByBody('پاسخ به ریشه')['id']] as $id) {
            $this->actingAs($editor)->post('/admin/blog/comments/' . $id . '/approve')->assertRedirect();
        }

        $page = $this->get('/blog/' . $post->slug());
        $page->assertOk();
        $body = $page->content();
        self::assertStringContainsString('نظر ریشه', $body);
        self::assertStringContainsString('پاسخ به ریشه', $body);
        self::assertStringContainsString('comment-children', $body);   // ساختار تو‌در‌تو

        // پاسخ با والدِ نامعتبر ذخیره نشده است (DB-level)
        self::assertNull($this->findCommentByBody('پاسخ نامعتبر'));
    }

    // ─── E2E-03: جست‌وجوی فارسی ───────────────────────────────────────────

    public function testE2E03_PersianSearch(): void
    {
        $author = $this->user('author5@test.dev', 'نویسنده', role: 'author');
        $this->createPost($author, 'persian-search-post', publish: true, title: 'آموزش فریمورک فارسی', body: 'جست‌وجوی فارسی با نرمال‌سازی ی و ک');
        $this->createPost($author, 'other-post', publish: true, title: 'A post in English', body: 'nothing relevant here');

        // انتشار رویداد → ایندکس search (native BM25 فارسی‌آگاه)
        $page = $this->get('/blog?q=' . \rawurlencode('فارسی'));
        $page->assertOk();
        $body = $page->content();
        self::assertStringContainsString('آموزش فریمورک فارسی', $body);
        self::assertStringNotContainsString('A post in English', $body);
    }

    // ─── E2E-04: فید RSS معتبر ────────────────────────────────────────────

    public function testE2E04_ValidRssFeed(): void
    {
        $author = $this->user('author6@test.dev', 'نویسنده', role: 'author');
        $this->createPost($author, 'feed-post', publish: true, title: 'مقالهٔ فید <X>');

        $response = $this->get('/blog/feed');
        $response->assertOk();
        self::assertSame('application/rss+xml; charset=UTF-8', $response->header('Content-Type'));

        $xml = $response->content();
        $prev = \libxml_use_internal_errors(true);
        $rss = \simplexml_load_string($xml);
        \libxml_use_internal_errors($prev);
        self::assertNotSame(false, $rss, 'RSS باید XML معتبر باشد (AC-BLOG-04)');

        $items = $rss->channel->item;
        self::assertGreaterThanOrEqual(1, \count($items));

        $found = false;
        foreach ($items as $item) {
            if ((string) $item->title === 'مقالهٔ فید <X>') {
                $found = true;
                self::assertStringContainsString('/blog/feed-post', (string) $item->link);
                self::assertNotSame(false, \strtotime((string) $item->pubDate));
            }
        }
        self::assertTrue($found, 'مقالهٔ منتشرشده در فید نیست');

        // Atom هم معتبر است
        $atom = $this->get('/blog/feed?format=atom');
        $atom->assertOk();
        self::assertSame('application/atom+xml; charset=UTF-8', $atom->header('Content-Type'));
        $prev = \libxml_use_internal_errors(true);
        self::assertNotSame(false, \simplexml_load_string($atom->content()));
        \libxml_use_internal_errors($prev);
    }

    // ─── E2E-05: آرشیو بر اساس تاریخ جلالی ────────────────────────────────

    public function testE2E05_JalaliArchive(): void
    {
        $author = $this->user('author7@test.dev', 'نویسنده', role: 'author');

        // مقاله با انتشار در ۱۴۰۳/۰۷/۱۰ (= 2024-10-01)
        $post = $this->createPost($author, 'meh-post', publish: false, title: 'مقالهٔ مهرماه');
        /** @var PostWriteService $write */
        $write = $this->app()->make(PostWriteService::class);
        $write->publish($post, new \DateTimeImmutable('2024-10-01 10:00:00', new \DateTimeZone('UTC')));

        // آرشیو ۱۴۰۳/۰۷ → مقاله هست
        $page = $this->get('/blog/archive/1403/07');
        $page->assertOk();
        $page->assertSee('مقالهٔ مهرماه');
        $page->assertSee('مهر ۱۴۰۳');

        // ارقام فارسی هم کار می‌کند
        $this->get('/blog/archive/' . \rawurlencode('۱۴۰۳') . '/' . \rawurlencode('۰۷'))->assertOk();

        // ماه دیگر → خالی
        $empty = $this->get('/blog/archive/1403/01');
        $empty->assertOk();
        $empty->assertDontSee('مقالهٔ مهرماه');

        // ماه نامعتبر → 404
        $this->get('/blog/archive/1403/13')->assertNotFound();
    }

    // ─── AC-BLOG-02: SEO (meta/sitemap/OG) ────────────────────────────────

    public function testSeoMetaOpenGraphAndSitemap(): void
    {
        $author = $this->user('author8@test.dev', 'نویسنده', role: 'author');
        $this->createPost($author, 'seo-post', publish: true, title: 'مقالهٔ سئو', body: 'بدنهٔ سئو');

        $page = $this->get('/blog/seo-post');
        $page->assertOk();
        $body = $page->content();
        self::assertStringContainsString('<meta name="description"', $body);
        self::assertStringContainsString('property="og:title"', $body);
        self::assertStringContainsString('property="og:type" content="article"', $body);

        $sitemap = $this->get('/blog/sitemap.xml');
        $sitemap->assertOk();
        self::assertSame('application/xml; charset=UTF-8', $sitemap->header('Content-Type'));
        $prev = \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($sitemap->content());
        \libxml_use_internal_errors($prev);
        self::assertNotSame(false, $xml);
        self::assertStringContainsString('/blog/seo-post', $sitemap->content());
    }

    // ─── سایر: بازدید، زمان‌بندی، ۴۰۴ ─────────────────────────────────────

    public function testViewCountIncrements(): void
    {
        $author = $this->user('author9@test.dev', 'نویسنده', role: 'author');
        $post = $this->createPost($author, 'viewed-post', publish: true);

        $this->get('/blog/viewed-post')->assertOk();
        $this->get('/blog/viewed-post')->assertOk();

        /** @var PostRepositoryInterface $repo */
        $repo = $this->app()->make(PostRepositoryInterface::class);
        self::assertSame(2, $repo->findBySlug('viewed-post')?->viewCount());
    }

    public function testScheduledPostHiddenUntilDue(): void
    {
        $author = $this->user('author10@test.dev', 'نویسنده', role: 'author');
        $post = $this->createPost($author, 'scheduled-post', publish: false);

        /** @var PostWriteService $write */
        $write = $this->app()->make(PostWriteService::class);
        $future = new \DateTimeImmutable('+2 days', new \DateTimeZone('UTC'));
        $write->schedule($post, $future);

        // هنوز منتشر نشده
        $this->get('/blog/scheduled-post')->assertNotFound();

        // دستور blog:publish-scheduled با زمان آینده → منتشر نمی‌کند
        $this->runConsole('blog:publish-scheduled');
        /** @var PostRepositoryInterface $repo */
        $repo = $this->app()->make(PostRepositoryInterface::class);
        self::assertSame('scheduled', $repo->findBySlug('scheduled-post')?->status()->value);
    }

    public function testUnknownSlug404(): void
    {
        $this->get('/blog/nafahmidam-chist')->assertNotFound();
        $this->get('/blog/category/na-mojood')->assertNotFound();
        $this->get('/blog/tag/na-mojoud')->assertNotFound();
    }

    public function testSoftDeletedPostHidden(): void
    {
        $editor = $this->user('editor9@test.dev', 'ویراستار', role: 'editor');
        $post = $this->createPost($editor, 'trashed-post', publish: true);

        $this->actingAs($editor)->delete('/admin/blog/posts/' . $post->id())->assertRedirect();

        $this->get('/blog/trashed-post')->assertNotFound();
        $list = $this->get('/blog');
        $list->assertOk();
        $list->assertDontSee('trashed-post');
    }

    // ─── Fixtureها ────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        $permissions = [
            'blog.post.view', 'blog.post.create', 'blog.post.update',
            'blog.post.delete', 'blog.post.publish',
            'blog.comment.view', 'blog.comment.approve', 'blog.comment.delete',
        ];
        foreach ($permissions as $key) {
            if (!$this->permissionExists($key)) {
                $this->roles->createPermission($key, 'tavaan.blog');
            }
        }

        $rolePerms = [
            'author' => ['blog.post.view', 'blog.post.create'],
            'editor' => $permissions,
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

    private function permissionExists(string $key): bool
    {
        $row = \Tavaan\Core\Facade\Facades\DB::table('tvn_permissions')->where('key', $key)->first();

        return $row !== null;
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    private function createPost(
        User $author,
        string $slug,
        bool $publish = false,
        string $title = 'عنوان پیش‌فرض',
        string $body = 'بدنهٔ پیش‌فرض مقاله برای تست.',
    ): Post {
        /** @var PostWriteService $write */
        $write = $this->app()->make(PostWriteService::class);
        $post = $write->create([
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'status' => $publish ? 'published' : 'draft',
        ], $author->id);

        return $post;
    }

    /** @return array<string, mixed>|null */
    private function findCommentByBody(string $body): ?array
    {
        return \Tavaan\Core\Facade\Facades\DB::table('blog_comments')->where('body', $body)->first();
    }

    private function runConsole(string $command): int
    {
        /** @var \Tavaan\Engine\Console\ConsoleKernel $kernel */
        $kernel = $this->app()->make(\Tavaan\Engine\Console\ConsoleKernel::class);

        return $kernel->call($command);
    }
}
