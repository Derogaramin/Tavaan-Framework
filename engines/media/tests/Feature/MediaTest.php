<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Exceptions\Engine\EngineCapabilityException;
use Tavaan\Engine\Http\UploadedFile;
use Tavaan\Engine\Media\Exceptions\InvalidFileTypeException;
use Tavaan\Engine\Media\Exceptions\MediaException;
use Tavaan\Engine\Media\Image;
use Tavaan\Engine\Media\Media;
use Tavaan\Engine\Media\MediaManager;
use Tavaan\Engine\Media\MimeValidator;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Media — DOC-074 §9 (T-01..T-10).
 */
final class MediaTest extends TestCase
{
    /** JPEG معتبر ۱×۱ (base64) */
    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDs0NDT/wAALCAABAAEBAREA/8QAFAABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AmgAAAP/Z';

    private Application $app;

    private string $tmp;

    protected function setUp(): void
    {
        Clock::setTestNow(null);

        $this->tmp = \sys_get_temp_dir() . '/tvn-media-test-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp . '/root', 0775, true);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('filesystem.disks.mediatest', ['driver' => 'local', 'root' => $this->tmp . '/root', 'visibility' => 'private']);
        $config->set('media.disk', 'mediatest');
        $config->set('media.paranoid_scan', true);
        $config->set('media.dedup', true);

        (require \dirname(__DIR__, 2) . '/migrations/2026_05_03_000001_create_media_table.php')->up();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
        $this->rrmdir($this->tmp);
    }

    private function media(): MediaManager
    {
        return $this->app->make(MediaManager::class);
    }

    /** JPEG معتبر روی دیسک محلی + UploadedFile. */
    private function jpegUpload(string $name = 'photo.jpg', string $extra = ''): UploadedFile
    {
        $path = $this->tmp . '/' . $name;
        \file_put_contents($path, \base64_decode(self::JPEG_1X1, true) . $extra);

        return new UploadedFile($path, $name, 'image/jpeg', (int) \filesize($path), 0);
    }

    // ─── T-01: آپلود تصویر معتبر ─────────────────────────────────────────

    public function testUploadValidImage(): void
    {
        $media = $this->media()->upload($this->jpegUpload(), folder: 'products');

        $this->assertSame('image/jpeg', $media->mimeType);
        $this->assertSame(1, $media->width);
        $this->assertSame(1, $media->height);
        $this->assertSame('photo.jpg', $media->originalName);
        $this->assertTrue($media->isImage());

        // 🔒 نام ذخیره‌شده تصادفی است — نه نام اصلی
        $this->assertMatchesRegex('#^products/[a-f0-9]{32}\.jpg$#', $media->path);

        // فایل واقعاً روی disk است
        $fs = $this->app->make('filesystem')->disk('mediatest');
        $this->assertTrue($fs->exists($media->path));

        // رکورد کتابخانه
        $found = $this->media()->find($media->id);
        $this->assertNotNull($found);
        $this->assertSame($media->hash, $found->hash);
        $this->assertSame(64, \strlen($media->hash));   // SHA-256
    }

    // ─── T-02: `.php` با image MIME → رد ─────────────────────────────────

    public function testPhpShellWithImageMimeRejected(): void
    {
        // بایت‌های JPEG اما پسوند php
        $upload = $this->jpegUpload('evil.php');

        try {
            $this->media()->upload($upload);
            $this->fail('آپلود shell باید رد شود');
        } catch (MediaException $e) {
            $this->assertSame('media.extension_not_allowed', $e->messageId);
        }
    }

    // ─── T-03: magic bytes detection + MIME spoofing ─────────────────────

    public function testMagicBytesDetection(): void
    {
        $validator = new MimeValidator();

        $jpegPath = $this->tmp . '/detect.jpg';
        \file_put_contents($jpegPath, (string) \base64_decode(self::JPEG_1X1, true));
        $this->assertSame('image/jpeg', $validator->detectRealMime($jpegPath));

        $pngPath = $this->tmp . '/detect.png';
        \file_put_contents($pngPath, "\x89PNG\r\n\x1a\n" . \str_repeat("\0", 32));
        $this->assertSame('image/png', $validator->detectRealMime($pngPath));
    }

