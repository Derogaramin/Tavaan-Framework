<?php

declare(strict_types=1);

/*
 * Seeder دمو — ماژول خبرگزاری (توسط `tvn kit:install news --with-demo`).
 * قرارداد: callable() — idempotent. وابسته به seed دمؤ core-user.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = static fn (DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s.v');

    $author = DB::table('users')->where('email', 'author@blog.test')->first()
        ?? DB::table('users')->where('email', 'admin@blog.test')->first();
    if ($author === null) {
        return;
    }
    $authorId = (int) $author['id'];
    $editor = DB::table('users')->where('email', 'editor@blog.test')->first();
    $editorId = $editor !== null ? (int) $editor['id'] : $authorId;

    // دسته‌ها (با یک زیردسته)
    $catIds = [];
    foreach ([
        ['title' => 'سیاست', 'slug' => 'politics', 'parent' => null],
        ['title' => 'اقتصاد', 'slug' => 'economy', 'parent' => null],
        ['title' => 'فناوری', 'slug' => 'tech', 'parent' => null],
        ['title' => 'هوش مصنوعی', 'slug' => 'ai', 'parent' => 'tech'],
    ] as $cat) {
        $row = DB::table('news_categories')->where('slug', $cat['slug'])->first();
        if ($row !== null) {
            $catIds[$cat['slug']] = (int) $row['id'];
            continue;
        }
        $parentId = $cat['parent'] !== null ? ($catIds[$cat['parent']] ?? null) : null;
        $catIds[$cat['slug']] = (int) DB::table('news_categories')->insertGetId([
            'title' => $cat['title'],
            'slug' => $cat['slug'],
            'parent_id' => $parentId,
            'created_at' => $fmt($now),
            'updated_at' => $fmt($now),
        ]);
    }

    // برچسب‌ها
    $tagIds = [];
    foreach ([['name' => 'توان', 'slug' => 'tavaan'], ['name' => 'اخبار فناوری', 'slug' => 'tech-news']] as $tag) {
        $row = DB::table('news_tags')->where('slug', $tag['slug'])->first();
        $tagIds[$tag['slug']] = $row === null
            ? (int) DB::table('news_tags')->insertGetId($tag)
            : (int) $row['id'];
    }

    $articles = [
        [
            'slug' => 'ravand-e-bazare-technology',
            'title' => 'روند بازار فناوری در هفتهٔ گذشته',
            'lead' => 'بررسی روندهای کلان بازار فناوری و مهم‌ترین رویدادهای هفته.',
            'body' => '<p>بازار فناوری هفتهٔ پرنوسانی را پشت سر گذاشت. در این گزارش به <strong>مهم‌ترین روندها</strong> می‌پردازیم.</p><ul><li>رشد ابزارهای هوش مصنوعی</li><li>تمرکز بر زیرساخت ابری</li></ul>',
            'category' => 'tech',
            'tags' => ['tech-news'],
            'breaking' => false,
            'days_ago' => 1,
        ],
        [
            'slug' => 'shekaf-e-hosh-e-masnooi',
            'title' => 'هوش مصنوعی و شکاف مهارتی نیروی کار',
            'lead' => 'کارشناسان دربارهٔ شکاف مهارتی در بازار کار هوش مصنوعی هشدار می‌دهند.',
            'body' => '<p>گزارش‌های تازه نشان می‌دهد تقاضا برای متخصصان هوش مصنوعی چند برابر عرضه است.</p><blockquote>آموزش مستمر، کلید بقا در بازار کار جدید است.</blockquote>',
            'category' => 'ai',
            'tags' => ['tavaan', 'tech-news'],
            'breaking' => false,
            'days_ago' => 0,
        ],
        [
            'slug' => 'fori-erteghat-e-shabake',
            'title' => 'فوری: ارتقای زیرساخت شبکهٔ ملی تکمیل شد',
            'lead' => 'فاز دوم ارتقای زیرساخت امروز به بهره‌برداری رسید.',
            'body' => '<p>به گزارش خبرنگار ما، فاز دوم ارتقای زیرساخت شبکه امروز با موفقیت تکمیل شد.</p>',
            'category' => 'tech',
            'tags' => [],
            'breaking' => true,
            'days_ago' => 0,
        ],
    ];

    foreach ($articles as $a) {
        if (DB::table('news_articles')->where('slug', $a['slug'])->exists()) {
            continue;
        }
        $publishedAt = $now->sub(new DateInterval('P' . $a['days_ago'] . 'D'));
        $id = (string) Tavaan\Support\Ulid::generate($publishedAt);
        DB::table('news_articles')->insert([
            'id' => $id,
            'slug' => $a['slug'],
            'title' => $a['title'],
            'lead' => $a['lead'],
            'body' => $a['body'],
            'author_id' => $authorId,
            'editor_id' => $editorId,
            'category_id' => $catIds[$a['category']] ?? null,
            'status' => 'published',
            'is_breaking' => $a['breaking'] ? 1 : 0,
            'is_featured' => 0,
            'view_count' => 0,
            'published_at' => $fmt($publishedAt),
            'seo_title' => $a['title'],
            'seo_description' => $a['lead'],
            'created_at' => $fmt($publishedAt),
            'updated_at' => $fmt($publishedAt),
        ]);
        foreach ($a['tags'] as $tagSlug) {
            if (isset($tagIds[$tagSlug])) {
                DB::table('news_article_tag')->insertOrIgnore(['article_id' => $id, 'tag_id' => $tagIds[$tagSlug]]);
            }
        }
    }

    // یک نظر نمونه
    $first = DB::table('news_articles')->where('slug', 'ravand-e-bazare-technology')->first();
    if ($first !== null && !DB::table('news_comments')->where('commentable_id', $first['id'])->exists()) {
        DB::table('news_comments')->insert([
            'id' => (string) Tavaan\Support\Ulid::generate(),
            'commentable_type' => 'news_article',
            'commentable_id' => (string) $first['id'],
            'author_name' => 'خوانندهٔ نمونه',
            'author_email' => 'reader@blog.test',
            'body' => 'گزارش جامع و دقیقی بود؛ سپاس.',
            'status' => 'approved',
            'created_at' => $fmt($now),
        ]);
    }
};
