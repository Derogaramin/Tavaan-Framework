<?php

declare(strict_types=1);

namespace Modules\Content\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E صفحات CMS — پنل /admin/pages با RBAC واقعی، نمایش عمومی /pages،
 * sitemap، سانیتیزه‌سازی XSS و یکپارچگی نظرات (ماژول comment — نوع page).
 *
 * مسیر واقعی HTTP + SQLite in-memory (هم‌راستا با CommentHttpTest).
 */
final class PageHttpTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private UserRepositoryInterface $users;

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
        $config->set('session.csrf.except', ['admin/*', 'comments/*', 'api/*']);
        $config->set('comment.moderation.mode', 'manual');
        $config->set('comment.moderation.auto_approve_trusted', false);

        $this->users = $app->make(UserRepositoryInterface::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── دسترسی پنل ────────────────────────────────────────────────────────

    public function testGuestRedirectedFromAdminPages(): void
    {
        $this->get('/admin/pages')->assertRedirect();
        $this->get('/admin/pages/create')->assertRedirect();
    }

    public function testUserWithoutPermissionForbidden(): void
    {
        $plain = $this->users->create(name: 'کاربر ساده', email: 'plain@example.com', plainPassword: 'test-12345');

        $this->actingAs($plain)->get('/admin/pages')->assertForbidden();
        $this->actingAs($plain)->post('/admin/pages', ['title' => 'تلاش بدون مجوز'])->assertForbidden();
    }

    // ─── CRUD مدیریت ───────────────────────────────────────────────────────

    public function testAdminCreatesDraftPage(): void
    {
        $admin = $this->makeAdmin('creator@example.com');

        $this->actingAs($admin)->post('/admin/pages', [
            'title' => 'صفحهٔ شرایط استفاده',
            'body' => "# شرایط\n\nمتن حقوقی **مهم**",
        ])->assertRedirect();

        $row = DB::table('content_pages')->where('title', 'صفحهٔ شرایط استفاده')->first();
        self::assertNotNull($row);
        self::assertSame('draft', $row['status']);
        self::assertNotSame('', (string) $row['slug']);
        self::assertStringContainsString('<strong>', (string) $row['body']);

        // فرم ویرایش قابل دسترس است
        $this->actingAs($admin)->get('/admin/pages/' . $row['id'] . '/edit')
            ->assertOk()
            ->assertSee('صفحهٔ شرایط استفاده');
    }

    public function testCreateWithoutTitleRedirectsBackWithErrors(): void
    {
        $admin = $this->makeAdmin('invalid@example.com');

        $this->actingAs($admin)->post('/admin/pages', ['title' => ''])
            ->assertRedirect('/admin/pages/create');

        self::assertFalse(DB::table('content_pages')->exists());
    }

    public function testAdminUpdatesPage(): void
    {
        $admin = $this->makeAdmin('updater@example.com');
        $row = $this->createPageAsAdmin($admin, 'عنوان قدیم');

        $this->actingAs($admin)->post('/admin/pages/' . $row['id'], [
            'title' => 'عنوان ویرایش‌شده',
            'body' => 'بدنهٔ ویرایش‌شده',
            'comments_open' => '1',
        ])->assertRedirect();

        $fresh = DB::table('content_pages')->where('id', $row['id'])->first();
        self::assertNotNull($fresh);
        self::assertSame('عنوان ویرایش‌شده', $fresh['title']);
    }

    public function testPublishArchiveLifecycleViaHttp(): void
    {
        $admin = $this->makeAdmin('lifecycle@example.com');
        $row = $this->createPageAsAdmin($admin, 'صفحهٔ چرخه');
        $slug = (string) $row['slug'];

        // پیش‌نویس → عمومی 404
        $this->get('/pages/' . $slug)->assertStatus(404);

        // انتشار → عمومی 200
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/publish')->assertRedirect();
        $published = DB::table('content_pages')->where('id', $row['id'])->first();
        self::assertNotNull($published);
        self::assertSame('published', $published['status']);
        self::assertNotNull($published['published_at']);

        $this->get('/pages/' . $slug)->assertOk()->assertSee('صفحهٔ چرخه');

        // بایگانی → عمومی دوباره 404
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/archive')->assertRedirect();
        $this->get('/pages/' . $slug)->assertStatus(404);
    }

    public function testDestroySoftDeletesAndHidesFromAdminList(): void
    {
        $admin = $this->makeAdmin('deleter@example.com');
        $row = $this->createPageAsAdmin($admin, 'صفحهٔ حذف‌شدنی');

        $this->actingAs($admin)->delete('/admin/pages/' . $row['id'])->assertRedirect('/admin/pages');

        $trashed = DB::table('content_pages')->where('id', $row['id'])->first();
        self::assertNotNull($trashed);
        self::assertNotNull($trashed['deleted_at']);

        $this->actingAs($admin)->get('/admin/pages')->assertOk()->assertDontSee('صفحهٔ حذف‌شدنی');
    }

    // ─── نمایش عمومی ──────────────────────────────────────────────────────

    public function testPagesIndexListsOnlyPublished(): void
    {
        $admin = $this->makeAdmin('indexer@example.com');
        $live = $this->createPageAsAdmin($admin, 'صفحهٔ منتشرشدهٔ فهرست');
        $this->actingAs($admin)->post('/admin/pages/' . $live['id'] . '/publish')->assertRedirect();
        $this->createPageAsAdmin($admin, 'پیش‌نویسِ پنهانِ فهرست');

        $this->get('/pages')
            ->assertOk()
            ->assertSee('صفحهٔ منتشرشدهٔ فهرست')
            ->assertDontSee('پیش‌نویسِ پنهانِ فهرست');
    }

    public function testViewCountIncrementedOnShow(): void
    {
        $admin = $this->makeAdmin('counter@example.com');
        $row = $this->createPageAsAdmin($admin, 'صفحهٔ شمارش بازدید');
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/publish')->assertRedirect();

        $this->get('/pages/' . $row['slug'])->assertOk();

        $fresh = DB::table('content_pages')->where('id', $row['id'])->first();
        self::assertNotNull($fresh);
        self::assertSame(1, (int) $fresh['view_count']);
    }

    public function testXssSanitizedInPublicOutput(): void
    {
        $admin = $this->makeAdmin('xss@example.com');
        $this->actingAs($admin)->post('/admin/pages', [
            'title' => 'صفحهٔ آلوده',
            'body' => '<script>alert(666)</script> متن سالمِ صفحه',
        ])->assertRedirect();

        $row = DB::table('content_pages')->where('title', 'صفحهٔ آلوده')->first();
        self::assertNotNull($row);
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/publish')->assertRedirect();

        $page = $this->get('/pages/' . $row['slug']);
        $page->assertOk();
        self::assertStringNotContainsString('<script>alert(666)</script>', $page->content());
        $page->assertSee('متن سالمِ صفحه');
    }

    public function testSitemapContainsPublishedSlugs(): void
    {
        $admin = $this->makeAdmin('sitemap@example.com');
        $row = $this->createPageAsAdmin($admin, 'صفحهٔ نقشهٔ سایت');
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/publish')->assertRedirect();

        $response = $this->get('/pages-sitemap.xml');
        $response->assertOk();
        $xml = $response->content();
        self::assertStringContainsString('<urlset', $xml);
        self::assertStringContainsString('/pages/' . $row['slug'], $xml);
    }

    // ─── یکپارچگی با ماژول comment ────────────────────────────────────────

    public function testCommentThreadUnderPublishedPage(): void
    {
        $admin = $this->makeAdmin('moderator@example.com');
        $row = $this->createPageAsAdmin($admin, 'صفحهٔ نظرپذیر');
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/publish')->assertRedirect();
        $slug = (string) $row['slug'];

        // ثبت نظر مهمان از مسیر standalone ماژول comment
        $this->asGuest()->post('/comments/page/' . $slug, [
            'author_name' => 'خوانندهٔ صفحه',
            'author_email' => 'reader@example.com',
            'body' => 'نظر یکپارچه‌سازی زیر صفحهٔ CMS',
        ])->assertRedirect();

        $comment = DB::table('comment_comments')->where('commentable_id', $slug)->first();
        self::assertNotNull($comment);
        self::assertSame('pending', $comment['status']);

        // پیش از تأیید → دیده نمی‌شود
        $this->get('/pages/' . $slug)->assertOk()->assertDontSee('نظر یکپارچه‌سازی زیر صفحهٔ CMS');

        // تأیید در پنل نظرات → عمومی visible
        $this->actingAs($admin)->post('/admin/comments/' . $comment['id'] . '/approve')->assertRedirect();
        $this->get('/pages/' . $slug)->assertOk()->assertSee('نظر یکپارچه‌سازی زیر صفحهٔ CMS');
    }

    public function testCommentsClosedPageHasNoForm(): void
    {
        $admin = $this->makeAdmin('closed@example.com');
        $this->actingAs($admin)->post('/admin/pages', [
            'title' => 'صفحهٔ بستهٔ نظرات',
            'body' => 'بدنه',
            'comments_open' => '0',
        ])->assertRedirect();

        $row = DB::table('content_pages')->where('title', 'صفحهٔ بستهٔ نظرات')->first();
        self::assertNotNull($row);
        $this->actingAs($admin)->post('/admin/pages/' . $row['id'] . '/publish')->assertRedirect();

        $this->get('/pages/' . $row['slug'])
            ->assertOk()
            ->assertDontSee('ارسال نظر');   // content::messages.comment_submit
    }

    // ─── کمکی ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> ردیف content_pages */
    private function createPageAsAdmin(User $admin, string $title): array
    {
        $this->actingAs($admin)->post('/admin/pages', [
            'title' => $title,
            'body' => 'بدنهٔ آزمایشی «' . $title . '»',
            'comments_open' => '1',
        ])->assertRedirect();

        $row = DB::table('content_pages')->where('title', $title)->first();
        self::assertNotNull($row, 'صفحه باید در دیتابیس ساخته می‌شد: ' . $title);

        /** @var array<string, mixed> */
        return $row;
    }

    private function seedRbac(): void
    {
        $permissions = [
            'content.page.view' => 'tavaan.content',
            'content.page.create' => 'tavaan.content',
            'content.page.update' => 'tavaan.content',
            'content.page.publish' => 'tavaan.content',
            'content.page.delete' => 'tavaan.content',
            // برای تست یکپارچگی moderation نظرات
            'comment.view' => 'tavaan.comment',
            'comment.approve' => 'tavaan.comment',
            'comment.delete' => 'tavaan.comment',
        ];

        foreach ($permissions as $key => $group) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, $group);
            }
        }

        if ($this->roles->findRole('admin') === null) {
            $this->roles->create('admin', 'admin');
        }
        foreach (\array_keys($permissions) as $key) {
            $this->roles->grantPermissionToRole('admin', $key);
        }
    }

    private function makeAdmin(string $email): User
    {
        $user = $this->users->create(name: 'مدیر محتوا', email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, 'admin');

        return $user;
    }
}
