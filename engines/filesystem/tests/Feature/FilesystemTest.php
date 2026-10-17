<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Tests\Feature;

require_once __DIR__ . '/../Fixtures/FakeS3Transport.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Exceptions\Filesystem\PathTraversalException;
use Tavaan\Engine\Filesystem\Adapters\FtpFilesystem;
use Tavaan\Engine\Filesystem\Adapters\LocalFilesystem;
use Tavaan\Engine\Filesystem\Adapters\MemoryFilesystem;
use Tavaan\Engine\Filesystem\Adapters\S3Filesystem;
use Tavaan\Engine\Filesystem\Adapters\S3Signer;
use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;
use Tavaan\Engine\Filesystem\FilesystemManager;
use Tavaan\Engine\Filesystem\Tests\Fixtures\FakeS3Transport;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\UploadedFile;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Filesystem — DOC-063 §9 (T-01..T-08).
 */
final class FilesystemTest extends TestCase
{
    private Application $app;

    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-fs-test-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp . '/root', 0775, true);
        \mkdir($this->tmp . '/outside', 0775, true);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('filesystem.default', 'local');
        $config->set('filesystem.disks.local', ['driver' => 'local', 'root' => $this->tmp . '/root', 'visibility' => 'private']);
        $config->set('filesystem.disks.public', ['driver' => 'local', 'root' => $this->tmp . '/root/public', 'url' => 'http://localhost/storage', 'visibility' => 'public']);
        $config->set('filesystem.disks.memory', ['driver' => 'memory', 'url' => 'http://localhost/mem']);
        $config->set('filesystem.links', [$this->tmp . '/link' => $this->tmp . '/root/public']);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    private function manager(): FilesystemManager
    {
        return $this->app->make(FilesystemManager::class);
    }

    private function local(): LocalFilesystem
    {
        $disk = $this->manager()->disk('local');
        \assert($disk instanceof LocalFilesystem);

        return $disk;
    }

    private function s3(?FakeS3Transport $fake = null): S3Filesystem
    {
        return new S3Filesystem('s3', [
            'bucket' => 'test-bucket',
            'key' => 'AKIAIOSFODNN7EXAMPLE',
            'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'region' => 'us-east-1',
            'use_path_style' => true,
            'endpoint' => 'http://s3.fake',
        ], $fake ?? new FakeS3Transport());
    }

    // ─── T-01: put/get/exists/delete در درایورها ──────────────────────────

    public function testLocalDriverCrud(): void
    {
        $fs = $this->local();

        $this->assertTrue($fs->put('docs/hello.txt', 'سلام دنیا'));
        $this->assertTrue($fs->exists('docs/hello.txt'));
        $this->assertSame('سلام دنیا', $fs->get('docs/hello.txt'));
        $this->assertFalse($fs->missing('docs/hello.txt'));
        $this->assertSame(\strlen('سلام دنیا'), $fs->size('docs/hello.txt'));
        $this->assertGreaterThan(0, $fs->lastModified('docs/hello.txt'));

        $this->assertTrue($fs->copy('docs/hello.txt', 'copy/hello.txt'));
        $this->assertSame('سلام دنیا', $fs->get('copy/hello.txt'));

        $this->assertTrue($fs->move('copy/hello.txt', 'moved/hello.txt'));
        $this->assertFalse($fs->exists('copy/hello.txt'));
        $this->assertTrue($fs->exists('moved/hello.txt'));

        $this->assertTrue($fs->append('docs/hello.txt', '!'));
        $this->assertSame('سلام دنیا!', $fs->get('docs/hello.txt'));

        $this->assertTrue($fs->prepend('docs/hello.txt', '# '));
        $this->assertSame('# سلام دنیا!', $fs->get('docs/hello.txt'));

        $this->assertTrue($fs->delete('docs/hello.txt'));
        $this->assertNull($fs->get('docs/hello.txt'));
        $this->assertTrue($fs->missing('docs/hello.txt'));
    }

    public function testMemoryDriverCrud(): void
    {
        $fs = new MemoryFilesystem('memory');

        $this->assertTrue($fs->put('a/b.txt', 'content'));
        $this->assertTrue($fs->exists('a/b.txt'));
        $this->assertSame('content', $fs->get('a/b.txt'));
        $this->assertSame(7, $fs->size('a/b.txt'));
        $this->assertSame('text/plain', $fs->mimeType('a/b.txt'));
        $this->assertSame(['a/b.txt'], $fs->allFiles());
        $this->assertTrue($fs->delete('a/b.txt'));
        $this->assertNull($fs->get('a/b.txt'));
    }

    public function testS3DriverCrudWithFakeTransport(): void
    {
        $fake = new FakeS3Transport();
        $fs = $this->s3($fake);

        $this->assertTrue($fs->put('docs/hello.txt', 's3 content'));
        $this->assertTrue($fs->exists('docs/hello.txt'));
        $this->assertSame('s3 content', $fs->get('docs/hello.txt'));
        $this->assertSame(10, $fs->size('docs/hello.txt'));
        $this->assertGreaterThan(0, $fs->lastModified('docs/hello.txt'));

        $this->assertTrue($fs->copy('docs/hello.txt', 'backup/hello.txt'));
        $this->assertSame(['backup/hello.txt', 'docs/hello.txt'], $fake->keys());

        $this->assertTrue($fs->move('backup/hello.txt', 'moved/hello.txt'));
        $this->assertFalse($fs->exists('backup/hello.txt'));

        $this->assertTrue($fs->delete('moved/hello.txt'));
        $this->assertSame(['docs/hello.txt'], $fake->keys());

        // درخواست‌ها امضاشده هستند
        foreach ($fake->requests as $request) {
            $this->assertArrayHasKey('Authorization', $request['headers']);
            $this->assertStringContainsString('AWS4-HMAC-SHA256', $request['headers']['Authorization']);
        }
    }

    public function testS3ListingFilesAndDirectories(): void
    {
        $fake = new FakeS3Transport();
        $fs = $this->s3($fake);

        $fs->put('photos/a.jpg', 'a');
        $fs->put('photos/2024/b.jpg', 'b');
        $fs->put('docs/x.pdf', 'x');

        $this->assertSame(['photos/a.jpg'], $fs->files('photos'));
        $this->assertSame(['photos/2024'], $fs->directories('photos'));
        $this->assertSame(['photos/2024/b.jpg', 'photos/a.jpg'], $fs->allFiles('photos'));
        $this->assertSame(['docs/x.pdf', 'photos/2024/b.jpg', 'photos/a.jpg'], $fs->allFiles());
        $this->assertSame(['docs', 'photos', 'photos/2024'], $fs->allDirectories());
    }

    public function testS3DirectoryAndVisibilityAndUrls(): void
    {
        $fake = new FakeS3Transport();
        $fs = $this->s3($fake);

        $this->assertTrue($fs->makeDirectory('empty-dir'));
        $this->assertContains('empty-dir/', $fake->keys());

        $fs->put('private.txt', 'secret');
        $this->assertSame('private', $fs->visibility('private.txt'));
        $this->assertTrue($fs->setVisibility('private.txt', 'public'));
        $this->assertSame('public', $fs->visibility('private.txt'));

        $this->assertSame('http://s3.fake/test-bucket/private.txt', $fs->url('private.txt'));

        $tempUrl = $fs->temporaryUrl('private.txt', new \DateTimeImmutable('+10 minutes'));
        $this->assertStringContainsString('X-Amz-Signature=', $tempUrl);
        $this->assertStringContainsString('X-Amz-Expires=', $tempUrl);
        $this->assertStringContainsString('X-Amz-Credential=AKIAIOSFODNN7EXAMPLE', $tempUrl);
    }

    public function testFtpDriverConfigurationSmoke(): void
    {
        $fs = new FtpFilesystem('ftp', ['host' => 'ftp.example.com', 'username' => 'u', 'password' => 'p', 'root' => '/srv/www']);

        $this->assertSame('ftp', $fs->diskName());
        $this->assertSame('ftp://ftp.example.com/srv/www/a.txt', $fs->url('a.txt'));

        try {
            $fs->temporaryUrl('a.txt', new \DateTimeImmutable('+1 hour'));
            $this->fail('temporaryUrl روی FTP باید رد شود');
        } catch (FilesystemException $e) {
            $this->assertSame('filesystem.not_supported', $e->messageId);
        }
    }

    // ─── T-02/T-03: Path Traversal و Symlink Escape ─────────────────────

    public function testPathTraversalRejected(): void
    {
        $fs = $this->local();

        foreach (['../../etc/passwd', 'a/../../etc/passwd', "a\0b.txt", '..'] as $evil) {
            try {
                $fs->get($evil);
                $this->fail('مسیر خطرناک باید رد شود: ' . \addcslashes($evil, "\0"));
            } catch (PathTraversalException $e) {
                $this->assertSame('filesystem.path_traversal', $e->messageId);
            }
        }

        // مسیر مطلق → داخل sandbox تفسیر می‌شود (فرار ممکن نیست)
        $this->assertNull($fs->get('/etc/passwd'));
        $this->assertFalse(\file_exists($this->tmp . '/root/etc/passwd'));
    }

    public function testSymlinkEscapeRejected(): void
    {
        if (!self::symlinkSupported()) {
            // 🔧 ویندوز بدون Developer Mode/ادمین: ساخت symlink ممکن نیست → skip
            $this->assertTrue(true);

            return;
        }

        $fs = $this->local();

        // symlink داخل root به بیرون
        \symlink($this->tmp . '/outside', $this->tmp . '/root/escape');

        try {
            $fs->get('escape/secret.txt');
            $this->fail('فرار از symlink باید رد شود');
        } catch (PathTraversalException $e) {
            $this->assertSame('filesystem.path_traversal', $e->messageId);
        }

        // symlink معتبر داخل root → مجاز
        \file_put_contents($this->tmp . '/root/real.txt', 'real');
        \symlink($this->tmp . '/root/real.txt', $this->tmp . '/root/inside-link');
        $this->assertSame('real', $fs->get('inside-link'));
    }

    // ─── T-04: Atomic write ──────────────────────────────────────────────

    public function testAtomicWriteLeavesNoTempFiles(): void
    {
        $fs = $this->local();
        $content = \str_repeat('توان-', 10000);

        $this->assertTrue($fs->put('atomic/big.txt', $content));
        $this->assertSame($content, $fs->get('atomic/big.txt'));

        // هیچ فایل tmp باقی نمانده
        $leftovers = \glob($this->tmp . '/root/atomic/*.tmp') ?: [];
        $this->assertCount(0, $leftovers);
    }

    // ─── T-05: S3 signing با AWS test vectors ────────────────────────────

    public function testS3SignerMatchesAwsReferenceVector(): void
    {
        // بردار مرجع AWS (S3 API docs — GET Object example)
        $signer = new S3Signer(
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'us-east-1',
            's3',
        );

        $now = new \DateTimeImmutable('2013-05-24 00:00:00', new \DateTimeZone('UTC'));
        $headers = $signer->signRequest(
            'GET',
            '/test.txt',
            [],
            ['host' => 'examplebucket.s3.amazonaws.com', 'range' => 'bytes=0-9'],
            '',
            $now,
        );

        $this->assertSame('20130524T000000Z', $headers['x-amz-date']);
        $this->assertSame(S3Signer::EMPTY_PAYLOAD_HASH, $headers['x-amz-content-sha256']);
        $this->assertStringContainsString(
            'Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41',
            $headers['Authorization'],
        );
        $this->assertStringContainsString(
            'Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request',
            $headers['Authorization'],
        );
        $this->assertStringContainsString(
            'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date',
            $headers['Authorization'],
        );
    }

    public function testS3SignerPutObjectVector(): void
    {
        // بردار مرجع AWS (PUT Object example)
        $signer = new S3Signer(
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'us-east-1',
            's3',
        );

        $now = new \DateTimeImmutable('2013-05-24 00:00:00', new \DateTimeZone('UTC'));
        $headers = $signer->signRequest(
            'PUT',
            '/test$file.text',
            [],
            [
                'host' => 'examplebucket.s3.amazonaws.com',
                'date' => 'Fri, 24 May 2013 00:00:00 GMT',
                'x-amz-storage-class' => 'REDUCED_REDUNDANCY',
            ],
            'Welcome to Amazon S3.',
            $now,
        );

        $this->assertStringContainsString(
            'SignedHeaders=date;host;x-amz-content-sha256;x-amz-date;x-amz-storage-class',
            $headers['Authorization'],
        );

        $this->assertStringContainsString(
            'Signature=98ad721746da40c64f1a55b78f14c238d841ea1380cd77a1b5971af0ece108bd',
            $headers['Authorization'],
        );
    }

    // ─── T-06: readStream با حافظهٔ ثابت ─────────────────────────────────

    public function testReadStreamUsesConstantMemory(): void
    {
        $fs = $this->local();
        $size = 4 * 1024 * 1024;   // 4MB
        \file_put_contents($this->tmp . '/root/big.bin', \str_repeat('A', $size));

        $stream = $fs->readStream('big.bin');
        $this->assertNotNull($stream);

        $before = \memory_get_usage();
        $hash = \hash_init('sha256');
        $read = 0;
        while (!\feof($stream)) {
            $chunk = \fread($stream, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            \hash_update($hash, $chunk);
            $read += \strlen($chunk);
        }
        \fclose($stream);

        $delta = \memory_get_usage() - $before;

        $this->assertSame($size, $read);
        $this->assertSame(\hash('sha256', \str_repeat('A', $size)), \hash_final($hash));
        $this->assertLessThan(1024 * 1024, $delta);   // حافظهٔ ثابت (< 1MB)
    }

    public function testWriteStreamChunked(): void
    {
        $fs = $this->local();
        $source = \fopen('php://temp', 'w+b');
        \fwrite($source, \str_repeat('B', 1024 * 1024));
        \rewind($source);

        $this->assertTrue($fs->writeStream('stream/out.bin', $source));
        \fclose($source);

        $this->assertSame(1024 * 1024, $fs->size('stream/out.bin'));
    }

    // ─── T-07: temporaryUrl امضاشده ──────────────────────────────────────

    public function testLocalTemporaryUrlIsSignedAndVerifiable(): void
    {
        $fs = $this->local();
        $fs->put('private/report.pdf', 'PDF');

        $expiration = new \DateTimeImmutable('+10 minutes');
        $url = $fs->temporaryUrl('private/report.pdf', $expiration);

        $this->assertStringContainsString('expires=' . $expiration->getTimestamp(), $url);
        $this->assertStringContainsString('signature=', $url);

        // امضا با کلید اپ (crypt) قابل تأیید است
        $query = [];
        \parse_str((string) \parse_url($url, \PHP_URL_QUERY), $query);
        $encrypter = $this->app->make('encrypter');
        $expected = $encrypter->hmac('private/report.pdf|' . $query['expires'], 'filesystem.tempurl');
        $this->assertSame($expected, $query['signature']);
    }

    public function testTemporaryUrlWithoutCryptFails(): void
    {
        $fs = new LocalFilesystem('bare', ['root' => $this->tmp . '/root']);   // بدون signer

        try {
            $fs->temporaryUrl('x.txt', new \DateTimeImmutable('+5 minutes'));
            $this->fail('بدون signer باید استثنا پرتاب شود');
        } catch (FilesystemException $e) {
            $this->assertSame('filesystem.signing_unavailable', $e->messageId);
        }
    }

    // ─── T-08: directories/files recursive ───────────────────────────────

    public function testDirectoryListingRecursive(): void
    {
        $fs = $this->local();
        $fs->put('top.txt', '1');
        $fs->put('dir/a.txt', '2');
        $fs->put('dir/nested/b.txt', '3');
        $fs->makeDirectory('empty-dir');

        $this->assertSame(['top.txt'], $fs->files());
        $this->assertSame(['dir', 'empty-dir'], $fs->directories());
        $this->assertSame(['dir/a.txt'], $fs->files('dir'));
        $this->assertSame(['dir/nested'], $fs->directories('dir'));
        $this->assertSame(['dir/a.txt', 'dir/nested/b.txt', 'top.txt'], $fs->allFiles());
        $this->assertSame(['dir', 'dir/nested', 'empty-dir'], $fs->allDirectories());

        $this->assertTrue($fs->deleteDirectory('dir'));
        $this->assertFalse($fs->exists('dir/a.txt'));
        $this->assertFalse($fs->exists('dir/nested/b.txt'));
    }

    // ─── Manager: disk/extend/putFile/download ──────────────────────────

    public function testManagerResolvesDisksAndDelegates(): void
    {
        $manager = $this->manager();

        $this->assertSame('local', $manager->getDefaultDisk());
        $this->assertTrue($manager->put('via-manager.txt', 'ok'));
        $this->assertSame('ok', $manager->get('via-manager.txt'));
        $this->assertInstanceOf(MemoryFilesystem::class, $manager->disk('memory'));

        // disk ناشناخته
        try {
            $manager->disk('nope');
            $this->fail('disk ناشناخته باید رد شود');
        } catch (FilesystemException $e) {
            $this->assertSame('filesystem.disk_not_configured', $e->messageId);
        }

        // درایور سفارشی با extend
        $manager->extend('memory', static fn (array $config): MemoryFilesystem => new MemoryFilesystem('custom'));
        $this->assertInstanceOf(MemoryFilesystem::class, $manager->disk('memory'));
    }

    public function testPutFileWithUploadedFile(): void
    {
        // فایل آپلودشدهٔ ساختگی
        $uploadPath = $this->tmp . '/upload-photo.jpg';
        \file_put_contents($uploadPath, "\xFF\xD8\xFF\xE0fake-jpeg-body");
        $file = new UploadedFile($uploadPath, 'my photo.jpg', 'image/jpeg', (int) \filesize($uploadPath), 0);

        $manager = $this->manager();
        $path = $manager->putFile('avatars', $file);

        $this->assertTrue(\is_string($path));
        $this->assertMatchesRegex('#^avatars/[a-f0-9]{40}\.jpg$#', (string) $path);
        $this->assertTrue($manager->exists((string) $path));
        // 🔒 نام اصلی (با فاصله) هرگز ذخیره نمی‌شود
        $this->assertFalse($manager->exists('avatars/my photo.jpg'));

        $pathAs = $manager->putFileAs('avatars', $file, 'fixed.jpg');
        $this->assertSame('avatars/fixed.jpg', $pathAs);
    }

    public function testDownloadReturnsAttachmentResponse(): void
    {
        $manager = $this->manager();
        $manager->put('files/report.pdf', 'PDF-CONTENT');

        $response = $manager->download('files/report.pdf', 'گزارش.pdf');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->statusCode());
        $this->assertSame('PDF-CONTENT', $response->body());
        $this->assertStringContainsString('attachment', (string) $response->header('Content-Disposition'));
    }

    public function testUrlGeneration(): void
    {
        $manager = $this->manager();

        $this->assertSame('http://localhost/storage/a.jpg', $manager->disk('public')->url('a.jpg'));
        $this->assertSame('http://localhost/mem/b.txt', $manager->disk('memory')->url('b.txt'));
    }

    public function testVisibilityLocal(): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            // 🔧 NTFS بیت‌های مجوز POSIX ندارد (chmod فقط پرچم read-only) → skip
            $this->assertTrue(true);

            return;
        }

        $fs = $this->local();
        $fs->put('vis.txt', 'x');

        $this->assertSame('private', $fs->visibility('vis.txt'));
        $this->assertTrue($fs->setVisibility('vis.txt', 'public'));
        $this->assertSame('public', $fs->visibility('vis.txt'));
    }

    public function testMimeTypeDetection(): void
    {
        $fs = $this->local();
        // PNG با magic bytes واقعی
        $fs->put('img.png', "\x89PNG\r\n\x1a\n" . \str_repeat("\0", 32));

        $this->assertSame('image/png', $fs->mimeType('img.png'));
        $fs->put('notes.txt', 'plain text');
        $this->assertSame('text/plain', $fs->mimeType('notes.txt'));
    }

    public function testCreatePublicLinks(): void
    {
        if (!self::symlinkSupported()) {
            // 🔧 ویندوز بدون مجوز symlink → skip (خود موتور graceful degrade می‌کند)
            $this->assertTrue(true);

            return;
        }

        $created = $this->manager()->createPublicLinks();

        $this->assertContains($this->tmp . '/link', $created);
        $this->assertTrue(\is_link($this->tmp . '/link'));
        \unlink($this->tmp . '/link');
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
            \is_dir($path) && !\is_link($path) ? $this->rrmdir($path) : \unlink($path);
        }
        \rmdir($dir);
    }

    /**
     * 🔧 آیا symlink در این محیط واقعاً کار می‌کند؟
     * (ویندوز: نیازمند ادمین/Developer Mode — probe عملی انجام می‌شود)
     */
    private static function symlinkSupported(): bool
    {
        if (!\function_exists('symlink')) {
            return false;
        }
        if (\PHP_OS_FAMILY !== 'Windows') {
            return true;
        }

        $dir = (string) \sys_get_temp_dir();
        $target = $dir . '/tvn-symlink-probe-' . \getmypid() . '.txt';
        $link = $dir . '/tvn-symlink-probe-' . \getmypid() . '.lnk';
        \file_put_contents($target, 'x');
        $ok = @\symlink($target, $link);
        @\unlink($link);
        @\unlink($target);

        return $ok;
    }
}
