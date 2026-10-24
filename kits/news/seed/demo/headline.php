<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت — تیتر یک خبرگزاری (پس از seed ماژول‌ها).
 * قرارداد: callable() — idempotent.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    if (DB::table('news_articles')->where('slug', 'khbar-online-shod')->exists()) {
        return;
    }

    $chief = DB::table('users')->where('email', 'admin@blog.test')->first();
    if ($chief === null) {
        return;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = 'Y-m-d H:i:s.v';

    DB::table('news_articles')->insert([
        'id' => (string) Tavaan\Support\Ulid::generate(),
        'slug' => 'khbar-online-shod',
        'title' => 'پرتال خبری شما آنلاین شد 🎉',
        'lead' => 'کیت خبرگزاری تَوان با موفقیت نصب شد — این تیتر نمونه را از پنل مدیریت ویرایش یا حذف کنید.',
        'body' => '<h2>آغاز به کار</h2><p>پرتال خبری شما شامل <strong>گردش کار ویراستاری</strong>، خبر فوری، زمان‌بندی انتشار، AMP و فیدهای RSS/Atom است.</p><p>از <code>/admin/news/articles</code> مدیریت تحریریه را آغاز کنید.</p>',
        'author_id' => (int) $chief['id'],
        'editor_id' => (int) $chief['id'],
        'category_id' => null,
        'status' => 'published',
        'is_breaking' => 0,
        'is_featured' => 1,
        'view_count' => 0,
        'published_at' => $now->format($fmt),
        'seo_title' => 'پرتال خبری شما آنلاین شد',
        'seo_description' => 'کیت خبرگزاری تَوان نصب شد.',
        'created_at' => $now->format($fmt),
        'updated_at' => $now->format($fmt),
    ]);
};
