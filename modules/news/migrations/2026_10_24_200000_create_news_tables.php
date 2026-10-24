<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول خبرگزاری — DOC-092 §2 (news_articles/news_categories/
 * news_article_tag) + news_tags و news_comments (پورت ماژول comment با
 * prefix ماژول — DoD §3). FULLTEXT روی sqlite/pg به index fallback می‌کند.
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('news_categories')) {
            Schema::create('news_categories', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('parent_id')->nullable();   // دستهٔ تو‌در‌تو
                $t->string('title', 190);
                $t->string('slug', 200)->unique();
                $t->text('description')->nullable();
                $t->timestamps(3);
                $t->index(['parent_id'], 'idx_news_cat_parent');
            });
        }

        if (!Schema::hasTable('news_articles')) {
            Schema::create('news_articles', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                    // ULID
                $t->string('slug', 255)->unique();
                $t->string('title', 300);
                $t->text('lead')->nullable();                     // لید خبر
                $t->longText('body')->nullable();
                $t->unsignedBigInteger('author_id');
                $t->unsignedBigInteger('editor_id')->nullable();  // ویراستار workflow
                $t->unsignedBigInteger('category_id')->nullable();
                $t->string('status', 20)->default('draft');       // draft|review|approved|published|archived
                $t->boolean('is_breaking')->default(false);
                $t->boolean('is_featured')->default(false);
                $t->char('featured_image', 26)->nullable();       // → tvn_media
                $t->json('gallery')->nullable();                  // [ULID, …]
                $t->integer('view_count')->default(0);
                $t->dateTime('published_at', 3)->nullable();
                $t->dateTime('scheduled_at', 3)->nullable();      // زمان‌بندی انتشار
                $t->string('seo_title', 300)->nullable();
                $t->text('seo_description')->nullable();
                $t->string('reject_reason', 500)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();
                $t->index(['status', 'published_at'], 'idx_status_pub');
                $t->index(['is_breaking', 'published_at'], 'idx_breaking');
                $t->index(['category_id'], 'idx_news_cat');
                $t->index(['author_id'], 'idx_news_author');
                $t->fullText(['title', 'lead'], 'ft_news_article');
            });
        }

        if (!Schema::hasTable('news_tags')) {
            Schema::create('news_tags', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 100);
                $t->string('slug', 120)->unique();
            });
        }

        if (!Schema::hasTable('news_article_tag')) {
            Schema::create('news_article_tag', function (Blueprint $t): void {
                $t->char('article_id', 26);
                $t->unsignedBigInteger('tag_id');
                $t->primary(['article_id', 'tag_id']);
            });
        }

        if (!Schema::hasTable('news_comments')) {
            Schema::create('news_comments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('commentable_type', 100);
                $t->char('commentable_id', 26);
                $t->string('author_name', 100);
                $t->string('author_email', 190);
                $t->text('body');
                $t->char('parent_id', 26)->nullable();
                $t->string('status', 20)->default('pending');     // pending|approved|spam
                $t->dateTime('created_at', 3);
                $t->index(['commentable_type', 'commentable_id', 'status'], 'idx_news_commentable');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_comments');
        Schema::dropIfExists('news_article_tag');
        Schema::dropIfExists('news_tags');
        Schema::dropIfExists('news_articles');
        Schema::dropIfExists('news_categories');
    }
};
