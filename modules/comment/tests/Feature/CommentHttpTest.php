<?php

declare(strict_types=1);

namespace Modules\Comment\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E نظرات standalone — ثبت عمومی، moderation در پنل، API JSON.
 *
 * مسیر واقعی HTTP با RBAC واقعی (نقش/مجوز DB) + SQLite in-memory.
 */
final class CommentHttpTest extends TestCase
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

    // ─── ثبت عمومی نظر ─────────────────────────────────────────────────────

    public function testGuestSubmitsCommentOnRegisteredType(): void
    {
        $response = $this->post('/comments/page/test-page-1', [
            'author_name' => 'خوانندهٔ مهمان',
            'author_email' => 'guest@example.com',
            'body' => 'نظر بسیار خوبی بود!',
        ]);
        $response->assertRedirect();

        $row = DB::table('comment_comments')->where('body', 'نظر بسیار خوبی بود!')->first();
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertSame('page', $row['commentable_type']);
        self::assertSame('test-page-1', $row['commentable_id']);
    }

    public function testPendingCommentNotVisibleInThread(): void
    {
        $this->post('/comments/page/test-page-2', [
            'author_name' => 'مهمان',
            'author_email' => 'g2@example.com',
            'body' => 'نظر در انتظار مشاهده',
        ])->assertRedirect();

        $page = $this->get('/comments/page/test-page-2');
        $page->assertOk();
        $page->assertDontSee('نظر در انتظار مشاهده');
    }

    public function testUnregisteredTypeRejected(): void
    {
        // فرم وب → redirect با flash (بدون رکورد جدید)
        $this->post('/comments/evil_type/x-1', [
            'author_name' => 'مهمان',
            'author_email' => 'g3@example.com',
            'body' => 'تلاش برای ثبت نظر روی نوع ثبت‌نشده',
        ])->assertRedirect();

        self::assertFalse(DB::table('comment_comments')->where('commentable_type', 'evil_type')->exists());

        // کلاینت JSON → 422 با ساختار خطا
        $this->postJson('/comments/evil_type/x-1', [
            'author_name' => 'مهمان',
            'author_email' => 'g3@example.com',
            'body' => 'تلاش برای ثبت نظر روی نوع ثبت‌نشده',
        ])->assertStatus(422);
    }

    public function testXssInCommentNeutralized(): void
    {
        $this->post('/comments/page/xss-page', [
            'author_name' => '<script>bad</script>',
            'author_email' => 'xss@example.com',
            'body' => '<script>alert(1)</script> متن سالم',
        ])->assertRedirect();

        $row = DB::table('comment_comments')->where('commentable_id', 'xss-page')->first();
        self::assertNotNull($row);
        $id = (string) $row['id'];

        // تأیید و بررسی رندر
        $admin = $this->makeAdmin('mod@example.com');
        $this->actingAs($admin)->post('/admin/comments/' . $id . '/approve')->assertRedirect();

        $page = $this->get('/comments/page/xss-page');
        $body = $page->content();
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;', $body);
    }

    // ─── moderation در پنل ─────────────────────────────────────────────────

    public function testAdminApprovesAndCommentBecomesVisible(): void
    {
        $this->post('/comments/page/mod-page', [
            'author_name' => 'مهمان',
            'author_email' => 'm1@example.com',
            'body' => 'نظر نیازمند بررسی مدیر',
        ])->assertRedirect();

        $row = DB::table('comment_comments')->where('body', 'نظر نیازمند بررسی مدیر')->first();
        self::assertNotNull($row);
        $id = (string) $row['id'];

        // بدون ورود → redirect
        $this->post('/admin/comments/' . $id . '/approve')->assertRedirect();

        // کاربر بدون مجوز → 403
        $plain = $this->users->create(name: 'کاربر ساده', email: 'plain@example.com', plainPassword: 'test-12345');
        $this->actingAs($plain)->post('/admin/comments/' . $id . '/approve')->assertForbidden();

        // مدیر تأیید می‌کند
        $admin = $this->makeAdmin('admin-c@example.com');
        $this->actingAs($admin)->post('/admin/comments/' . $id . '/approve')->assertRedirect('/admin/comments');

        $fresh = DB::table('comment_comments')->where('id', $id)->first();
        self::assertNotNull($fresh);
        self::assertSame('approved', $fresh['status']);

        // حالا عمومی visible است
        $page = $this->get('/comments/page/mod-page');
        $page->assertOk();
        $page->assertSee('نظر نیازمند بررسی مدیر');
    }

    public function testAdminQueueShowsStatsAndFilters(): void
    {
        $this->post('/comments/page/stat-page', [
            'author_name' => 'آمارگیر',
            'author_email' => 'stat@example.com',
            'body' => 'نظر برای آمار پنل',
        ])->assertRedirect();

        $admin = $this->makeAdmin('admin-s@example.com');
        $page = $this->actingAs($admin)->get('/admin/comments');
        $page->assertOk();
        $page->assertSee('صف بررسی');
        $page->assertSee('نظر برای آمار پنل');
    }

    public function testAdminDeletesComment(): void
    {
        $this->post('/comments/page/del-page', [
            'author_name' => 'حذف‌شونده',
            'author_email' => 'del@example.com',
            'body' => 'نظری که حذف خواهد شد',
        ])->assertRedirect();

        $row = DB::table('comment_comments')->where('body', 'نظری که حذف خواهد شد')->first();
        self::assertNotNull($row);

        $admin = $this->makeAdmin('admin-d@example.com');
        $this->actingAs($admin)->delete('/admin/comments/' . $row['id'])->assertRedirect('/admin/comments');

        self::assertNull(DB::table('comment_comments')->where('id', $row['id'])->first());
    }

    // ─── API JSON ──────────────────────────────────────────────────────────

    public function testApiReturnsThreadJson(): void
    {
        $this->post('/comments/page/api-page', [
            'author_name' => 'ای‌پی‌آی',
            'author_email' => 'api@example.com',
            'body' => 'نظر برای خروجی جی‌سان',
        ])->assertRedirect();

        $row = DB::table('comment_comments')->where('body', 'نظر برای خروجی جی‌سان')->first();
        self::assertNotNull($row);
        DB::table('comment_comments')->where('id', $row['id'])->update(['status' => 'approved']);

        $response = $this->getJson('/api/comments/page/api-page');
        $response->assertOk();
        $json = \json_decode($response->content(), true);
        self::assertIsArray($json);
        self::assertSame('page', $json['meta']['type'] ?? '');
        self::assertSame(1, $json['meta']['count'] ?? 0);
        self::assertSame('ای‌پی‌آی', $json['data'][0]['author_name'] ?? '');
    }

    public function testApiSubmitReturnsJsonCreated(): void
    {
        $response = $this->postJson('/api/comments/page/api-submit', [
            'author_name' => 'کلاینت جی‌سان',
            'author_email' => 'client@example.com',
            'body' => 'ثبت نظر از راه API',
        ]);
        $response->assertStatus(201);
        $json = \json_decode($response->content(), true);
        self::assertSame('pending', $json['data']['status'] ?? '');
    }

    // ─── کمکی ──────────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        foreach (['comment.view', 'comment.approve', 'comment.delete'] as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.comment');
            }
        }

        if ($this->roles->findRole('admin') === null) {
            $this->roles->create('admin', 'admin');
        }
        foreach (['comment.view', 'comment.approve', 'comment.delete'] as $key) {
            $this->roles->grantPermissionToRole('admin', $key);
        }
    }

    private function makeAdmin(string $email): User
    {
        $user = $this->users->create(name: 'مدیر', email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, 'admin');

        return $user;
    }
}