    public function testMimeSpoofingRejected(): void
    {
        // بایت‌های PNG با نام .jpg → extension mismatch
        $path = $this->tmp . '/fake.jpg';
        \file_put_contents($path, "\x89PNG\r\n\x1a\n" . \str_repeat("\0", 32));
        $upload = new UploadedFile($path, 'fake.jpg', 'image/jpeg', (int) \filesize($path), 0);

        try {
            $this->media()->upload($upload);
            $this->fail('MIME جعلی باید رد شود');
        } catch (InvalidFileTypeException $e) {
            $this->assertSame('media.extension_mismatch', $e->messageId);
        }

        // محتوای اجرایی (ELF) با نام .jpg → mime_mismatch (خارج از allowlist)
        $path2 = $this->tmp . '/notimage.jpg';
        \file_put_contents($path2, "\x7fELF\x02\x01\x01\x00" . \random_bytes(64));
        $upload2 = new UploadedFile($path2, 'notimage.jpg', 'image/jpeg', (int) \filesize($path2), 0);

        try {
            $this->media()->upload($upload2);
            $this->fail('محتوای غیرمجاز باید رد شود');
        } catch (InvalidFileTypeException $e) {
            $this->assertSame('media.mime_mismatch', $e->messageId);
        }
    }

    public function testPolyglotFileRejected(): void
    {
        // JPEG معتبر + کد PHP جاسازی‌شده
        $upload = $this->jpegUpload('poly.jpg', "<?php system(\$_GET['c']); ?>");

        try {
            $this->media()->upload($upload);
            $this->fail('polyglot باید رد شود');
        } catch (InvalidFileTypeException $e) {
            $this->assertSame('media.polyglot_detected', $e->messageId);
        }
    }

    // ─── T-07: image bomb ────────────────────────────────────────────────

    public function testImageBombRejected(): void
    {
        // PNG با IHDR claiming 30000×30000 — بدون decode واقعی
        $ihdr = \pack('NN', 30000, 30000) . "\x08\x02\x00\x00\x00";
        $png = "\x89PNG\r\n\x1a\n"
            . \pack('N', 13) . 'IHDR' . $ihdr . \pack('N', \crc32('IHDR' . $ihdr));

        $path = $this->tmp . '/bomb.png';
        \file_put_contents($path, $png);
        $upload = new UploadedFile($path, 'bomb.png', 'image/png', (int) \filesize($path), 0);

        try {
            $this->media()->upload($upload);
            $this->fail('image bomb باید رد شود');
        } catch (InvalidFileTypeException $e) {
            $this->assertSame('media.image_bomb', $e->messageId);
        }
    }

    public function testTooLargeRejected(): void
    {
        $this->app->make('config')->set('media.max_size', 10);

        try {
            $this->media()->upload($this->jpegUpload('big.jpg'));
            $this->fail('فایل بزرگ باید رد شود');
        } catch (MediaException $e) {
            $this->assertSame('media.too_large', $e->messageId);
        }
    }

    // ─── T-08: بدون gd → EngineCapabilityException ───────────────────────

    public function testImageProcessingWithoutDriverThrowsCapability(): void
    {
        $jpegPath = $this->tmp . '/cap.jpg';
        \file_put_contents($jpegPath, (string) \base64_decode(self::JPEG_1X1, true));

        $image = Image::make($jpegPath);

        if ($image->driver() !== 'none') {
            $this->assertTrue(true);   // gd/imagick موجود — این سناریو N/A

            return;
        }

        try {
            $image->resize(100, 100);
            $this->fail('بدون درایور باید EngineCapabilityException پرتاب شود');
        } catch (EngineCapabilityException $e) {
            $this->assertSame('engine.capability_missing', $e->messageId);
            $this->assertSame('image.resize', $e->context['feature'] ?? '');
        }

        // save بدون عملیات = «فقط ذخیره» — مجاز
        $copy = $this->tmp . '/cap-copy.jpg';
        Image::make($jpegPath)->save($copy);
        $this->assertTrue(\is_file($copy));

        // metadata بدون درایور کار می‌کند
        $this->assertSame([1, 1], Image::make($jpegPath)->dimensions());
    }

    // ─── T-04/T-05/T-06: پردازش تصویر (فقط با gd/imagick) ───────────────

