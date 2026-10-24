<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadPolicy — مالکیت + مجوزهای moderation موضوع (DOC-094 §4).
 */

namespace Modules\Forum\Policies;

use Modules\Forum\Domain\Entities\Thread;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * ThreadPolicy — از راه `Gate::authorize('<ability>', $thread)`.
 *
 * نقش‌ها (DOC-094 §4):
 * - member: نوشتن موضوع/پست، رأی
 * - moderator: + ویرایش/حذف/پنهان، قفل موضوع، جابجایی
 * - admin: مدیریت تالارها، ban کاربر
 *
 * 🔒 بررسی DB-permission با ability بدون آرگومان → callPolicy مدل null
 * می‌گیرد و fallthrough می‌کند — بدون بازگشت (هم‌راستا با blog/news).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ThreadPolicy
{
    /** موضوعات انجمن عمومی‌اند. */
    public function view(Authenticatable $user, Thread $thread): bool
    {
        return true;
    }

    /** ویرایش عنوان: نویسندهٔ موضوع یا moderator. */
    public function update(Authenticatable $user, Thread $thread): bool
    {
        return $this->isOwner($user, $thread) || Gate::allows('forum.thread.update');
    }

    public function delete(Authenticatable $user, Thread $thread): bool
    {
        return Gate::allows('forum.thread.delete');
    }

    public function lock(Authenticatable $user, Thread $thread): bool
    {
        return Gate::allows('forum.thread.lock');
    }

    public function pin(Authenticatable $user, Thread $thread): bool
    {
        return Gate::allows('forum.thread.pin');
    }

    public function move(Authenticatable $user, Thread $thread): bool
    {
        return Gate::allows('forum.thread.move');
    }

    /** اشتراک: هر کاربر احراز هویت‌شده. */
    public function subscribe(Authenticatable $user, Thread $thread): bool
    {
        return true;
    }

    private function isOwner(Authenticatable $user, Thread $thread): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $thread->authorId();
    }
}
