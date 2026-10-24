<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticlePolicy — مالکیت + مجوزهای workflow خبر (DOC-092 §4).
 */

namespace Modules\News\Policies;

use Modules\News\Domain\Entities\Article;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * ArticlePolicy — از راه `Gate::authorize('<ability>', $article)`.
 *
 * نقش‌ها (DOC-092 §4):
 * - reporter: نوشتن/ویرایش خبر **خود** + submit
 * - editor: review/approve/reject + ویرایش همه
 * - chief-editor: انتشار + breaking + دسته‌ها
 * - admin: همه
 *
 * 🔒 بررسی DB-permission با ability بدون آرگومان → callPolicy مدل null
 * می‌گیرد و fallthrough می‌کند — بدون بازگشت (هم‌راستا با blog).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class ArticlePolicy
{
    public function view(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.view') || $this->isOwner($user, $article);
    }

    public function update(Authenticatable $user, Article $article): bool
    {
        if (Gate::allows('news.article.update')) {
            return true;    // editor/chief/admin
        }

        // reporter → فقط خبر خود، و فقط تا پیش از انتشار
        return $this->isOwner($user, $article)
            && Gate::allows('news.article.create')
            && $article->status()->isInPipeline();
    }

    public function delete(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.delete');
    }

    public function submit(Authenticatable $user, Article $article): bool
    {
        if (!Gate::allows('news.article.submit')) {
            return false;
        }

        return $this->isOwner($user, $article) || Gate::allows('news.article.update');
    }

    public function review(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.review');
    }

    public function approve(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.approve');
    }

    public function reject(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.approve');
    }

    public function publish(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.publish');
    }

    public function breaking(Authenticatable $user, Article $article): bool
    {
        return Gate::allows('news.article.breaking');
    }

    private function isOwner(Authenticatable $user, Article $article): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $article->authorId();
    }
}
