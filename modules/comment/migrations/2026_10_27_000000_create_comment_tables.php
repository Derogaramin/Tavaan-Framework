<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول عمومی نظرات — ماژول comment (حالت standalone).
 *
 * طراحی: پلی‌مورفیک (commentable_type + commentable_id) بدون FK — هر
 * subject (پست وبلاگ/خبر/صفحه/…) با نوع رشته‌ای ثبت می‌شود (DOC-090 §6).
 * ماژول‌های مصرف‌کننده می‌توانند به‌جای این جدول، store اختصاصی با prefix
 * خودشان پیاده کنند (مانند blog_comments) — این جدول برای مصرف مستقیم و
 * بدون ماژول واسط است.
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('comment_comments')) {
            Schema::create('comment_comments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                 // ULID
                $t->string('commentable_type', 100);
                $t->string('commentable_id', 64);
                $t->char('parent_id', 26)->nullable();
                $t->unsignedBigInteger('user_id')->nullable(); // نویسندهٔ واردشده
                $t->string('author_name', 190);
                $t->string('author_email', 190);
                $t->text('body');
                $t->string('status', 20)->default('pending');  // pending|approved|spam
                $t->char('ip_hash', 64)->nullable();           // 🔒 حریم خصوصی: hash(IP+salt)
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['commentable_type', 'commentable_id', 'status'], 'idx_comment_subject');
                $t->index(['status', 'created_at'], 'idx_comment_status');
                $t->index(['author_email'], 'idx_comment_author_email');
                $t->index(['parent_id'], 'idx_comment_parent');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_comments');
    }
};
