<?php

declare(strict_types=1);

namespace Modules\Poll\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E نظرسنجی — پنل /admin/polls با RBAC واقعی، نمایش عمومی /polls،
 * رأی‌گیری وب/JSON، یکتایی رأی و قفل گزینه‌ها.
 */
final class PollHttpTest extends TestCase
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
        $config->set('session.csrf.except', ['admin/*', 'polls/*', 'api/*']);
        $config->set('poll.results', 'after_vote');

        $this->users = $app->make(UserRepositoryInterface::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── دسترسی پنل ────────────────────────────────────────────────────────

    public function testGuestRedirectedFromAdminPolls(): void
    {
        $this->get('/admin/polls')->assertRedirect();
        $this->get('/admin/polls/create')->assertRedirect();
    }

    public function testUserWithoutPermissionForbidden(): void
    {
        $plain = $this->users->create(name: 'کاربر ساده', email: 'plain@example.com', plainPassword: 'test-12345');

        $this->actingAs($plain)->get('/admin/polls')->assertForbidden();
    }

    // ─── CRUD مدیریت ───────────────────────────────────────────────────────

    public function testAdminCreatesPollWithStatusOpen(): void
    {
        $admin = $this->makeAdmin('creator@example.com');

        $this->actingAs($admin)->post('/admin/polls', [
            'question' => 'محبوب‌ترین زبان برنامه‌نویسی؟',
            'options' => ['PHP', 'Python', 'JavaScript'],
            'status' => 'open',
        ])->assertRedirect();

        $row = DB::table('poll_polls')->where('question', 'محبوب‌ترین زبان برنامه‌نویسی؟')->first();
        self::assertNotNull($row);
        self::assertSame('open', $row['status']);
        self::assertNotSame('', (string) $row['slug']);

        $options = \json_decode((string) $row['options'], true);
        self::assertSame(['PHP', 'Python', 'JavaScript'], $options);

        // عمومی قابل مشاهده است
        $this->get('/polls')->assertOk()->assertSee('محبوب‌ترین زبان برنامه‌نویسی؟');
    }

    public function testDraftPollHiddenFromPublic(): void
    {
        $admin = $this->makeAdmin('draft@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش پیش‌نویس پنهان؟', status: 'draft');

        $this->get('/polls')->assertOk()->assertDontSee('پرسش پیش‌نویس پنهان؟');
        $this->get('/polls/' . $row['slug'])->assertStatus(404);
    }

    public function testCreateWithOneOptionRedirectsBack(): void
    {
        $admin = $this->makeAdmin('bad@example.com');

        $this->actingAs($admin)->post('/admin/polls', [
            'question' => 'نظرسنجی خراب',
            'options' => ['فقط یک گزینه', '', ''],
        ])->assertRedirect('/admin/polls/create');

        self::assertFalse(DB::table('poll_polls')->where('question', 'نظرسنجی خراب')->exists());
    }

    public function testOpenAndCloseViaAdmin(): void
    {
        $admin = $this->makeAdmin('toggle@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش باز/بسته؟');
        $id = (string) $row['id'];

        $this->actingAs($admin)->post('/admin/polls/' . $id . '/open')->assertRedirect();
        self::assertSame('open', (string) DB::table('poll_polls')->where('id', $id)->first()['status']);

        $this->actingAs($admin)->post('/admin/polls/' . $id . '/close')->assertRedirect();
        self::assertSame('closed', (string) DB::table('poll_polls')->where('id', $id)->first()['status']);
    }

    public function testDestroySoftDeletes(): void
    {
        $admin = $this->makeAdmin('deleter@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش حذف‌شدنی؟');

        $this->actingAs($admin)->delete('/admin/polls/' . $row['id'])->assertRedirect('/admin/polls');

        $trashed = DB::table('poll_polls')->where('id', $row['id'])->first();
        self::assertNotNull($trashed);
        self::assertNotNull($trashed['deleted_at']);

        $this->actingAs($admin)->get('/admin/polls')->assertOk()->assertDontSee('پرسش حذف‌شدنی؟');
    }

    // ─── رأی‌گیری وب ──────────────────────────────────────────────────────

    public function testGuestVotesAndSeesResults(): void
    {
        $admin = $this->makeAdmin('webvote@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش رأی وب؟', status: 'open');
        $slug = (string) $row['slug'];

        // فرم رأی نمایش داده می‌شود
        $this->get('/polls/' . $slug)->assertOk()->assertSee('گزینهٔ یک');

        // ثبت رأی مهمان (تک‌انتخابی)
        $this->post('/polls/' . $slug . '/vote', ['option' => '1'])->assertRedirect('/polls/' . $slug);

        $vote = DB::table('poll_votes')->where('poll_id', $row['id'])->first();
        self::assertNotNull($vote);
        self::assertSame(1, (int) $vote['option_index']);
        self::assertNull($vote['user_id']);
        self::assertNotNull($vote['ip_hash']);

        // نتیجه پس از رأی visible است (after_vote)
        $this->get('/polls/' . $slug)->assertOk()->assertSee('نتایج');
    }

    public function testSecondVoteRejected(): void
    {
        $admin = $this->makeAdmin('dedupe@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش یکتایی؟', status: 'open');
        $slug = (string) $row['slug'];

        $this->post('/polls/' . $slug . '/vote', ['option' => '0'])->assertRedirect();
        $this->post('/polls/' . $slug . '/vote', ['option' => '1'])->assertRedirect();

        self::assertSame(1, DB::table('poll_votes')->where('poll_id', $row['id'])->count());
    }

    public function testLoggedInUserVoteRecordedWithUserId(): void
    {
        $admin = $this->makeAdmin('uservote@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش رأی کاربر؟', status: 'open', multiple: true);

        $voter = $this->users->create(name: 'رأی‌دهنده', email: 'voter@example.com', plainPassword: 'test-12345');
        $this->actingAs($voter)->post('/polls/' . $row['slug'] . '/vote', [
            'options' => ['0', '2'],
        ])->assertRedirect();

        $votes = DB::table('poll_votes')->where('poll_id', $row['id'])->get();
        self::assertCount(2, $votes);
        foreach ($votes as $vote) {
            self::assertSame((int) $voter->id, (int) $vote['user_id']);
        }
    }

    public function testVoteOnClosedPollRejected(): void
    {
        $admin = $this->makeAdmin('closedvote@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش بسته؟', status: 'open');
        $this->actingAs($admin)->post('/admin/polls/' . $row['id'] . '/close')->assertRedirect();

        $this->post('/polls/' . $row['slug'] . '/vote', ['option' => '0'])->assertRedirect();
        self::assertSame(0, DB::table('poll_votes')->where('poll_id', $row['id'])->count());

        // نتایج نظرسنجی بسته بدون رأی‌دادن هم visible است
        $this->get('/polls/' . $row['slug'])->assertOk()->assertSee('نتایج');
    }

    // ─── JSON API ─────────────────────────────────────────────────────────

    public function testApiShowReturnsJson(): void
    {
        $admin = $this->makeAdmin('api@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش ای‌پی‌آی؟', status: 'open');

        $response = $this->getJson('/api/polls/' . $row['slug']);
        $response->assertOk();
        $json = \json_decode($response->content(), true);
        self::assertIsArray($json);
        self::assertSame('پرسش ای‌پی‌آی؟', $json['data']['question'] ?? '');
        self::assertSame('open', $json['data']['status'] ?? '');
        self::assertTrue($json['data']['votable'] ?? false);
        self::assertNull($json['data']['results'] ?? 'x');   // هنوز رأی نداده → نتایج نیست
    }

    public function testApiVoteReturnsJsonCreatedThen422OnDuplicate(): void
    {
        $admin = $this->makeAdmin('apivote@example.com');
        $row = $this->createPollAsAdmin($admin, 'پرسش رأی جی‌سان؟', status: 'open');
        $url = '/api/polls/' . $row['slug'] . '/vote';

        $first = $this->postJson($url, ['option' => 2]);
        $first->assertStatus(201);
        $json = \json_decode($first->content(), true);
        self::assertSame(1, $json['data']['votes'] ?? 0);
        self::assertSame(1, $json['data']['results']['total'] ?? 0);

        $second = $this->postJson($url, ['option' => 1]);
        $second->assertStatus(422);
        $errors = \json_decode($second->content(), true);
        self::assertArrayHasKey('errors', $errors);
    }

    public function testUnknownSlugIs404(): void
    {
        $this->get('/polls/ناشناخته-کامل')->assertStatus(404);
        $this->getJson('/api/polls/no-such-poll')->assertStatus(404);
    }

    // ─── کمکی ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> ردیف poll_polls */
    private function createPollAsAdmin(User $admin, string $question, string $status = 'draft', bool $multiple = false): array
    {
        $payload = [
            'question' => $question,
            'options' => ['گزینهٔ یک', 'گزینهٔ دو', 'گزینهٔ سه'],
            'status' => $status,
        ];
        if ($multiple) {
            $payload['multiple'] = '1';
        }

        $this->actingAs($admin)->post('/admin/polls', $payload)->assertRedirect();

        $row = DB::table('poll_polls')->where('question', $question)->first();
        self::assertNotNull($row, 'نظرسنجی باید در دیتابیس ساخته می‌شد: ' . $question);

        /** @var array<string, mixed> */
        return $row;
    }

    private function seedRbac(): void
    {
        foreach (['poll.view', 'poll.create', 'poll.update', 'poll.open', 'poll.delete'] as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.poll');
            }
        }

        if ($this->roles->findRole('admin') === null) {
            $this->roles->create('admin', 'admin');
        }
        foreach (['poll.view', 'poll.create', 'poll.update', 'poll.open', 'poll.delete'] as $key) {
            $this->roles->grantPermissionToRole('admin', $key);
        }
    }

    private function makeAdmin(string $email): User
    {
        $user = $this->users->create(name: 'مدیر نظرسنجی', email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, 'admin');

        return $user;
    }
}
