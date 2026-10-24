<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول رسانه
 * FacadeMediaGateway — آداپتر پورت رسانه روی موتور media (از راه Facade هسته).
 */

namespace Modules\Media\Infrastructure;

use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\Media\Domain\ValueObjects\MediaFile;
use Tavaan\Core\Facade\Facades\Media;

/**
 * FacadeMediaGateway — پلی بین دامنهٔ ماژول‌ها و موتور media.
 *
 * 🔒 ARCH-003: فقط Facade هسته (`Tavaan\Core\Facade\Facades\Media`) — بدون
 * ارجاع مستقیم به کلاس‌های موتور؛ نگاشت به DTO ماژولی (`MediaFile`).
 *
 * @api 1.0
 * @see modules/media/README.md
 */
final class FacadeMediaGateway implements MediaGatewayInterface
{
    public function find(string $id): ?MediaFile
    {
        if ($id === '') {
            return null;
        }

        $media = Media::find($id);
        if (!\is_object($media)) {
            return null;
        }

        $url = \method_exists($media, 'url') ? (string) $media->url() : '';
        $row = \method_exists($media, 'toArray') ? (array) $media->toArray() : [];

        $variants = [];
        foreach ((array) ($row['variants'] ?? []) as $name => $_) {
            if (\method_exists($media, 'variantUrl')) {
                $variantUrl = $media->variantUrl((string) $name);
                if (\is_string($variantUrl) && $variantUrl !== '') {
                    $variants[(string) $name] = $variantUrl;
                }
            }
        }

        return new MediaFile(
            id: (string) ($row['id'] ?? $id),
            url: $url,
            mimeType: (string) ($row['mime_type'] ?? ''),
            width: isset($row['width']) ? (int) $row['width'] : null,
            height: isset($row['height']) ? (int) $row['height'] : null,
            variants: $variants,
        );
    }

    public function attach(string $mediaId, string $subjectType, string $subjectId): bool
    {
        $media = Media::find($mediaId);
        if (!\is_object($media)) {
            return false;
        }

        Media::attach($media, $subjectType, $subjectId);

        return true;
    }

    public function delete(string $mediaId): bool
    {
        return Media::delete($mediaId);
    }
}
