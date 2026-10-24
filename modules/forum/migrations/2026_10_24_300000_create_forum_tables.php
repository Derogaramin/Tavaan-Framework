<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول انجمن — DOC-094 §2 (forum_forums/forum_threads/forum_posts/
 * forum_votes/forum_subscriptions) + forum_flags (صف moderation — DOC-094 §5)
 * و forum_profiles (username برای @mention + Reputation/Karma — DOC-094 §6).
 * FULLTEXT روی sqlite/pg به index fallback می‌کند.
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('forum_forums')) {
            Schema::create('forum_forums', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('parent_id')->nullable();   // تالار تو‌در‌تو
                $t->string('title', 190);
                $t->string('slug', 200)->unique();
                $t->text('description')->nullable();
                $t->integer('position')->default(0);
                $t->boolean('is_locked')->default(false);
                $t->integer('thread_count')->default(0);
                $t->integer('post_count')->default(0);
                $t->char('last_post_id', 26)->nullable();          // ULID آخرین پست (هم‌نوع با forum_posts.id)
                $t->timestamps(3);
                $t->index(['parent_id'], 'idx_forum_parent');
            });
        }

        if (!Schema::hasTable('forum_threads')) {
            Schema::create('forum_threads', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                     // ULID
                $t->unsignedBigInteger('forum_id');
                $t->unsignedBigInteger('author_id');
                $t->string('title', 255);
                $t->string('slug', 280)->unique();
                $t->boolean('is_pinned')->default(false);
                $t->boolean('is_locked')->default(false);
                $t->boolean('is_solved')->default(false);          // پاسخ برگزیده دارد (Q&A)
                $t->integer('view_count')->default(0);
                $t->integer('reply_count')->default(0);
                $t->dateTime('last_post_at', 3)->nullable();
                $t->timestamps(3);
                $t->index(['forum_id', 'is_pinned', 'last_post_at'], 'idx_forum_pinned_last');
                $t->index(['author_id'], 'idx_thread_author');
                $t->fullText(['title'], 'ft_forum_thread');
            });
        }

        if (!Schema::hasTable('forum_posts')) {
            Schema::create('forum_posts', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                     // ULID
                $t->char('thread_id', 26);
                $t->unsignedBigInteger('author_id');
                $t->char('parent_id', 26)->nullable();             // پاسخ تو‌در‌تو/نقل‌قول
                $t->longText('body');
                $t->integer('vote_score')->default(0);
                $t->boolean('is_solution')->default(false);
                $t->string('status', 20)->default('visible');      // visible|hidden|deleted
                $t->timestamps(3);
                $t->index(['thread_id', 'created_at'], 'idx_post_thread');
                $t->index(['author_id'], 'idx_post_author');
                $t->index(['status'], 'idx_post_status');
                $t->fullText(['body'], 'ft_forum_post');
            });
        }

        if (!Schema::hasTable('forum_votes')) {
            Schema::create('forum_votes', function (Blueprint $t): void {
                $t->id();
                $t->char('post_id', 26);
                $t->unsignedBigInteger('user_id');
                $t->tinyInteger('value');                          // +1 / -1
                $t->timestamp('created_at', 3)->nullable();
                $t->unique(['post_id', 'user_id'], 'uk_vote');
            });
        }

        if (!Schema::hasTable('forum_subscriptions')) {
            Schema::create('forum_subscriptions', function (Blueprint $t): void {
                $t->unsignedBigInteger('user_id');
                $t->char('thread_id', 26);
                $t->timestamp('created_at', 3)->nullable();
                $t->primary(['user_id', 'thread_id'], 'pk_subscription');
            });
        }

        if (!Schema::hasTable('forum_flags')) {
            Schema::create('forum_flags', function (Blueprint $t): void {
                $t->id();
                $t->char('post_id', 26);
                $t->unsignedBigInteger('user_id')->nullable();     // گزارش‌دهنده (guest=null)
                $t->string('reason', 500)->nullable();
                $t->string('status', 20)->default('pending');      // pending|resolved|dismissed
                $t->unsignedBigInteger('moderator_id')->nullable();
                $t->dateTime('resolved_at', 3)->nullable();
                $t->timestamps(3);
                $t->index(['status'], 'idx_flag_status');
                $t->index(['post_id'], 'idx_flag_post');
            });
        }

        if (!Schema::hasTable('forum_profiles')) {
            Schema::create('forum_profiles', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id')->unique();
                $t->string('username', 40)->unique();              // @username (mention)
                $t->text('bio')->nullable();
                $t->string('signature', 300)->nullable();
                $t->integer('karma')->default(0);                  // Reputation/Karma
                $t->integer('thread_count')->default(0);
                $t->integer('post_count')->default(0);
                $t->integer('solution_count')->default(0);
                $t->boolean('is_banned')->default(false);
                $t->dateTime('banned_until', 3)->nullable();
                $t->string('ban_reason', 500)->nullable();
                $t->timestamps(3);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_profiles');
        Schema::dropIfExists('forum_flags');
        Schema::dropIfExists('forum_subscriptions');
        Schema::dropIfExists('forum_votes');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forum_forums');
    }
};