    public function testResizeThumbnailConvertWhenDriverAvailable(): void
    {
        $jpegPath = $this->tmp . '/proc.jpg';
        \file_put_contents($jpegPath, (string) \base64_decode(self::JPEG_1X1, true));

        $image = Image::make($jpegPath);
        if ($image->driver() === 'none') {
            $this->assertTrue(true);   // محیط بدون gd/imagick — T-08 پوشش داد

            return;
        }

        $out = $this->tmp . '/resized.jpg';
        $image->resize(2, 2)->save($out);
        $this->assertTrue(\is_file($out));

        $thumb = $this->tmp . '/thumb.jpg';
        Image::make($jpegPath)->thumbnail(1, 1)->save($thumb);
        $this->assertTrue(\is_file($thumb));

        if (\function_exists('imagewebp')) {
            $webp = $this->tmp . '/conv.webp';
            Image::make($jpegPath)->convert('webp', 80)->save($webp);
            $this->assertTrue(\is_file($webp));
        }
    }

    // ─── T-09: dedup با hash ─────────────────────────────────────────────

    public function testDeduplicationByHash(): void
    {
        $first = $this->media()->upload($this->jpegUpload('a.jpg'));
        $second = $this->media()->upload($this->jpegUpload('b.jpg'));

        // محتوای یکسان → همان رکورد
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->path, $second->path);

        // فقط یک فایل روی disk
        $files = $this->app->make('filesystem')->disk('mediatest')->allFiles('media');
        $this->assertCount(1, $files);
    }

    // ─── T-10: EXIF ──────────────────────────────────────────────────────

    public function testExifAndMetadata(): void
    {
        $jpegPath = $this->tmp . '/exif.jpg';
        \file_put_contents($jpegPath, (string) \base64_decode(self::JPEG_1X1, true));

        $exif = Image::make($jpegPath)->exif();
        $this->assertTrue(\is_array($exif));

        // meta در کتابخانه فقط کلیدهای امن (بدون GPS)
        $media = $this->media()->upload($this->jpegUpload('meta.jpg'));
        $this->assertArrayNotHasKey('GPSLatitude', $media->meta);

        if (\function_exists('exif_read_data')) {
            $raw = @\exif_read_data($jpegPath);
            $this->assertTrue(\is_array($raw) || $raw === false);
        }
    }

    // ─── fromPath / attach / delete ──────────────────────────────────────

    public function testFromPathAttachAndDelete(): void
    {
        $path = $this->tmp . '/local.jpg';
        \file_put_contents($path, (string) \base64_decode(self::JPEG_1X1, true));

        $media = $this->media()->fromPath($path, folder: 'docs', originalName: 'my file.jpg');
        $this->assertSame('image/jpeg', $media->mimeType);
        $this->assertSame('my file.jpg', $media->originalName);
        $this->assertStringContainsString('docs/', $media->path);

        // attach به موجودیت
        $attached = $this->media()->attach($media, 'product', 42);
        $this->assertSame('product', $attached->mediableType);
        $this->assertSame('42', $attached->mediableId);

        // delete: فایل + رکورد
        $fs = $this->app->make('filesystem')->disk('mediatest');
        $this->assertTrue($fs->exists($media->path));

        $this->assertTrue($this->media()->delete($media));
        $this->assertFalse($fs->exists($media->path));
        $this->assertNull($this->media()->find($media->id));
    }

    public function testVariantsWithoutDriverThrowsCapability(): void
    {
        if (Image::resolveDriver('auto') !== 'none') {
            $this->assertTrue(true);   // با درایور — سناریوی N/A در این محیط

            return;
        }

        $media = $this->media()->upload($this->jpegUpload('v.jpg'));

        try {
            $this->media()->variants($media, ['thumb' => ['w' => 1, 'h' => 1, 'fit' => 'crop']]);
            $this->fail('variants بدون درایور باید رد شود');
        } catch (EngineCapabilityException $e) {
            $this->assertSame('engine.capability_missing', $e->messageId);
        }
    }

    // ─── ابزار ───────────────────────────────────────────────────────────

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            \is_dir($path) ? $this->rrmdir($path) : \unlink($path);
        }
        \rmdir($dir);
    }
}
