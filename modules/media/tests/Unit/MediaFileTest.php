<?php

declare(strict_types=1);

namespace Modules\Media\Tests\Unit;

use Modules\Media\Domain\ValueObjects\MediaFile;
use Tavaan\Testing\TestCase;

/**
 * تست واحد DTO رسانه — variantUrl/isImage.
 */
final class MediaFileTest extends TestCase
{
    public function testMediaFile_VariantsAndType(): void
    {
        $file = new MediaFile(
            id: '01HZX9C0FFEE000000000000AB',
            url: '/storage/blog/cover.jpg',
            mimeType: 'image/jpeg',
            width: 1200,
            height: 630,
            variants: ['thumb' => '/storage/blog/cover-thumb.jpg', 'og' => '/storage/blog/cover-og.jpg'],
        );

        self::assertSame('/storage/blog/cover-thumb.jpg', $file->variantUrl('thumb'));
        self::assertNull($file->variantUrl('missing'));
        self::assertTrue($file->isImage());
        self::assertFalse((new MediaFile(id: 'x', url: '/f.pdf', mimeType: 'application/pdf'))->isImage());
    }
}
