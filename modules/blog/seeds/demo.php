<?php

declare(strict_types=1);

/*
 * Seeder دمو — ماژول وبلاگ (توسط `tvn kit:install blog --with-demo` اجرا می‌شود).
 *
 * قرارداد: callable() برمی‌گرداند (بدون autoload) — از Facadeهای هسته استفاده
 * می‌کند. 🔒 Idempotent و وابسته به seed دمؤ core-user (کاربران).
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = static fn (DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s.v');

    $author = DB::table('users')->where('email', 'author@blog.test')->first()
        ?? DB::table('users')->where('email', 'admin@blog.test')->first();
    if ($author === null) {
        return;   // بدون کاربر — seed دمؤ core-user اول اجرا شود
    }
    $authorId = (int) $author['id'];

    // دسته‌ها
    $categories = [];
    foreach ([
        ['name' => 'آموزش', 'slug' => 'amoozesh', 'description' => 'مقالات آموزشی فریمورک تَوان'],
        ['name' => 'اخبار', 'slug' => 'akhbar', 'description' => 'تازه‌های پروژه'],
    ] as $cat) {
        $row = DB::table('blog_categories')->where('slug', $cat['slug'])->first();
        if ($row === null) {
            $id = DB::table('blog_categories')->insertGetId([
                ...$cat, 'created_at' => $fmt($now), 'updated_at' => $fmt($now),
            ]);
        } else {
            $id = (int) $row['id'];
        }
        $categories[$cat['slug']] = $id;
    }

    // برچسب‌ها
    $tags = [];
    foreach ([['name' => 'توان', 'slug' => 'tavaan'], ['name' => 'پی‌اچ‌پی', 'slug' => 'php'], ['name' => 'آموزش', 'slug' => 'tutorial']] as $tag) {
        $row = DB::table('blog_tags')->where('slug', $tag['slug'])->first();
        $tags[$tag['slug']] = $row === null
            ? (int) DB::table('blog_tags')->insertGetId($tag)
            : (int) $row['id'];
    }

    // مقالات نمونه
    $posts = [
        [
            'slug' => 'salam-donya',
            'title' => 'سلام دنیا با تَوان',
            'excerpt' => 'اولین مقالهٔ نمونهٔ وبلاگ — سلامی به دنیای فریمورک فارسی‌اول تَوان.',
            'body' => '<h2>سلام دنیا!</h2><p>این اولین مقالهٔ <strong>نمونه</strong> روی کیت وبلاگ تَوان است. موتور ویو با auto-escape و موتور i18n با تقویم جلالی به‌صورت پیش‌فرض فعال هستند.</p><ul><li>صفر وابستگی خارجی</li><li>فارسی‌اول و RTL</li><li>PHP 8.2+</li></ul>',
            'category' => 'amoozesh',
            'tags' => ['tavaan', 'tutorial'],
            'days_ago' => 2,
        ],
        [
            'slug' => 'mvc-hmvc-modular',
            'title' => 'از MVC تا معماری ماژولار',
            'excerpt' => 'مروری بر سطح‌بندی معماری اپلیکیشن‌ها در تَوان: mvc، hmvc، modular و hexagonal.',
            'body' => '<p>تَوان چهار سطح معماری دارد. کیت وبلاگ با <strong>hmvc</strong> شروع می‌شود و فروشگاه/ERP به <strong>modular/hexagonal</strong> می‌روند.</p><blockquote>هر کاربرد = یک کیت = مجموعه‌ای از ماژول‌ها + پوسته + دادهٔ نمونه.</blockquote>',
            'category' => 'amoozesh',
            'tags' => ['tavaan'],
            'days_ago' => 1,
        ],
        [
            'slug' => 'release-notes-0-8',
            'title' => 'یادداشت انتشار نسخهٔ ۰٫۸',
            'excerpt' => 'خلاصهٔ تغییرات نسخهٔ ۰٫۸ فریمورک تَوان.',
            'body' => '<p>در این نسخه ۵۲ موتور تکمیل شد و فاز M9 (کیت‌های اپلیکیشن) آغاز گردید.</p>',
            'category' => 'akhbar',
            'tags' => ['php'],
            'days_ago' => 0,
        ],
    ];

    foreach ($posts as $p) {
        if (DB::table('blog_posts')->where('slug', $p['slug'])->exists()) {
            continue;
        }
        $publishedAt = $now->sub(new DateInterval('P' . $p['days_ago'] . 'D'));
        $id = (string) Tavaan\Support\Ulid::generate($publishedAt);
        DB::table('blog_posts')->insert([
            'id' => $id,
            'slug' => $p['slug'],
            'title' => $p['title'],
            'excerpt' => $p['excerpt'],
            'body' => $p['body'],
            'author_id' => $authorId,
            'category_id' => $categories[$p['category']] ?? null,
            'status' => 'published',
            'view_count' => 0,
            'published_at' => $fmt($publishedAt),
            'seo_title' => $p['title'],
            'seo_description' => $p['excerpt'],
            'created_at' => $fmt($publishedAt),
            'updated_at' => $fmt($publishedAt),
        ]);
        foreach ($p['tags'] as $tagSlug) {
            if (isset($tags[$tagSlug])) {
                DB::table('blog_post_tag')->insertOrIgnore(['post_id' => $id, 'tag_id' => $tags[$tagSlug]]);
            }
        }
    }

    // نظرات نمونه (یکی تأییدشده، یکی در انتظار)
    $first = DB::table('blog_posts')->where('slug', 'salam-donya')->first();
    if ($first !== null && !DB::table('blog_comments')->where('commentable_id', $first['id'])->exists()) {
        DB::table('blog_comments')->insert([
            'id' => (string) Tavaan\Support\Ulid::generate(),
            'commentable_type' => 'blog_post',
            'commentable_id' => (string) $first['id'],
            'author_name' => 'خوانندهٔ نمونه',
            'author_email' => 'reader@blog.test',
            'body' => 'مقالهٔ خوبی بود؛ منتظر بخش‌های بعدی هستم!',
            'status' => 'approved',
            'created_at' => $fmt($now),
        ]);
        DB::table('blog_comments')->insert([
            'id' => (string) Tavaan\Support\Ulid::generate(),
            'commentable_type' => 'blog_post',
            'commentable_id' => (string) $first['id'],
            'author_name' => 'کاربر جدید',
            'author_email' => 'newcomer@example.com',
            'body' => 'سؤال: امکان چندزبانه کردن وبلاگ هم هست؟',
            'status' => 'pending',
            'created_at' => $fmt($now),
        ]);
    }
};
