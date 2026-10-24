<?php

declare(strict_types=1);

namespace Modules\Forum\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Modules\Forum\Application\UseCases\ForumWriteService;
use Modules\Forum\Application\UseCases\PostService;
use Modules\Forum\Application\UseCases\ProfileService;
use Modules\Forum\Application\UseCases\ThreadService;
use Modules\Forum\Domain\Entities\Forum;
use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ProfileRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Facade\Facades\Broadcast;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Engine\Realtime\RealtimeManager;
use Tavaan\Engine\Realtime\Sse\SseStreamer;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول انجمن — سناریوهای DOC-094 §8 (E2E-01..07) + AC-FORUM.
 *
 * مسیر واقعی HTTP درون‌فرایندی + SQLite in-memory + RBAC واقعی (نقش/مجوز DB).
 */
final class ForumE2ETest extends TestCase
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
        $config->set('session.csrf.except', ['admin/*', 'threads', 'threads/*', 'posts/*', 'members/*']);
        // 🔒 ایندکس native جست‌وجو: مسیر موقت یکتا — سند کهنه از اجرای قبلی
        // تست‌ها را قطعی می‌کند (index در فرایند جاری ساخته می‌شود).
        $searchTmp = \sys_get_temp_dir() . '/tvn-forum-search-' . \getmypid() . '-' . \random_int(10000, 99999);
        $config->set('search.native.path', $searchTmp);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── E2E-01: عضو موضوع می‌سازد → دیگران پاسخ می‌دهند ──────────────────

    public function testE2E01_MemberCreatesThreadOthersReply(): void
    {
        $forum = $this->forum('گفت‌وجوی عمومی', 'general');
        $alice = $this->member('alice@forum.test', 'آلیس');
        $bob = $this->member('bob@forum.test', 'بابک');

        // ۱) عضو موضوع می‌سازد
        $this->actingAs($alice)->post('/threads', [
            'forum_id' => $forum->id(),
            'title' => 'معرفی خودتان به انجمن',
            'body' => "سلام! من آلیس هستم و تازه وارد انجمن شدم.\n\n**خوشحالم که اینجام.**",
        ])->assertRedirect();

        /** @var ThreadRepositoryInterface $threads */
        $threads = $this->app()->make(ThreadRepositoryInterface::class);
        $thread = $threads->findBySlug('معرفی-خودتان-به-انجمن');
        self::assertNotNull($thread, 'موضوع با slug فارسی ساخته نشد');

        // پست اول = بدنهٔ موضوع
        /** @var PostRepositoryInterface $posts */
        $posts = $this->app()->make(PostRepositoryInterface::class);
        $first = $posts->firstPostOfThread($thread->id());
        self::assertNotNull($first);
        self::assertStringContainsString('تازه وارد انجمن شدم', $first->body());
        self::assertSame(0, $thread->replyCount(), 'پست اول reply شمرده نمی‌شود');

        // ۲) عضو دیگر پاسخ می‌دهد
        $this->actingAs($bob)->post('/threads/' . $thread->id() . '/posts', [
            'body' => 'خوش آمدی آلیس! 🎉',
        ])->assertRedirect();

        $fresh = $threads->findById($thread->id());
        self::assertSame(1, $fresh?->replyCount());

        // ۳) پاسخ در صفحهٔ موضوع دیده می‌شود
        $page = $this->get('/threads/' . $thread->slug());
        $page->assertOk();
        $page->assertSee('خوش آمدی آلیس');
        $page->assertSee('معرفی خودتان به انجمن');

        // ۴) آمار تالار همگام شد
        $freshForum = $this->app()->make(\Modules\Forum\Domain\Repositories\ForumRepositoryInterface::class)
            ->findById((int) $forum->id());
        self::assertSame(1, $freshForum?->threadCount());
        self::assertSame(2, $freshForum?->postCount());

        // ۵) نویسندهٔ موضوع خودکار مشترک شد + اعلان پاسخ (database) ثبت شد
        self::assertTrue(DB::table('forum_subscriptions')
            ->where('user_id', $alice->id)
            ->where('thread_id', $thread->id())
            ->exists());
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $alice->id)
            ->exists(), 'اعلان پاسخ به نویسندهٔ موضوع ثبت نشد');
    }

    // ─── E2E-02: رأی‌دهی + محاسبهٔ score ─────────────────────────────────

    public function testE2E02_VotingAndScore(): void
    {
        [$forum, $thread, $firstPost] = $this->threadWithFirstPost('آلیس', 'alice2@forum.test');
        $bob = $this->member('bob2@forum.test', 'بابک');
        $carol = $this->member('carol2@forum.test', 'کارول');

        /** @var ProfileRepositoryInterface $profiles */
        $profiles = $this->app()->make(ProfileRepositoryInterface::class);
        $aliceId = (int) $firstPost->authorId();

        // ۱) رأی مثبت بابک → score=1، karma آلیس +۲
        $this->actingAs($bob)->post('/posts/' . $firstPost->id() . '/vote', ['value' => 1])->assertRedirect();

        $post = $this->app()->make(PostRepositoryInterface::class)->findById($firstPost->id());
        self::assertSame(1, $post?->voteScore());
        $karmaAfterUp = $profiles->findByUserId($aliceId)?->karma() ?? 0;
        self::assertGreaterThanOrEqual(2, $karmaAfterUp);

        // ۲) رأی تکراری بابک → toggle (لغو) → score=0
        $this->actingAs($bob)->post('/posts/' . $firstPost->id() . '/vote', ['value' => 1])->assertRedirect();
        $post = $this->app()->make(PostRepositoryInterface::class)->findById($firstPost->id());
        self::assertSame(0, $post?->voteScore());
        self::assertSame(0, DB::table('forum_votes')
            ->where('post_id', $firstPost->id())
            ->where('user_id', $bob->id)
            ->count());

        // ۳) رأی منفی کارول → score=-1
        $this->actingAs($carol)->post('/posts/' . $firstPost->id() . '/vote', ['value' => -1])->assertRedirect();
        $post = $this->app()->make(PostRepositoryInterface::class)->findById($firstPost->id());
        self::assertSame(-1, $post?->voteScore());

        // ۴) تغییر رأی کارول از -1 به +1 → score=+1
        $this->actingAs($carol)->post('/posts/' . $firstPost->id() . '/vote', ['value' => 1])->assertRedirect();
        $post = $this->app()->make(PostRepositoryInterface::class)->findById($firstPost->id());
        self::assertSame(1, $post?->voteScore());

        // ۵) یکتایی رأی — یک ردیف برای کارول
        self::assertSame(1, DB::table('forum_votes')
            ->where('post_id', $firstPost->id())
            ->where('user_id', $carol->id)
            ->count());
    }

    public function testE2E02b_CannotVoteOwnPost(): void
    {
        $alice = $this->member('alice3@forum.test', 'آلیس');
        $forum = $this->forum('عمومی خودرأی', 'general-selfvote');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, [
            'title' => 'موضوع رأی به خود',
            'body' => 'بدنهٔ پست اول.',
        ]);

        // رأی به پست خود → policy رد می‌کند (403)
        $this->actingAs($alice)->post('/posts/' . $result['post']->id() . '/vote', ['value' => 1])->assertForbidden();
    }

    // ─── E2E-03: پاسخ برگزیده ─────────────────────────────────────────────

    public function testE2E03_MarkSolution(): void
    {
        $alice = $this->member('alice4@forum.test', 'آلیس');
        $bob = $this->member('bob4@forum.test', 'بابک');
        $carol = $this->member('carol4@forum.test', 'کارول');

        $forum = $this->forum('پشتیبانی', 'support');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, [
            'title' => 'چطور کش را پاک کنیم؟',
            'body' => 'دستور پاک‌کردن کش چیست؟',
        ]);
        $thread = $result['thread'];

        /** @var PostService $postService */
        $postService = $this->app()->make(PostService::class);
        $answer = $postService->createPost($thread, $bob->id, 'از `tvn cache:clear` استفاده کنید.');

        // ۱) کاربر غریبه (نه نویسندهٔ موضوع، نه moderator) نمی‌تواند برگزیده کند → 403
        $this->actingAs($carol)->post('/posts/' . $answer->id() . '/mark-solution')->assertForbidden();

        // ۲) نویسندهٔ موضوع برگزیده می‌کند
        $this->actingAs($alice)->post('/posts/' . $answer->id() . '/mark-solution')->assertRedirect();

        $freshAnswer = $this->app()->make(PostRepositoryInterface::class)->findById($answer->id());
        self::assertTrue($freshAnswer?->isSolution());

        $freshThread = $this->app()->make(ThreadRepositoryInterface::class)->findById($thread->id());
        self::assertTrue($freshThread?->isSolved());

        // ۳) karma نویسندهٔ پاسخ افزایش یافت (+۱۵ + ۱ پست)
        $profile = $this->app()->make(ProfileRepositoryInterface::class)->findByUserId($bob->id);
        self::assertGreaterThanOrEqual(16, $profile?->karma());
        self::assertSame(1, $profile?->solutionCount());

        // ۴) اعلان «پاسخ شما برگزیده شد» برای بابک ثبت شد
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $bob->id)
            ->exists());

        // ۵) moderator هم می‌تواند برگزیده را بردارد
        $moderator = $this->member('mod4@forum.test', 'ناظم', role: 'moderator');
        $this->actingAs($moderator)->post('/posts/' . $answer->id() . '/mark-solution')->assertRedirect();

        $afterUnmark = $this->app()->make(PostRepositoryInterface::class)->findById($answer->id());
        self::assertFalse($afterUnmark?->isSolution());
        $threadAfter = $this->app()->make(ThreadRepositoryInterface::class)->findById($thread->id());
        self::assertFalse($threadAfter?->isSolved());
    }

    // ─── E2E-04: moderation — flag → hidden ───────────────────────────────

    public function testE2E04_FlagThenModeratorHides(): void
    {
        $alice = $this->member('alice5@forum.test', 'آلیس');
        $spammer = $this->member('spam5@forum.test', 'اسپمر');
        $moderator = $this->member('mod5@forum.test', 'ناظم', role: 'moderator');

        $forum = $this->forum('عمومی', 'general5');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, [
            'title' => 'موضوع سالم برای تست moderation',
            'body' => 'بدنهٔ سالم.',
        ]);
        $thread = $result['thread'];

        /** @var PostService $postService */
        $postService = $this->app()->make(PostService::class);
        $badPost = $postService->createPost($thread, $spammer->id, 'محتوای آزاردهنده برای گزارش.');

        // ۱) آلیس پست را گزارش می‌کند
        $this->actingAs($alice)->post('/posts/' . $badPost->id() . '/flag', [
            'reason' => 'محتوای نامناسب',
        ])->assertRedirect();

        /** @var FlagRepositoryInterface $flags */
        $flags = $this->app()->make(FlagRepositoryInterface::class);
        self::assertSame(1, $flags->countByStatus(\Modules\Forum\Domain\Enums\FlagStatus::Pending));

        // گزارش تکراری همان کاربر → رد (flash خطا، flag جدید نمی‌سازد)
        $this->actingAs($alice)->post('/posts/' . $badPost->id() . '/flag', ['reason' => 'دوباره'])->assertRedirect();
        self::assertSame(1, $flags->countByStatus(\Modules\Forum\Domain\Enums\FlagStatus::Pending));

        // ۲) moderator صف را می‌بیند
        $queue = $this->actingAs($moderator)->get('/admin/forum/moderation');
        $queue->assertOk();
        $queue->assertSee('محتوای آزاردهنده');

        // ۳) resolve → پست پنهان می‌شود + flag بسته می‌شود
        $flagId = (int) $flags->paginate(\Modules\Forum\Domain\Enums\FlagStatus::Pending, 1, 10)['rows'][0]->id();
        $this->actingAs($moderator)->post('/admin/forum/moderation/' . $flagId . '/resolve')->assertRedirect();

        $hidden = $this->app()->make(PostRepositoryInterface::class)->findById($badPost->id());
        self::assertSame('hidden', $hidden?->status()->value);
        self::assertSame(0, $flags->countByStatus(\Modules\Forum\Domain\Enums\FlagStatus::Pending));
        self::assertSame(1, $flags->countByStatus(\Modules\Forum\Domain\Enums\FlagStatus::Resolved));

        // ۴) moderator جای‌نمای پست پنهان را در صفحهٔ موضوع می‌بیند
        $modPage = $this->actingAs($moderator)->get('/threads/' . $thread->slug());
        $modPage->assertOk();
        $modPage->assertSee('پست توسط moderator پنهان شده');

        // ۵) کاربر عادی (alice) پست پنهان و محتوایش را نمی‌بیند
        // (actingAs آخرین کاربر را نگه می‌دارد → صریحاً alice می‌شویم)
        $page = $this->actingAs($alice)->get('/threads/' . $thread->slug());
        $page->assertOk();
        $page->assertDontSee('محتوای آزاردهنده برای گزارش');
        $page->assertDontSee('پنهان شده است');
    }

    public function testE2E04b_MemberCannotAccessModerationQueue(): void
    {
        $member = $this->member('member6@forum.test', 'عضو');

        $this->actingAs($member)->get('/admin/forum/moderation')->assertForbidden();
        $this->actingAs($member)->get('/admin/forum/forums')->assertForbidden();
    }

    // ─── E2E-05: جست‌وجوی فارسی ───────────────────────────────────────────

    public function testE2E05_PersianSearch(): void
    {
        $alice = $this->member('alice7@forum.test', 'آلیس');
        $forum = $this->forum('فنی', 'tech7');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, [
            'title' => 'پیکربندی سرور پرودیوسن در سال ۱۴۰۵',
            'body' => "## پیکربندی\n\nنصب PHP 8.2 و تنظیم OPcache برای استقرار ۱۴۰۵.",
        ]);
        $thread = $result['thread'];

        // جست‌وجو با واژهٔ فارسی عنوان
        $page = $this->get('/forums/search?q=' . \rawurlencode('پیکربندی'));
        $page->assertOk();
        $page->assertSee('پیکربندی سرور پرودیوسن');

        // جست‌وجو با رقم فارسی → نرمال‌سازی به 1405 و یافتن موضوع
        $page2 = $this->get('/forums/search?q=' . \rawurlencode('۱۴۰۵'));
        $page2->assertOk();
        $page2->assertSee('پیکربندی سرور پرودیوسن');

        // جست‌وجوی متن پست (بدنه)
        $page3 = $this->get('/forums/search?q=' . \rawurlencode('OPcache'));
        $page3->assertOk();
        $page3->assertSee('پیکربندی سرور پرودیوسن');

        self::assertNotNull($thread);
    }

    // ─── E2E-06: Realtime — پست جدید به حاضران (SSE بدون ext) ─────────────

    public function testE2E06_RealtimeBroadcastAndSse(): void
    {
        $alice = $this->member('alice8@forum.test', 'آلیس');
        $bob = $this->member('bob8@forum.test', 'بابک');
        $forum = $this->forum('عمومی', 'general8');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, [
            'title' => 'موضوع realtime',
            'body' => 'بدنهٔ اولیه.',
        ]);
        $thread = $result['thread'];
        $channel = 'forum.thread.' . $thread->id();

        /** @var RealtimeManager $realtime */
        $realtime = $this->app()->make(RealtimeManager::class);
        $broker = $realtime->broker();

        // ۱) پاسخ جدید از راه HTTP → رویداد روی کانال موضوع broadcast می‌شود
        $this->actingAs($bob)->post('/threads/' . $thread->id() . '/posts', [
            'body' => 'پاسخ زنده برای مشترکین SSE',
        ])->assertRedirect();

        $events = $broker->poll([$channel], 0, 0);
        $names = \array_map(static fn (array $e): string => $e['name'], $events);
        self::assertContains('post.created', $names, 'پست جدید روی کانال موضوع broadcast نشد');
        self::assertContains('thread.created', $names);

        // ۲) AC-FORUM-08: SSE بدون ext — streamer رویداد تازه را تحویل می‌دهد
        /** @var SseStreamer $streamer */
        $streamer = $this->app()->make(SseStreamer::class);
        $response = $streamer->response([$channel], maxSeconds: 1);
        Broadcast::publish($channel, 'post.created', ['post_id' => 'X1', 'excerpt' => 'پاسخ دوم']);
        $body = (string) $response->body();
        self::assertStringContainsString('event: post.created', $body);
        self::assertStringContainsString('X1', $body);

        // ۳) endpoint موتور realtime پاسخ SSE سالم می‌دهد (کانال عمومی)
        $sse = $this->get('/events/stream?channels=' . \rawurlencode($channel) . '&max_seconds=1');
        $sse->assertOk();
        self::assertStringContainsString('text/event-stream', (string) $sse->header('Content-Type'));
    }

    // ─── E2E-07: Flood control ────────────────────────────────────────────

    public function testE2E07_FloodControlBlocksRapidPosts(): void
    {
        $alice = $this->member('alice9@forum.test', 'آلیس');
        $bob = $this->member('bob9@forum.test', 'بابک');
        $forum = $this->forum('عمومی', 'general9');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, [
            'title' => 'موضوع flood',
            'body' => 'بدنه.',
        ]);
        $thread = $result['thread'];

        // پاسخ اول بابک → موفق
        $this->actingAs($bob)->post('/threads/' . $thread->id() . '/posts', [
            'body' => 'پاسخ اول بابک',
        ])->assertRedirect();

        // پاسخ دوم بلافاصله → flood control (min_seconds=30 پیش‌فرض) → رد
        $this->actingAs($bob)->post('/threads/' . $thread->id() . '/posts', [
            'body' => 'پاسخ دوم سریع بابک',
        ])->assertRedirect();

        /** @var PostRepositoryInterface $posts */
        $posts = $this->app()->make(PostRepositoryInterface::class);
        $all = $posts->allVisibleForThread($thread->id());
        self::assertCount(2, $all, 'پست دوم باید با flood control رد می‌شد (۱ پست اول + ۱ پاسخ)');

        $fresh = $this->app()->make(ThreadRepositoryInterface::class)->findById($thread->id());
        self::assertSame(1, $fresh?->replyCount());
    }

    // ─── Anti-spam: honeypot + XSS ────────────────────────────────────────

    public function testHoneypotSilentlyBlocksSpamBots(): void
    {
        $alice = $this->member('alice10@forum.test', 'آلیس');
        $bot = $this->member('bot10@forum.test', 'ربات');
        $forum = $this->forum('عمومی', 'general10');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع honeypot', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        // فیلد نامرئی website پر شده → پست ذخیره نمی‌شود
        $this->actingAs($bot)->post('/threads/' . $thread->id() . '/posts', [
            'body' => 'پاسخ ربات اسپم',
            'website' => 'http://spam.example',
        ])->assertRedirect();

        $posts = $this->app()->make(PostRepositoryInterface::class)->allVisibleForThread($thread->id());
        self::assertCount(1, $posts, 'فقط پست اول باید بماند — honeypot پاسخ ربات را گرفت');
    }

    public function testXssInPostBodyIsSanitized(): void
    {
        $alice = $this->member('alice11@forum.test', 'آلیس');
        $bob = $this->member('bob11@forum.test', 'بابک');
        $forum = $this->forum('عمومی', 'general11');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع XSS', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        $this->actingAs($bob)->post('/threads/' . $thread->id() . '/posts', [
            'body' => 'سلام <script>alert("xss")</script> <b>سلامت</b>',
        ])->assertRedirect();

        $posts = $this->app()->make(PostRepositoryInterface::class)->allVisibleForThread($thread->id());
        $reply = $posts[\count($posts) - 1];
        self::assertStringNotContainsString('<script', $reply->body());
        self::assertStringContainsString('سلامت', $reply->body());

        $page = $this->get('/threads/' . $thread->slug());
        $page->assertOk();
        $page->assertDontSee('<script>alert');
    }

    // ─── Mention (@username) با اعلان ─────────────────────────────────────

    public function testMentionNotifiesTargetUser(): void
    {
        $alice = $this->member('alice12@forum.test', 'آلیس');
        $bob = $this->member('bob12@forum.test', 'بابک');
        $carol = $this->member('carol12@forum.test', 'کارول');

        // username دلخواه بابک: beta12
        /** @var ProfileService $profileService */
        $profileService = $this->app()->make(ProfileService::class);
        $profileService->updateProfile($bob->id, ['username' => 'beta12', 'bio' => null, 'signature' => null]);

        $forum = $this->forum('عمومی', 'general12');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع mention', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        /** @var PostService $postService */
        $postService = $this->app()->make(PostService::class);
        $postService->createPost($thread, $carol->id, 'نظر تو چیست @beta12 عزیز؟');

        // اعلان mention برای بابک ثبت شد
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $bob->id)
            ->exists(), 'اعلان mention ثبت نشد');

        // username در پروفایل عمومی قابل مشاهده است
        $page = $this->get('/members/beta12');
        $page->assertOk();
        $page->assertSee('beta12');
    }

    // ─── نقل‌قول (Quote) ──────────────────────────────────────────────────

    public function testQuotePrefillsReplyForm(): void
    {
        $alice = $this->member('alice13@forum.test', 'آلیس');
        $bob = $this->member('bob13@forum.test', 'بابک');
        $forum = $this->forum('عمومی', 'general13');

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع نقل‌قول', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        /** @var PostService $postService */
        $postService = $this->app()->make(PostService::class);
        $answer = $postService->createPost($thread, $bob->id, 'متنی که باید نقل‌قول شود.');

        $page = $this->actingAs($alice)->get('/threads/' . $thread->slug() . '?quote=' . $answer->id());
        $page->assertOk();
        $page->assertSee('نقل‌قول شود');
        $page->assertSee('&gt;');   // blockquote markdown در textarea
    }

    // ─── RBAC: moderator/admin ────────────────────────────────────────────

    public function testModeratorCanLockThreadAndMemberCannot(): void
    {
        $alice = $this->member('alice14@forum.test', 'آلیس');
        $bob = $this->member('bob14@forum.test', 'بابک');
        $moderator = $this->member('mod14@forum.test', 'ناظم', role: 'moderator');

        $forum = $this->forum('عمومی', 'general14');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع قفل', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        // عضو عادی نمی‌تواند قفل کند → 403
        $this->actingAs($bob)->post('/admin/forum/threads/' . $thread->id() . '/lock')->assertForbidden();

        // moderator قفل می‌کند
        $this->actingAs($moderator)->post('/admin/forum/threads/' . $thread->id() . '/lock')->assertRedirect();
        $fresh = $this->app()->make(ThreadRepositoryInterface::class)->findById($thread->id());
        self::assertTrue($fresh?->isLocked());

        // موضوع قفل → پاسخ جدید رد می‌شود
        $this->actingAs($bob)->post('/threads/' . $thread->id() . '/posts', ['body' => 'پاسخ در موضوع قفل'])->assertRedirect();
        $posts = $this->app()->make(PostRepositoryInterface::class)->allVisibleForThread($thread->id());
        self::assertCount(1, $posts);
    }

    public function testAdminManagesForumsAndBansUsers(): void
    {
        $admin = $this->member('admin15@forum.test', 'مدیر', role: 'admin');
        $member = $this->member('member15@forum.test', 'عضو');
        $alice = $this->member('alice15@forum.test', 'آلیس');

        // moderator بدون forum.forum.manage → ساخت تالار 403
        $moderator = $this->member('mod15@forum.test', 'ناظم', role: 'moderator');
        $this->actingAs($moderator)->post('/admin/forum/forums', ['title' => 'تالار غیرمجاز'])->assertForbidden();

        // admin تالار می‌سازد
        $this->actingAs($admin)->post('/admin/forum/forums', [
            'title' => 'تالار اطلاعیه‌ها',
            'description' => 'فقط اطلاعیه‌های رسمی',
        ])->assertRedirect();

        $forum = $this->app()->make(\Modules\Forum\Domain\Repositories\ForumRepositoryInterface::class)
            ->findBySlug('تالار-اطلاعیه-ها');   // slugify: ZWNJ → hyphen
        self::assertNotNull($forum);

        // moderator در پنل موضوعات را می‌بیند (forum.thread.view دارد)
        $this->actingAs($moderator)->get('/admin/forum/threads')->assertOk();

        // admin کاربر ban می‌کند → کاربر ban‌شده نمی‌تواند پاسخ دهد
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع ban', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        $this->actingAs($admin)->post('/admin/forum/users/' . $member->id . '/ban', [
            'days' => 7,
            'reason' => 'نقض قوانین',
        ])->assertRedirect();

        $profile = $this->app()->make(ProfileRepositoryInterface::class)->findByUserId($member->id);
        self::assertTrue($profile?->isCurrentlyBanned());

        $this->actingAs($member)->post('/threads/' . $thread->id() . '/posts', ['body' => 'پاسخ کاربر ban'])->assertRedirect();
        $posts = $this->app()->make(PostRepositoryInterface::class)->allVisibleForThread($thread->id());
        self::assertCount(1, $posts, 'کاربر ban‌شده نباید بتواند پست بگذارد');

        // unban → دوباره می‌تواند
        $this->actingAs($admin)->post('/admin/forum/users/' . $member->id . '/unban')->assertRedirect();
        $this->actingAs($member)->post('/threads/' . $thread->id() . '/posts', ['body' => 'پاسخ پس از unban'])->assertRedirect();
        $posts = $this->app()->make(PostRepositoryInterface::class)->allVisibleForThread($thread->id());
        self::assertCount(2, $posts);
    }

    public function testGuestCanReadButCannotPost(): void
    {
        $alice = $this->member('alice16@forum.test', 'آلیس');
        $forum = $this->forum('عمومی', 'general16');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع عمومی', 'body' => 'بدنهٔ عمومی.']);
        $thread = $result['thread'];

        // خواندن عمومی آزاد است
        $this->get('/forums')->assertOk()->assertSee('عمومی');
        $this->get('/threads/' . $thread->slug())->assertOk()->assertSee('بدنهٔ عمومی');

        // نوشتن بدون احراز هویت → redirect (به ورود)
        $this->post('/threads/' . $thread->id() . '/posts', ['body' => 'پاسخ مهمان'])->assertRedirect();
        $posts = $this->app()->make(PostRepositoryInterface::class)->allVisibleForThread($thread->id());
        self::assertCount(1, $posts);
    }

    // ─── صفحهٔ انجمن + آمار + پروفایل ─────────────────────────────────────

    public function testForumIndexShowsTreeAndStats(): void
    {
        $root = $this->forum('پشتیبانی', 'support17');
        /** @var ForumWriteService $write */
        $write = $this->app()->make(ForumWriteService::class);
        $child = $write->createForum(['title' => 'سؤالات متداول', 'parent_id' => $root->id()]);

        $alice = $this->member('alice17@forum.test', 'آلیس');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $threadService->createThread($child, $alice->id, ['title' => 'موضوع زیرتالار', 'body' => 'بدنه.']);

        $page = $this->get('/forums');
        $page->assertOk();
        $page->assertSee('پشتیبانی');
        $page->assertSee('سؤالات متداول');
        $page->assertSee('موضوع زیرتالار');   // آخرین موضوعات

        // صفحهٔ تالار والد — موضوع زیرتالار را هم نشان می‌دهد (withDescendants)
        $forumPage = $this->get('/forums/support17');
        $forumPage->assertOk();
        $forumPage->assertSee('موضوع زیرتالار');
    }

    public function testSyncCountersCommandRecalculatesStats(): void
    {
        $forum = $this->forum('عمومی', 'general18');
        $alice = $this->member('alice18@forum.test', 'آلیس');
        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $alice->id, ['title' => 'موضوع شمارش', 'body' => 'بدنه.']);
        $thread = $result['thread'];

        // آمار را دستی خراب می‌کنیم
        DB::table('forum_forums')->where('id', $forum->id())->update(['thread_count' => 99, 'post_count' => 99]);
        DB::table('forum_threads')->where('id', $thread->id())->update(['reply_count' => 99]);

        $exitCode = $this->runConsole('forum:sync-counters');
        self::assertSame(0, $exitCode);

        $freshForum = $this->app()->make(\Modules\Forum\Domain\Repositories\ForumRepositoryInterface::class)
            ->findById((int) $forum->id());
        self::assertSame(1, $freshForum?->threadCount());
        self::assertSame(1, $freshForum?->postCount());

        $freshThread = $this->app()->make(ThreadRepositoryInterface::class)->findById($thread->id());
        self::assertSame(0, $freshThread?->replyCount());
    }

    // ─── helperها ─────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        $all = [
            'forum.forum.view', 'forum.forum.manage',
            'forum.thread.view', 'forum.thread.create', 'forum.thread.update',
            'forum.thread.delete', 'forum.thread.lock', 'forum.thread.pin', 'forum.thread.move',
            'forum.post.create', 'forum.post.update', 'forum.post.delete', 'forum.post.hide',
            'forum.post.vote', 'forum.post.solution',
            'forum.flag.create', 'forum.flag.manage',
            'forum.profile.manage', 'forum.user.ban',
        ];
        foreach ($all as $key) {
            $exists = DB::table('tvn_permissions')->where('key', $key)->first() !== null;
            if (!$exists) {
                $this->roles->createPermission($key, 'tavaan.forum');
            }
        }

        $member = [
            'forum.thread.create', 'forum.post.create', 'forum.post.vote',
            'forum.flag.create', 'forum.profile.manage',
        ];
        $moderator = [
            ...$member,
            'forum.thread.view', 'forum.thread.update', 'forum.thread.lock',
            'forum.thread.pin', 'forum.thread.move',
            'forum.post.update', 'forum.post.hide', 'forum.post.solution',
            'forum.flag.manage',
        ];
        $rolePerms = [
            'member' => $member,
            'moderator' => $moderator,
            'admin' => $all,
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

    private function member(string $email, string $name, string $role = 'member'): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    private function forum(string $title, string $slug): Forum
    {
        /** @var ForumWriteService $write */
        $write = $this->app()->make(ForumWriteService::class);

        return $write->createForum(['title' => $title, 'slug' => $slug]);
    }

    /** @return array{0: Forum, 1: Thread, 2: Post} */
    private function threadWithFirstPost(string $authorName, string $authorEmail): array
    {
        $author = $this->member($authorEmail, $authorName);
        $forum = $this->forum('عمومی ' . $authorEmail, 'general-' . \substr(\md5($authorEmail), 0, 8));

        /** @var ThreadService $threadService */
        $threadService = $this->app()->make(ThreadService::class);
        $result = $threadService->createThread($forum, $author->id, [
            'title' => 'موضوع تست برای ' . $authorName,
            'body' => 'بدنهٔ پست اول برای رأی‌گیری.',
        ]);

        return [$forum, $result['thread'], $result['post']];
    }

    private function runConsole(string $command): int
    {
        /** @var \Tavaan\Engine\Console\ConsoleKernel $kernel */
        $kernel = $this->app()->make(\Tavaan\Engine\Console\ConsoleKernel::class);

        return $kernel->call($command);
    }
}
