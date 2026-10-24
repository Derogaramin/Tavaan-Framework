<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostPolicy — مالکیت و مجوزهای مقاله (DOC-091 §4).
 */

namespace Modules\Blog\Policies;

use Modules\Blog\Domain\Entities\Post;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * PostPolicy — از راه `Gate::authorize('update'|'delete'|'publish', $post)`.
 *
 * نقش‌ها (DOC-091 §4):
 * - author: نوشتن/ویرایش/حذف مقالهٔ **خود** (دارای blog.post.create)
 * - editor/admin: ویرایش/حذف **همه** + انتشار (blog.post.update/delete/publish)
 *
 * 🔒 بررسی DB-permission با ability بدون آرگومان (`Gate::allows('blog.post.update')`)
 * → callPolicy مدل null می‌گیرد و fallthrough می‌کند — بدون بازگشت.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class PostPolicy
{
    public function update(Authenticatable $user, Post $post): bool
    {
        if (Gate::allows('blog.post.update')) {
            return true;    // editor/admin
        }

        return $this->isOwner($user, $post) && Gate::allows('blog.post.create');
    }

    public function delete(Authenticatable $user, Post $post): bool
    {
        if (Gate::allows('blog.post.delete')) {
            return true;    // editor/admin
        }

        return $this->isOwner($user, $post) && Gate::allows('blog.post.create');
    }

    public function publish(Authenticatable $user, Post $post): bool
    {
        return Gate::allows('blog.post.publish');
    }

    public function view(Authenticatable $user, Post $post): bool
    {
        // همهٔ واردشده‌ها با مجوز مدیریت یا مالک می‌توانند ببینند (پیش‌نویس خود)
        return Gate::allows('blog.post.view') || $this->isOwner($user, $post);
    }

    private function isOwner(Authenticatable $user, Post $post): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $post->authorId();
    }
}
