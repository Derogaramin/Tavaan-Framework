<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostStatus — وضعیت پست (DOC-094 §2: visible|hidden|deleted).
 */

namespace Modules\Forum\Domain\Enums;

/**
 * PostStatus — visible عمومی است؛ hidden فقط برای moderator قابل مشاهده؛
 * deleted نرم (soft) — ردیف باقی می‌ماند ولی در هیچ فهرستی نیست.
 *
 * @api 1.0
 */
enum PostStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case Deleted = 'deleted';

    public function isPublic(): bool
    {
        return $this === self::Visible;
    }

    /** آیا برای moderator/admin در فهرست moderation نشان داده می‌شود؟ */
    public function isModerationVisible(): bool
    {
        return $this !== self::Deleted;
    }

    public static function tryFromOrDefault(string $value): self
    {
        return self::tryFrom($value) ?? self::Visible;
    }
}
