<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول محتوا — صفحات CMS (content_pages).
 * prefix ماژول = content_ (DoD ماژول — DOC-113 §3).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('content_pages')) {
            Schema::create('content_pages', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                  // ULID
                $t->string('slug', 200)->unique();
                $t->string('title', 255);
                $t->text('excerpt')->nullable();
                $t->longText('body')->nullable();               // HTML سانیتیزه‌شده
                $t->unsignedBigInteger('author_id');
                $t->string('status', 20)->default('draft');     // draft|published|archived
                $t->integer('view_count')->default(0);
                $t->dateTime('published_at', 3)->nullable();
                $t->string('seo_title', 255)->nullable();
                $t->text('seo_description')->nullable();
                $t->text('seo_keywords')->nullable();
                $t->boolean('comments_open')->default(true);
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();      // soft delete
                $t->index(['status', 'published_at'], 'idx_pages_status_published');
                $t->index(['author_id'], 'idx_pages_author');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
