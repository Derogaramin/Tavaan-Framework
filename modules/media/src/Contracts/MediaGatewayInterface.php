<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaGatewayInterface — پورت دسترسی به کتابخانهٔ رسانه (Hexagonal).
 */

namespace Modules\Media\Contracts;

use Modules\Media\Domain\ValueObjects\MediaFile;

/**
 * پورت رسانه — آداپتر پیش‌فرض (`FacadeMediaGateway`) روی موتور media پیاده می‌شود.
 * ماژول‌های دامنه فقط به این پورت وابسته‌اند (ARCH-003).
 *
 * @api 1.0
 */
interface MediaGatewayInterface
{
    /** یافتن رسانه با شناسه (ULID) — null اگر موجود نباشد. */
    public function find(string $id): ?MediaFile;

    /** اتصال رسانه به یک موجودیت (mediable_type/id). */
    public function attach(string $mediaId, string $subjectType, string $subjectId): bool;

    /** حذف رسانه از کتابخانه. */
    public function delete(string $mediaId): bool;
}
