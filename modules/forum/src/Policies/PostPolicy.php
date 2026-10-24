<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostPolicy — مالکیت + مجوزهای moderation پست (DOC-094 §4).
 */

namespace Modules\Forum\Policies;

use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Entities\Thread;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * PostPolicy — از راه `Gate::authorize('<ability>', $post)` و برای
 * پاسخ برگزیده `Gate::authorize('solution', [$post, $thread])`.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class PostPolicy
{
    public function view(Authenticatable $user, Post $post): bool
    {
        return $post->status()->isModerationVisible() || Gate::allows('forum.post.hide');
    }

    /** ویرایش: نویسندهٔ پست (member) یا moderator (همه). */
    public function update(Authenticatable $user, Post $post): bool
    {
        if (Gate::allows('forum.post.update')) {
            return true;
        }

        return $this->isOwner($user, $post)
            && Gate::allows('forum.post.create')
            && $post->isVisible();
    }

    /** حذف: فقط moderator/admin (DOC-094 §4). */
    public function delete(Authenticatable $user, Post $post): bool
    {
        return Gate::allows('forum.post.delete');
    }

    public function hide(Authenticatable $user, Post $post): bool
    {
        return Gate::allows('forum.post.hide');
    }

    /** رأی: member — رأی به پست خود مجاز نیست (سرویس هم چک می‌کند). */
    public function vote(Authenticatable $user, Post $post): bool
    {
        return Gate::allows('forum.post.vote') && !$this->isOwner($user, $post);
    }

    /** گزارش (flag): هر عضو. */
    public function flag(Authenticatable $user, Post $post): bool
    {
        return Gate::allows('forum.flag.create');
    }

    /** پاسخ برگزیده: نویسندهٔ موضوع یا دارندهٔ مجوز (moderator). */
    public function solution(Authenticatable $user, Post $post, ?Thread $thread = null): bool
    {
        if (Gate::allows('forum.post.solution')) {
            return true;
        }

        return $thread !== null
            && (string) $user->getAuthIdentifier() === (string) $thread->authorId();
    }

    private function isOwner(Authenticatable $user, Post $post): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $post->authorId();
    }
}
