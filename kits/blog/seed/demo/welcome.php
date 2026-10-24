<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت — مقالهٔ خوش‌آمدگویی (پس از seed ماژول‌ها اجرا می‌شود).
 * قرارداد: callable() — idempotent.
 */

use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    if (DB::table('blog_posts')->where('slug', 'khosh-amadid')->exists()) {
        return;
    }

    $author = DB::table('users')->where('email', 'admin@blog.test')->first();
    if ($author === null) {
        return;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = 'Y-m-d H:i:s.v';

    DB::table('blog_posts')->insert([
        'id' => (string) Tavaan\Support\Ulid::generate(),
        'slug' => 'khosh-amadid',
        'title' => 'به وبلاگ تَوان خوش آمدید! 🎉',
        'excerpt' => 'کیت وبلاگ با موفقیت نصب شد — این مقالهٔ نمونه را از پیشخوان مدیریت ویرایش یا حذف کنید.',
        'body' => '<h2>نصب کامل شد</h2><p>کیت وبلاگ تَوان شامل مقاله، دسته، برچسب، نظرات تو‌در‌تو با moderation، جست‌وجوی فارسی، آرشیو جلالی، فید RSS/Atom و sitemap است.</p><p>از <code>/admin/blog/posts</code> محتوای خود را مدیریت کنید.</p>',
        'author_id' => (int) $author['id'],
        'category_id' => null,
        'status' => 'published',
        'view_count' => 0,
        'published_at' => $now->format($fmt),
        'seo_title' => 'به وبلاگ تَوان خوش آمدید',
        'seo_description' => 'کیت وبلاگ تَوان نصب شد.',
        'created_at' => $now->format($fmt),
        'updated_at' => $now->format($fmt),
    ]);
};
