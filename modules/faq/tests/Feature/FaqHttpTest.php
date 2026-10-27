<?php

declare(strict_types=1);

namespace Modules\Faq\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E پرسش‌های متداول — پنل /admin/faqs با RBAC واقعی، دسته‌ها،
 * نمایش عمومی /faq با جست‌وجو و فیلتر دسته، رأی «مفید بود» و API JSON.
 */
final class FaqHttpTest extends TestCase
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
        $config->set('session.csrf.except', ['admin/*', 'faq/*', 'api/*']);

        $this->users = $app->make(UserRepositoryInterface::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── دسترسی پنل ────────────────────────────────────────────────────────

    public function testGuestRedirectedFromAdminFaq(): void
    {
        $this->get('/admin/faqs')->assertRedirect();
        $this->get('/admin/faqs/create')->assertRedirect();
        $this->get('/admin/faq-categories')->assertRedirect();
    }

    public function testUserWithoutPermissionForbidden(): void
    {
        $plain = $this->users->create(name: 'کاربر ساده', email: 'plain@example.com', plainPassword: 'test-12345');

        $this->actingAs($plain)->get('/admin/faqs')->assertForbidden();
        $this->actingAs($plain)->get('/admin/faq-categories')->assertForbidden();
    }

    // ─── دسته‌ها ──────────────────────────────────────────────────────────

    public function testAdminCreatesAndUpdatesCategory(): void
    {
        $admin = $this->makeAdmin('cat@example.com');

        $this->actingAs($admin)->post('/admin/faq-categories', [
            'title' => 'شروع کار',
        ])->assertRedirect('/admin/faq-categories');

        $row = DB::table('faq_categories')->where('title', 'شروع کار')->first();
        self::assertNotNull($row);
        self::assertNotSame('', (string) $row['slug']);

        $this->actingAs($admin)->post('/admin/faq-categories/' . $row['id'], [
            'title' => 'شروع سریع',
            'sort_order' => '3',
        ])->assertRedirect('/admin/faq-categories');

        $updated = DB::table('faq_categories')->where('id', $row['id'])->first();
        self::assertNotNull($updated);
        self::assertSame('شروع سریع', (string) $updated['title']);
        self::assertSame(3, (int) $updated['sort_order']);
    }

    public function testCategoryDestroySoftDeletes(): void
    {
        $admin = $this->makeAdmin('catdel@example.com');
        $this->actingAs($admin)->post('/admin/faq-categories', ['title' => 'دستهٔ حذفی'])->assertRedirect();
        $row = DB::table('faq_categories')->where('title', 'دستهٔ حذفی')->first();
        self::assertNotNull($row);

        $this->actingAs($admin)->delete('/admin/faq-categories/' . $row['id'])->assertRedirect('/admin/faq-categories');

        $trashed = DB::table('faq_categories')->where('id', $row['id'])->first();
        self::assertNotNull($trashed);
        self::assertNotNull($trashed['deleted_at']);
    }

    // ─── CRUD پرسش‌ها ─────────────────────────────────────────────────────

    public function testAdminCreatesPublishedFaqVisibleOnPublic(): void
    {
        $admin = $this->makeAdmin('creator@example.com');
        $category = $this->createCategoryAsAdmin($admin, 'عمومی');

        $this->actingAs($admin)->post('/admin/faqs', [
            'question' => 'چگونه وارد شوم؟',
            'answer' => 'از دکمهٔ **ورود** استفاده کنید.',
            'category_id' => $category['id'],
            'status' => 'published',
        ])->assertRedirect();

        $row = DB::table('faq_items')->where('question', 'چگونه وارد شوم؟')->first();
        self::assertNotNull($row);
        self::assertSame('published', (string) $row['status']);
        self::assertSame((string) $category['id'], (string) $row['category_id']);
        self::assertStringContainsString('<strong>', (string) $row['answer']);

        $this->get('/faq')->assertOk()
            ->assertSee('چگونه وارد شوم؟')
            ->assertSee('عمومی');
    }

    public function testDraftFaqHiddenFromPublic(): void
    {
        $admin = $this->makeAdmin('draft@example.com');
        $this->createFaqAsAdmin($admin, 'پرسش پیش‌نویس پنهان؟', status: 'draft');

        $this->get('/faq')->assertOk()->assertDontSee('پرسش پیش‌نویس پنهان؟');
    }

    public function testPublishArchiveDraftLifecycleViaAdmin(): void
    {
        $admin = $this->makeAdmin('lifecycle@example.com');
        $row = $this->createFaqAsAdmin($admin, 'پرسش چرخهٔ وضعیت؟');
        $id = (string) $row['id'];

        $this->actingAs($admin)->post('/admin/faqs/' . $id . '/publish')->assertRedirect();
        self::assertSame('published', (string) DB::table('faq_items')->where('id', $id)->first()['status']);
        $this->get('/faq')->assertOk()->assertSee('پرسش چرخهٔ وضعیت؟');

        $this->actingAs($admin)->post('/admin/faqs/' . $id . '/archive')->assertRedirect();
        self::assertSame('archived', (string) DB::table('faq_items')->where('id', $id)->first()['status']);
        $this->get('/faq')->assertOk()->assertDontSee('پرسش چرخهٔ وضعیت؟');

        $this->actingAs($admin)->post('/admin/faqs/' . $id . '/draft')->assertRedirect();
        self::assertSame('draft', (string) DB::table('faq_items')->where('id', $id)->first()['status']);
    }

    public function testInvalidCreateRedirectsBackToForm(): void
    {
        $admin = $this->makeAdmin('invalid@example.com');

        $this->actingAs($admin)->post('/admin/faqs', [
            'question' => '',
            'answer' => 'پاسخ بدون پرسش',
        ])->assertRedirect('/admin/faqs/create');

        self::assertFalse(DB::table('faq_items')->where('answer', 'پاسخ بدون پرسش')->exists());
    }

    public function testDestroySoftDeletesThenForceDeletes(): void
    {
        $admin = $this->makeAdmin('deleter@example.com');
        $row = $this->createFaqAsAdmin($admin, 'پرسش حذف‌شدنی؟', status: 'published');
        $id = (string) $row['id'];

        $this->actingAs($admin)->delete('/admin/faqs/' . $id)->assertRedirect('/admin/faqs');
        $trashed = DB::table('faq_items')->where('id', $id)->first();
        self::assertNotNull($trashed);
        self::assertNotNull($trashed['deleted_at']);
        $this->get('/faq')->assertOk()->assertDontSee('پرسش حذف‌شدنی؟');

        // حذف دوم روی trash‌شده → حذف سخت
        $this->actingAs($admin)->delete('/admin/faqs/' . $id)->assertRedirect('/admin/faqs');
        self::assertNull(DB::table('faq_items')->where('id', $id)->first());
    }

    // ─── نمای عمومی: جست‌وجو و فیلتر دسته ────────────────────────────────

    public function testSearchFiltersPublicItems(): void
    {
        $admin = $this->makeAdmin('search@example.com');
        $this->createFaqAsAdmin($admin, 'راه‌اندازی اولیهٔ سامانه چگونه است؟', status: 'published');
        $this->createFaqAsAdmin($admin, 'روش بازیابی رمز عبور چیست؟', status: 'published');

        $this->get('/faq?q=' . \rawurlencode('رمز عبور'))->assertOk()
            ->assertSee('روش بازیابی رمز عبور چیست؟')
            ->assertDontSee('راه‌اندازی اولیهٔ سامانه چگونه است؟');
    }

    public function testCategoryFilterShowsOnlyCategoryItems(): void
    {
        $admin = $this->makeAdmin('filter@example.com');
        $catA = $this->createCategoryAsAdmin($admin, 'دستهٔ الف');
        $catB = $this->createCategoryAsAdmin($admin, 'دستهٔ ب');
        $this->createFaqAsAdmin($admin, 'پرسش مربوط به الف؟', status: 'published', categoryId: (string) $catA['id']);
        $this->createFaqAsAdmin($admin, 'پرسش مربوط به ب؟', status: 'published', categoryId: (string) $catB['id']);

        $this->get('/faq?cat=' . \rawurlencode((string) $catA['slug']))->assertOk()
            ->assertSee('پرسش مربوط به الف؟')
            ->assertDontSee('پرسش مربوط به ب؟');
    }

    // ─── رأی «مفید بود» ───────────────────────────────────────────────────

    public function testHelpfulVoteWebOncePerSession(): void
    {
        $admin = $this->makeAdmin('helpful@example.com');
        $row = $this->createFaqAsAdmin($admin, 'پرسش رأی مفید؟', status: 'published');
        $id = (string) $row['id'];

        $this->post('/faq/' . $id . '/helpful', ['value' => 'yes'])->assertRedirect('/faq');
        self::assertSame(1, (int) DB::table('faq_items')->where('id', $id)->first()['helpful_yes']);

        // رأی دوم در همان نشست شمرده نمی‌شود
        $this->post('/faq/' . $id . '/helpful', ['value' => 'no'])->assertRedirect('/faq');
        $after = DB::table('faq_items')->where('id', $id)->first();
        self::assertNotNull($after);
        self::assertSame(1, (int) $after['helpful_yes']);
        self::assertSame(0, (int) $after['helpful_no']);
    }

    public function testHelpfulVoteJsonAndDuplicate429(): void
    {
        $admin = $this->makeAdmin('helpjson@example.com');
        $row = $this->createFaqAsAdmin($admin, 'پرسش رأی جی‌سان؟', status: 'published');
        $url = '/faq/' . $row['id'] . '/helpful';

        $first = $this->postJson($url, ['value' => 'no']);
        $first->assertOk();
        $json = \json_decode($first->content(), true);
        self::assertSame(1, $json['data']['helpful_no'] ?? 0);

        $second = $this->postJson($url, ['value' => 'yes']);
        $second->assertStatus(429);
    }

    public function testHelpfulInvalidValueRejected(): void
    {
        $admin = $this->makeAdmin('helpbad@example.com');
        $row = $this->createFaqAsAdmin($admin, 'پرسش رأی نامعتبر؟', status: 'published');

        $this->postJson('/faq/' . $row['id'] . '/helpful', ['value' => 'maybe'])->assertStatus(422);
        self::assertSame(0, (int) DB::table('faq_items')->where('id', $row['id'])->first()['helpful_yes']);
    }

    public function testHelpfulOnDraftOrUnknownIs404(): void
    {
        $admin = $this->makeAdmin('help404@example.com');
        $draft = $this->createFaqAsAdmin($admin, 'پرسش پیش‌نویس رأی‌ناپذیر؟', status: 'draft');

        $this->postJson('/faq/' . $draft['id'] . '/helpful', ['value' => 'yes'])->assertStatus(404);
        $this->postJson('/faq/' . \str_repeat('0', 26) . '/helpful', ['value' => 'yes'])->assertStatus(404);
    }

    // ─── امنیت و API ──────────────────────────────────────────────────────

    public function testScriptInAnswerStrippedOnPublicPage(): void
    {
        $admin = $this->makeAdmin('xss@example.com');
        $this->createFaqAsAdmin(
            $admin,
            'پرسش آلوده؟',
            answer: 'پاسخ <script>alert(1)</script> آلوده',
            status: 'published',
        );

        $response = $this->get('/faq');
        $response->assertOk();
        self::assertStringNotContainsString('<script', $response->content());
    }

    public function testApiIndexReturnsPublishedTree(): void
    {
        $admin = $this->makeAdmin('api@example.com');
        $category = $this->createCategoryAsAdmin($admin, 'دستهٔ ای‌پی‌آی');
        $this->createFaqAsAdmin($admin, 'پرسش ای‌پی‌آی؟', status: 'published', categoryId: (string) $category['id']);
        $this->createFaqAsAdmin($admin, 'پرسش پیش‌نویس ای‌پی‌آی؟', status: 'draft');

        $response = $this->getJson('/api/faqs');
        $response->assertOk();
        $json = \json_decode($response->content(), true);
        self::assertIsArray($json);
        self::assertSame(1, $json['data']['total'] ?? 0);

        $found = false;
        foreach ($json['data']['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (($item['question'] ?? '') === 'پرسش ای‌پی‌آی؟') {
                    $found = true;
                    self::assertSame((string) $category['id'], (string) ($item['category_id'] ?? ''));
                }
                self::assertNotSame('پرسش پیش‌نویس ای‌پی‌آی؟', $item['question'] ?? '');
            }
        }
        self::assertTrue($found, 'پرسش منتشرشده باید در groups بود');
    }

    // ─── کمکی ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> ردیف faq_categories */
    private function createCategoryAsAdmin(User $admin, string $title): array
    {
        $this->actingAs($admin)->post('/admin/faq-categories', ['title' => $title])->assertRedirect();

        $row = DB::table('faq_categories')->where('title', $title)->first();
        self::assertNotNull($row, 'دسته باید در دیتابیس ساخته می‌شد: ' . $title);

        /** @var array<string, mixed> */
        return $row;
    }

    /** @return array<string, mixed> ردیف faq_items */
    private function createFaqAsAdmin(User $admin, string $question, string $answer = 'پاسخ پیش‌فرض آزمایشی.', string $status = 'draft', ?string $categoryId = null): array
    {
        $payload = [
            'question' => $question,
            'answer' => $answer,
            'status' => $status,
        ];
        if ($categoryId !== null) {
            $payload['category_id'] = $categoryId;
        }

        $this->actingAs($admin)->post('/admin/faqs', $payload)->assertRedirect();

        $row = DB::table('faq_items')->where('question', $question)->first();
        self::assertNotNull($row, 'پرسش باید در دیتابیس ساخته می‌شد: ' . $question);

        /** @var array<string, mixed> */
        return $row;
    }

    private function seedRbac(): void
    {
        foreach (['faq.view', 'faq.create', 'faq.update', 'faq.publish', 'faq.delete'] as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.faq');
            }
        }

        if ($this->roles->findRole('admin') === null) {
            $this->roles->create('admin', 'admin');
        }
        foreach (['faq.view', 'faq.create', 'faq.update', 'faq.publish', 'faq.delete'] as $key) {
            $this->roles->grantPermissionToRole('admin', $key);
        }
    }

    private function makeAdmin(string $email): User
    {
        $user = $this->users->create(name: 'مدیر پرسش‌ها', email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, 'admin');

        return $user;
    }
}
