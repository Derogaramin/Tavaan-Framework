<?php

declare(strict_types=1);

/*
 * ماژول انجمن — روت‌های عمومی وب (DOC-094 §3).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * ترتیب: مسیرهای ایستا (search/members) پیش از {slug}.
 * نوشتن‌ها با middleware احراز هویت + Throttle لایهٔ دوم anti-spam
 * (لایهٔ نخست FloodControl داخل PostService است — DOC-094 §7).
 */

use Modules\Forum\Http\Controllers\ForumController;
use Modules\Forum\Http\Controllers\MemberController;
use Modules\Forum\Http\Controllers\PostController;
use Modules\Forum\Http\Controllers\ThreadController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

// ── عمومی (خواندن) ─────────────────────────────────────────────────────────
Route::get('/forums', [ForumController::class, 'index'])->name('forum.index');
Route::get('/forums/search', [ForumController::class, 'search'])->name('forum.search');
Route::get('/forums/{slug}', [ForumController::class, 'show'])->name('forum.show');

Route::get('/threads/{slug}', [ThreadController::class, 'show'])->name('forum.thread.show');

Route::get('/posts/{id}/quote', [PostController::class, 'quote'])->name('forum.post.quote');

Route::get('/members/{username}', [MemberController::class, 'show'])->name('forum.member.show');

// ── نوشتن (احراز هویت + throttle ضد-flood لایهٔ دوم) ────────────────────────
Route::group(['middleware' => [AuthenticateMiddleware::class]], static function (): void {
    Route::get('/forums/{slug}/new', [ThreadController::class, 'create'])->name('forum.thread.create');
    Route::post('/threads', [ThreadController::class, 'store'])
        ->name('forum.thread.store')
        ->middleware([\Tavaan\Engine\Security\Middleware\ThrottleMiddleware::class . ':10,10']);

    Route::post('/threads/{id}/posts', [PostController::class, 'store'])
        ->name('forum.post.store')
        ->middleware([\Tavaan\Engine\Security\Middleware\ThrottleMiddleware::class . ':20,10']);
    Route::post('/threads/{id}/subscribe', [ThreadController::class, 'subscribe'])->name('forum.thread.subscribe');
    Route::post('/threads/{id}/unsubscribe', [ThreadController::class, 'unsubscribe'])->name('forum.thread.unsubscribe');

    Route::get('/posts/{id}/edit', [PostController::class, 'edit'])->name('forum.post.edit');
    Route::post('/posts/{id}', [PostController::class, 'update'])->name('forum.post.update');
    Route::post('/posts/{id}/vote', [PostController::class, 'vote'])->name('forum.post.vote');
    Route::post('/posts/{id}/mark-solution', [PostController::class, 'markSolution'])->name('forum.post.solution');
    Route::post('/posts/{id}/flag', [PostController::class, 'flag'])->name('forum.post.flag');

    Route::get('/members/{username}/edit', [MemberController::class, 'edit'])->name('forum.member.edit');
    Route::post('/members/{username}', [MemberController::class, 'update'])->name('forum.member.update');
});
