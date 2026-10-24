<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaAttachableInterface — قرارداد موجودیتِ پیوست‌پذیر رسانه (DOC-090 §6).
 */

namespace Modules\Media\Contracts;

/**
 * موجودیتی که می‌تواند رسانه پیوست کند (blog Post → تصویر شاخص، shop Product و …).
 *
 * ارائه‌دهنده: tavaan.media • مصرف‌کنندگان: blog, shop, lms (ماتریس DOC-090 §5).
 *
 * @api 1.0
 */
interface MediaAttachableInterface
{
    /** نوع پلی‌مورفیک برای ستون mediable_type (مثلاً `blog_post`). */
    public function mediaSubjectType(): string;

    /** شناسهٔ موجودیت برای ستون mediable_id. */
    public function mediaSubjectId(): string;
}
