<?php

declare(strict_types=1);

namespace Modules\Media\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Http\KernelInterface as HttpKernelInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Media;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\UploadedFile;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E کتابخانهٔ رسانه (ماژول media) — فهرست/آپلود/حذف با RBAC.
 *
 * آپلود با Request دستیِ دارای file (مسیر واقعی Kernel) و بقیه با
 * MakesHttpRequests. disk موقت + SQLite in-memory — بدون I/O ماندگار.
 */
final class MediaLibraryTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    /** JPEG 1×1 معتبر (fixture base64) */
    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDs0NDT/wAALCAABAAEBAREA/8QAFAABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AmgAAAP/Z';

    private UserRepositoryInterface $users;

    private RoleManager $roles;

    private string $tmp;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*']);

        // disk موقت برای فایل‌های رسانه
        $this->tmp = \sys_get_temp_dir() . '/tvn-media-mod-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp . '/root', 0775, true);
        $config->set('filesystem.disks.mediatest', ['driver' => 'local', 'root' => $this->tmp . '/root', 'visibility' => 'private']);
        $config->set('media.disk', 'mediatest');
        $config->set('media.dedup', false);
        $config->set('media.reencode', false);

        $this->users = $app->make(UserRepositoryInterface::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmp ?? '');
        parent::tearDown();
    }

    // ─── دسترسی ───────────────────────────────────────────────────────────

    public function testGuestRedirectedToLogin(): void
    {
        $this->get('/admin/media')->assertRedirect();
    }

    public function testUserWithoutPermissionGets403(): void
    {
        $plain = $this->makeUser('plain@test.dev', role: 'user');
        $this->actingAs($plain)->get('/admin/media')->assertForbidden();
    }

    // ─── فهرست ─────────────────────────────────────────────────────────────

    public function testIndexShowsLibraryItems(): void
    {
        $admin = $this->makeUser('admin@test.dev', role: 'admin');
        $id = $this->seedMediaFile('نمونه-تست.jpg');

        $page = $this->actingAs($admin)->get('/admin/media');
        $page->assertOk();
        $page->assertSee('کتابخانهٔ رسانه');
        $page->assertSee('نمونه-تست.jpg');

        self::assertNotNull(Media::find($id));
    }

    // ─── آپلود ─────────────────────────────────────────────────────────────

    public function testUploadStoresFileInLibrary(): void
    {
        $admin = $this->makeUser('admin2@test.dev', role: 'admin');
        $this->actingAs($admin);

        $tmpFile = $this->tmp . '/upload-source.jpg';
        \file_put_contents($tmpFile, (string) \base64_decode(self::JPEG_1X1, true));
        $upload = new UploadedFile($tmpFile, 'آپلود-جدید.jpg', 'image/jpeg', (int) \filesize($tmpFile), 0);

        $request = Request::create('/admin/media', 'POST', [], [], ['media_file' => $upload]);
        $kernel = $this->app()->make(HttpKernelInterface::class);
        $response = $kernel->handle($request);

        self::assertTrue($response->statusCode() === 302 || $response->statusCode() === 200, 'آپلود باید موفق شود — status: ' . $response->statusCode());

        $row = DB::table('tvn_media')->where('original_name', 'آپلود-جدید.jpg')->first();
        self::assertNotNull($row, 'رکورد رسانهٔ آپلودشده در tvn_media ثبت نشد');
    }

    public function testUploadWithoutFileRedirectsWithError(): void
    {
        $admin = $this->makeUser('admin3@test.dev', role: 'admin');

        $this->actingAs($admin)->post('/admin/media', [])->assertRedirect('/admin/media');
    }

    // ─── حذف ───────────────────────────────────────────────────────────────

    public function testDeleteRemovesRecord(): void
    {
        $admin = $this->makeUser('admin4@test.dev', role: 'admin');
        $id = $this->seedMediaFile('حذف-شونده.jpg');

        $this->actingAs($admin)->delete('/admin/media/' . $id)->assertRedirect('/admin/media');

        self::assertNull(Media::find($id));
    }

    public function testDeleteUnknownId404(): void
    {
        $admin = $this->makeUser('admin5@test.dev', role: 'admin');

        $this->actingAs($admin)->delete('/admin/media/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    }

    // ─── کمکی ──────────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        foreach (['media.view', 'media.upload', 'media.delete'] as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.media');
            }
        }

        foreach (['admin', 'user'] as $roleKey) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
        }

        foreach (['media.view', 'media.upload', 'media.delete'] as $key) {
            $this->roles->grantPermissionToRole('admin', $key);
        }
    }

    private function makeUser(string $email, string $role): User
    {
        $user = $this->users->create(name: 'کاربر ' . $role, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    /** ثبت یک فایل واقعی در کتابخانه (از راه موتور) و بازگرداندن شناسه. */
    private function seedMediaFile(string $originalName): string
    {
        $path = $this->tmp . '/seed-' . \random_int(1000, 9999) . '.jpg';
        \file_put_contents($path, (string) \base64_decode(self::JPEG_1X1, true));

        $media = Media::fromPath($path, originalName: $originalName);

        return (string) $media->id;
    }

    private function removeDir(string $dir): void
    {
        if ($dir === '' || !\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($dir);
    }
}
