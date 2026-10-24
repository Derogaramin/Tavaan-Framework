<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول وبلاگ — DOC-091 §2 (blog_posts/blog_categories/blog_tags/
 * blog_post_tag/blog_comments). prefix ماژول = blog_ (DoD ماژول — DOC-113 §3).
 * FULLTEXT فقط روی MySQL معنی دارد؛ grammarهای sqlite/pg به index معمولی
 * fallback می‌کنند (SchemaGrammar::fulltextIndexKeyword).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 100);
                $t->string('slug', 120)->unique();
                $t->text('description')->nullable();
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $t): void {
                $t->char('id', 26)->primary();               // ULID
                $t->string('slug', 200)->unique();
                $t->string('title', 255);
                $t->text('excerpt')->nullable();
                $t->longText('body')->nullable();
                $t->unsignedBigInteger('author_id');
                $t->unsignedBigInteger('category_id')->nullable();
                $t->string('status', 20)->default('draft'); // draft|published|scheduled|archived
                $t->char('featured_image', 26)->nullable(); // → tvn_media
                $t->integer('view_count')->default(0);
                $t->dateTime('published_at', 3)->nullable();
                $t->string('seo_title', 255)->nullable();
                $t->text('seo_description')->nullable();
                $t->text('seo_keywords')->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();  // soft delete
                $t->index(['status', 'published_at'], 'idx_status_published');
                $t->index(['author_id'], 'idx_posts_author');
                $t->index(['category_id'], 'idx_posts_category');
                $t->fullText(['title', 'excerpt'], 'ft_post');
            });
        }

        if (!Schema::hasTable('blog_tags')) {
            Schema::create('blog_tags', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 100);
                $t->string('slug', 120)->unique();
            });
        }

        if (!Schema::hasTable('blog_post_tag')) {
            Schema::create('blog_post_tag', function (Blueprint $t): void {
                $t->char('post_id', 26);
                $t->unsignedBigInteger('tag_id');
                $t->primary(['post_id', 'tag_id']);
            });
        }

        if (!Schema::hasTable('blog_comments')) {
            Schema::create('blog_comments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();               // ULID
                $t->string('commentable_type', 100);
                $t->char('commentable_id', 26);
                $t->string('author_name', 100);
                $t->string('author_email', 190);
                $t->text('body');
                $t->char('parent_id', 26)->nullable();
                $t->string('status', 20)->default('pending'); // pending|approved|spam
                $t->dateTime('created_at', 3);
                $t->index(['commentable_type', 'commentable_id', 'status'], 'idx_commentable');
                $t->index(['parent_id'], 'idx_comments_parent');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
