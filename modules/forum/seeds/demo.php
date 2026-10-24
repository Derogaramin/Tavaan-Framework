<?php

declare(strict_types=1);

/*
 * Seeder دمو — ماژول انجمن (توسط `tvn kit:install forum --with-demo`).
 * قرارداد: callable() — idempotent. وابسته به seed دموی core-user.
 *
 * شامل: تالارهای تو‌در‌تو، پروفایل/username اعضا، موضوع Q&A حل‌شده با
 * رأی و پاسخ برگزیده، موضوع گفت‌وجوی آزاد، یک گزارش در صف moderation
 * و یک اشتراک.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = static fn (DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s.v');

    // ── کاربران دمو (از core-user) ────────────────────────────────────────
    $admin = DB::table('users')->where('email', 'admin@blog.test')->first();
    $author = DB::table('users')->where('email', 'author@blog.test')->first();
    $reader = DB::table('users')->where('email', 'reader@blog.test')->first();
    if ($admin === null || $author === null || $reader === null) {
        return;
    }
    $adminId = (int) $admin['id'];
    $authorId = (int) $author['id'];
    $readerId = (int) $reader['id'];

    // ── پروفایل انجمن (username) ─────────────────────────────────────────
    $profiles = [
        [$adminId, 'admin', 'مدیر انجمن — اینجا را تمیز نگه می‌دارم. 🧹'],
        [$authorId, 'nevisandeh', 'نویسنده و پژوهشگر فناوری.'],
        [$readerId, 'khanandeh', 'فقط تماشا می‌کنم… فعلاً!'],
    ];
    foreach ($profiles as [$userId, $username, $bio]) {
        if (!DB::table('forum_profiles')->where('user_id', $userId)->exists()) {
            DB::table('forum_profiles')->insert([
                'user_id' => $userId,
                'username' => $username,
                'bio' => $bio,
                'signature' => null,
                'karma' => 0,
                'thread_count' => 0,
                'post_count' => 0,
                'solution_count' => 0,
                'is_banned' => 0,
                'banned_until' => null,
                'ban_reason' => null,
                'created_at' => $fmt($now),
                'updated_at' => $fmt($now),
            ]);
        }
    }

    // ── تالارها (تو‌در‌تو) ────────────────────────────────────────────────
    $forumIds = [];
    $forums = [
        ['title' => 'اطلاعیه‌ها', 'slug' => 'announcements', 'parent' => null,
         'description' => 'اخبار و اطلاعیه‌های رسمی انجمن'],
        ['title' => 'گفت‌وجوی آزاد', 'slug' => 'general', 'parent' => null,
         'description' => 'هر موضوعی که به انجمن مربوط است'],
        ['title' => 'پشتیبانی فنی', 'slug' => 'support', 'parent' => null,
         'description' => 'سؤال و پاسخ فنی دربارهٔ فریمورک تَوان'],
        ['title' => 'سؤالات متداول', 'slug' => 'faq', 'parent' => 'support',
         'description' => 'پرسش‌های پرتکرار'],
        ['title' => 'باگ‌ها', 'slug' => 'bugs', 'parent' => 'support',
         'description' => 'گزارش و پیگیری باگ‌ها'],
    ];
    foreach ($forums as $forum) {
        $row = DB::table('forum_forums')->where('slug', $forum['slug'])->first();
        if ($row !== null) {
            $forumIds[$forum['slug']] = (int) $row['id'];
            continue;
        }
        $parentId = $forum['parent'] !== null ? ($forumIds[$forum['parent']] ?? null) : null;
        $forumIds[$forum['slug']] = (int) DB::table('forum_forums')->insertGetId([
            'parent_id' => $parentId,
            'title' => $forum['title'],
            'slug' => $forum['slug'],
            'description' => $forum['description'],
            'position' => 0,
            'is_locked' => $forum['slug'] === 'announcements' ? 0 : 0,
            'thread_count' => 0,
            'post_count' => 0,
            'last_post_id' => null,
            'created_at' => $fmt($now),
            'updated_at' => $fmt($now),
        ]);
    }

    // ── موضوع ۱: خوش‌آمدگویی (اطلاعیه‌ها) ────────────────────────────────
    if (!DB::table('forum_threads')->where('slug', 'be-anjoman-khosh-amadid')->exists()) {
        $threadId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_threads')->insert([
            'id' => $threadId,
            'forum_id' => $forumIds['announcements'],
            'author_id' => $adminId,
            'title' => 'به انجمن خوش آمدید 🎉',
            'slug' => 'be-anjoman-khosh-amadid',
            'is_pinned' => 1,
            'is_locked' => 0,
            'is_solved' => 0,
            'view_count' => 0,
            'reply_count' => 1,
            'last_post_at' => $fmt($now->modify('+2 minutes')),
            'created_at' => $fmt($now),
            'updated_at' => $fmt($now),
        ]);
        $firstId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_posts')->insert([
            'id' => $firstId,
            'thread_id' => $threadId,
            'author_id' => $adminId,
            'parent_id' => null,
            'body' => '<p>انجمن گفت‌وجو با <strong>کیت انجمن تَوان</strong> راه‌اندازی شد.</p><p>قوانین ساده است: مهربان باشید، جست‌وجو کنید، و پاس‌های دقیق بدهید. ✅</p>',
            'vote_score' => 0,
            'is_solution' => 0,
            'status' => 'visible',
            'created_at' => $fmt($now),
            'updated_at' => $fmt($now),
        ]);
        $replyId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_posts')->insert([
            'id' => $replyId,
            'thread_id' => $threadId,
            'author_id' => $readerId,
            'parent_id' => null,
            'body' => '<p>سلام! خوشحالم که اینجایم. @nevisandeh را هم که دیدم خوش‌آمد می‌گویم.</p>',
            'vote_score' => 1,
            'is_solution' => 0,
            'status' => 'visible',
            'created_at' => $fmt($now->modify('+2 minutes')),
            'updated_at' => $fmt($now->modify('+2 minutes')),
        ]);
        DB::table('forum_votes')->insert([
            'post_id' => $replyId, 'user_id' => $adminId, 'value' => 1, 'created_at' => $fmt($now),
        ]);
        DB::table('forum_subscriptions')->insert([
            'user_id' => $adminId, 'thread_id' => $threadId, 'created_at' => $fmt($now),
        ]);
        DB::table('forum_profiles')->where('user_id', $adminId)->update(['thread_count' => 1, 'post_count' => 1]);
        DB::table('forum_profiles')->where('user_id', $readerId)->update(['post_count' => 1, 'karma' => 2]);
    }

    // ── موضوع ۲: Q&A حل‌شده (سؤالات متداول) ─────────────────────────────
    if (!DB::table('forum_threads')->where('slug', 'moshkel-e-encoding-farsi')->exists()) {
        $threadId = (string) Tavaan\Support\Ulid::generate();
        $created = $now->modify('-1 day');
        DB::table('forum_threads')->insert([
            'id' => $threadId,
            'forum_id' => $forumIds['faq'],
            'author_id' => $readerId,
            'title' => 'مشکل encoding متن فارسی در خروجی JSON',
            'slug' => 'moshkel-e-encoding-farsi',
            'is_pinned' => 0,
            'is_locked' => 0,
            'is_solved' => 1,
            'view_count' => 0,
            'reply_count' => 1,
            'last_post_at' => $fmt($created->modify('+40 minutes')),
            'created_at' => $fmt($created),
            'updated_at' => $fmt($created),
        ]);
        $firstId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_posts')->insert([
            'id' => $firstId,
            'thread_id' => $threadId,
            'author_id' => $readerId,
            'parent_id' => null,
            'body' => '<p>وقتی خروجی JSON می‌گیرم، متن فارسی به‌صورت <code>\\u0627\\u0646\\u062c\\u0645\\u0646</code> نمایش داده می‌شود. چطور درستش کنم؟</p>',
            'vote_score' => 0,
            'is_solution' => 0,
            'status' => 'visible',
            'created_at' => $fmt($created),
            'updated_at' => $fmt($created),
        ]);
        $solutionId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_posts')->insert([
            'id' => $solutionId,
            'thread_id' => $threadId,
            'author_id' => $authorId,
            'parent_id' => null,
            'body' => '<p>فلگ <code>JSON_UNESCAPED_UNICODE</code> را به json_encode اضافه کنید:</p><pre><code>json_encode($data, JSON_UNESCAPED_UNICODE);</code></pre><blockquote>خروجی: <strong>انجمن</strong> به‌جای escape.</blockquote>',
            'vote_score' => 2,
            'is_solution' => 1,
            'status' => 'visible',
            'created_at' => $fmt($created->modify('+40 minutes')),
            'updated_at' => $fmt($created->modify('+40 minutes')),
        ]);
        DB::table('forum_votes')->insert([
            ['post_id' => $solutionId, 'user_id' => $readerId, 'value' => 1, 'created_at' => $fmt($created)],
            ['post_id' => $solutionId, 'user_id' => $adminId, 'value' => 1, 'created_at' => $fmt($created)],
        ]);
        DB::table('forum_subscriptions')->insert([
            'user_id' => $readerId, 'thread_id' => $threadId, 'created_at' => $fmt($created),
        ]);
        DB::table('forum_profiles')->where('user_id', $readerId)->update(['thread_count' => 2, 'post_count' => 2]);
        DB::table('forum_profiles')->where('user_id', $authorId)->update(['post_count' => 1, 'solution_count' => 1, 'karma' => 19]);
    }

    // ── موضوع ۳: گفت‌وجوی آزاد + یک پست گزارش‌شده در صف moderation ──────
    if (!DB::table('forum_threads')->where('slug', 'behtarin-shive-motale')->exists()) {
        $threadId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_threads')->insert([
            'id' => $threadId,
            'forum_id' => $forumIds['general'],
            'author_id' => $authorId,
            'title' => 'بهترین شیوهٔ مطالعهٔ مستندات فریمورک',
            'slug' => 'behtarin-shive-motale',
            'is_pinned' => 0,
            'is_locked' => 0,
            'is_solved' => 0,
            'view_count' => 0,
            'reply_count' => 1,
            'last_post_at' => $fmt($now->modify('+10 minutes')),
            'created_at' => $fmt($now),
            'updated_at' => $fmt($now),
        ]);
        $firstId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_posts')->insert([
            'id' => $firstId,
            'thread_id' => $threadId,
            'author_id' => $authorId,
            'parent_id' => null,
            'body' => '<p>شما مستندات را چطور می‌خوانید؟ من با <strong>roadmap</strong> شروع می‌کنم و بعد سراغ کیت‌ها می‌روم.</p>',
            'vote_score' => 0,
            'is_solution' => 0,
            'status' => 'visible',
            'created_at' => $fmt($now),
            'updated_at' => $fmt($now),
        ]);
        $spamId = (string) Tavaan\Support\Ulid::generate();
        DB::table('forum_posts')->insert([
            'id' => $spamId,
            'thread_id' => $threadId,
            'author_id' => $readerId,
            'parent_id' => null,
            'body' => '<p>این پست برای آزمایش صف moderation گزارش شده است — moderator می‌تواند آن را پنهان یا رد کند.</p>',
            'vote_score' => -1,
            'is_solution' => 0,
            'status' => 'visible',
            'created_at' => $fmt($now->modify('+10 minutes')),
            'updated_at' => $fmt($now->modify('+10 minutes')),
        ]);
        DB::table('forum_votes')->insert([
            'post_id' => $spamId, 'user_id' => $adminId, 'value' => -1, 'created_at' => $fmt($now),
        ]);
        DB::table('forum_flags')->insert([
            'post_id' => $spamId,
            'user_id' => $adminId,
            'reason' => 'محتوای نامربوط',
            'status' => 'pending',
            'moderator_id' => null,
            'resolved_at' => null,
            'created_at' => $fmt($now->modify('+15 minutes')),
            'updated_at' => $fmt($now->modify('+15 minutes')),
        ]);
        DB::table('forum_profiles')->where('user_id', $authorId)->update(['thread_count' => 1, 'post_count' => 1]);
        DB::table('forum_profiles')->where('user_id', $readerId)->update(['post_count' => 2]);
    }

    // ── بازشماری آمار تالارها ────────────────────────────────────────────
    foreach ($forumIds as $forumId) {
        $threadRows = DB::table('forum_threads')->where('forum_id', $forumId)->get();
        $threadIds = array_map(static fn (array $r): string => (string) $r['id'], $threadRows);
        $postCount = 0;
        $lastPostId = null;
        if ($threadIds !== []) {
            $postRows = DB::table('forum_posts')
                ->whereIn('thread_id', $threadIds)
                ->where('status', 'visible')
                ->orderBy('created_at')
                ->get();
            $postCount = count($postRows);
            $lastPostId = $postRows === [] ? null : (string) end($postRows)['id'];
        }
        DB::table('forum_forums')->where('id', $forumId)->update([
            'thread_count' => count($threadIds),
            'post_count' => $postCount,
            'last_post_id' => $lastPostId,
        ]);
    }
};
