<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentableInterface — قرارداد موجودیتِ نظرپذیر (DOC-090 §6).
 */

namespace Modules\Comment\Contracts;

/**
 * موجودیتی که می‌تواند نظر بپذیرد (Post محصول دوره و …).
 *
 * ارائه‌دهنده: tavaan.comment • مصرف‌کنندگان: blog, shop, lms (ماتریس DOC-090 §5).
 *
 * @api 1.0
 */
interface CommentableInterface
{
    /** نوع پلی‌مورفیک (مثلاً `blog_post`) — ستون commentable_type. */
    public function commentableType(): string;

    /** شناسهٔ موجودیت — ستون commentable_id. */
    public function commentableId(): string;

    /** آیا ثبت نظر جدید مجاز است؟ (مثلاً آرشیو‌شده‌ها بسته‌اند) */
    public function commentsOpen(): bool;
}
