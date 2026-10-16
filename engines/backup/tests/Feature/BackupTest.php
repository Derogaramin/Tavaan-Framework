<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Backup\BackupEncrypter;
use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Backup\DatabaseDumper;
use Tavaan\Engine\Backup\Exceptions\BackupException;
use Tavaan\Engine\Backup\Manifest;
use Tavaan\Engine\Backup\ZipReader;
use Tavaan\Engine\Compat\ZipWriterPure;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Backup — DOC-068 §10 (T-01..T-07).
 */
final class BackupTest extends TestCase
{
    private Application $app;

    private string $tmp;

    protected function setUp(): void
    {
        Clock::setTestNow(null);

        $this->tmp = \sys_get_temp_dir() . '/tvn-backup-test-' . \getmypid() . '-' . \random_int(10000, 99999);
        \mkdir($this->tmp . '/disk', 0775, true);
        \mkdir($this->tmp . '/appfiles', 0775, true);
        \file_put_contents($this->tmp . '/appfiles/data.txt', 'دادهٔ مهم ۱');
        \mkdir($this->tmp . '/appfiles/nested', 0775, true);
        \file_put_contents($this->tmp . '/appfiles/nested/deep.txt', 'deep-content');

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('filesystem.disks.backuptest', ['driver' => 'local', 'root' => $this->tmp . '/disk', 'visibility' => 'private']);
        $config->set('backup.disk', 'backuptest');
        $config->set('backup.key', 'test-backup-key-123');
        $config->set('backup.encrypt', true);
        $config->set('backup.verify_after', true);
        $config->set('backup.include_files', [$this->tmp . '/appfiles']);
        $config->set('backup.exclude', []);

        // جدول تست با داده
        $db = $this->db();
        $this->app->make('schema')->create('backup_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->text('note')->nullable();
        });
        $db->table('backup_items')->insert([
            ['name' => 'اول', 'note' => 'سطر یک; با semicolon'],
            ['name' => 'دوم', 'note' => "خط۱\nخط۲ با 'quote'"],
        ]);
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
        $down = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/down' : '';
        if ($down !== '' && \is_file($down)) {
            \unlink($down);
        }
        $this->rrmdir($this->tmp);
    }

    private function db(): \Tavaan\Engine\Database\Connection
    {
        return $this->app->make('db')->connection();
    }

    private function backup(): BackupManager
    {
        return $this->app->make(BackupManager::class);
    }

    // ─── T-01: backup full → manifest + hash ─────────────────────────────

    public function testFullBackupCreatesManifestAndArchive(): void
    {
        $manifest = $this->backup()->run();

        $this->assertMatchesRegex('/^bkp_[0-9A-Z]{26}$/', $manifest->id);
        $this->assertSame('full', $manifest->type);
        $this->assertTrue($manifest->encrypted);
        $this->assertGreaterThan(0, $manifest->size);
        $this->assertStringContainsString('sha256:', $manifest->hash);
        $this->assertTrue($manifest->contents['database']);
        $this->assertGreaterThan(0, \count($manifest->contents['files']));

        // آرشیو + manifest روی disk
        $disk = $this->app->make('filesystem')->disk('backuptest');
        $this->assertTrue($disk->exists($manifest->archiveName()));
        $this->assertTrue($disk->exists($manifest->manifestName()));

        // verify پس از ساخت (verify_after)
        $this->assertTrue($this->backup()->verify($manifest->id));

        // list
        $list = $this->backup()->list();
        $this->assertCount(1, $list);
        $this->assertSame($manifest->id, $list[0]->id);
    }

    // ─── T-02: DB dump خالص و chunked ────────────────────────────────────

    public function testDatabaseDumpIsPureSql(): void
    {
        $dumper = new DatabaseDumper();
        $sql = $dumper->dump($this->db());

        // schema + data
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('backup_items', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('اول', $sql);

        // 🔒 مقدار دارای `;` و newline و quote سالم escape شده
        $this->assertStringContainsString('semicolon', $sql);

        // dump روی فایل (مسیر streaming) هم کار می‌کند
        $path = $this->tmp . '/dump.sql';
        $tables = $dumper->dumpToFile($this->db(), $path);
        $this->assertSame(1, $tables);
        $this->assertStringContainsString('INSERT INTO', (string) \file_get_contents($path));
    }

    public function testDatabaseOnlyBackup(): void
    {
        $manifest = $this->backup()->database();

        $this->assertSame('database', $manifest->type);
        $this->assertTrue($manifest->contents['database']);
        $this->assertCount(0, $manifest->contents['files']);
    }

    // ─── T-03: رمزنگاری/رمزگشایی roundtrip ──────────────────────────────

    public function testEncryptionRoundtripWithDedicatedKey(): void
    {
        $encrypter = new BackupEncrypter('dedicated-key');
        $this->assertTrue($encrypter->usesDedicatedKey());

        $plain = 'payload-محرمانه-' . \str_repeat('X', 1000);
        $cipher = $encrypter->encrypt($plain);

        $this->assertTrue(\str_starts_with($cipher, "TVNBKP1\n"));
        $this->assertStringNotContainsString('محرمانه', $cipher);

        // roundtrip
        $this->assertSame($plain, $encrypter->decrypt($cipher));

        // 🔒 دستکاری ciphertext → GCM auth شکست می‌خورد
        $tampered = $cipher;
        $tampered[\strlen($tampered) - 5] = $tampered[\strlen($tampered) - 5] === 'A' ? 'B' : 'A';
        try {
            $encrypter->decrypt($tampered);
            $this->fail('ciphertext دستکاری‌شده باید رد شود');
        } catch (BackupException $e) {
            $this->assertSame('backup.decrypt_failed', $e->messageId);
        }

        // 🔒 کلید اشتباه → شکست
        $wrong = new BackupEncrypter('wrong-key');
        try {
            $wrong->decrypt($cipher);
            $this->fail('کلید اشتباه باید رد شود');
        } catch (BackupException $e) {
            $this->assertSame('backup.decrypt_failed', $e->messageId);
        }
    }

    // ─── T-04: verify با دستکاری → fail ──────────────────────────────────

    public function testVerifyDetectsTampering(): void
    {
        $manifest = $this->backup()->run();

        $disk = $this->app->make('filesystem')->disk('backuptest');
        $blob = (string) $disk->get($manifest->archiveName());

        // یک بایت دستکاری
        $pos = (int) (\strlen($blob) / 2);
        $blob[$pos] = $blob[$pos] === "\x00" ? "\x01" : "\x00";
        $disk->put($manifest->archiveName(), $blob);

        try {
            $this->backup()->verify($manifest->id);
            $this->fail('آرشیو دستکاری‌شده باید در verify رد شود');
        } catch (BackupException $e) {
            $this->assertSame('backup.verify_failed', $e->messageId);
        }
    }

    // ─── T-05: restore ───────────────────────────────────────────────────

    public function testRestoreRequiresForce(): void
    {
        $manifest = $this->backup()->run();

        try {
            $this->backup()->restore($manifest->id);
            $this->fail('restore بدون force باید رد شود');
        } catch (BackupException $e) {
            $this->assertSame('backup.force_required', $e->messageId);
        }
    }

    public function testRestoreDatabaseAndFiles(): void
    {
        $manifest = $this->backup()->run();

        // خراب‌سازی: جدول drop + فایل حذف
        $this->db()->statement('DROP TABLE backup_items');
        \unlink($this->tmp . '/appfiles/data.txt');
        $this->assertFalse(\file_exists($this->tmp . '/appfiles/data.txt'));

        // بازیابی
        $this->backup()->restore($manifest->id, force: true);

        // داده برگشت
        $rows = $this->db()->table('backup_items')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('اول', $rows[0]['name']);
        $this->assertSame('سطر یک; با semicolon', $rows[0]['note']);
        $this->assertSame("خط۱\nخط۲ با 'quote'", $rows[1]['note']);

        // فایل برگشت
        $this->assertSame('دادهٔ مهم ۱', (string) \file_get_contents($this->tmp . '/appfiles/data.txt'));
        $this->assertSame('deep-content', (string) \file_get_contents($this->tmp . '/appfiles/nested/deep.txt'));

        // maintenance mode پاک شد
        $this->assertFalse(\is_file(TVN_BASE_PATH . '/storage/framework/down'));
    }

    // ─── T-06: retention / prune ─────────────────────────────────────────

    public function testPruneRemovesOldBackups(): void
    {
        $fresh = $this->backup()->run();

        // یک manifest قدیمی ساختگی (۶۰ روز پیش)
        $oldId = 'bkp_' . (string) \Tavaan\Support\Ulid::generate();
        $old = new Manifest(
            id: $oldId,
            type: 'full',
            createdAt: Clock::now()->sub(new \DateInterval('P60D'))->format('Y-m-d\TH:i:s.uP'),
            size: 10,
            hash: 'sha256:x',
        );
        $disk = $this->app->make('filesystem')->disk('backuptest');
        $disk->put($old->archiveName(), 'old-archive');
        $disk->put($old->manifestName(), $old->toJson());

        $this->assertCount(2, $this->backup()->list());

        $deleted = $this->backup()->prune(keepDays: 30);

        $this->assertSame(1, $deleted);
        $remaining = $this->backup()->list();
        $this->assertCount(1, $remaining);
        $this->assertSame($fresh->id, $remaining[0]->id);
        $this->assertFalse($disk->exists($old->archiveName()));
    }

    // ─── T-07: ZIP خالص (بدون ext-zip) ───────────────────────────────────

    public function testPureZipRoundtrip(): void
    {
        $writer = new ZipWriterPure();
        $writer->addFromString('text/hello.txt', 'سلام ZIP');
        $binary = \random_bytes(10_000);
        $writer->addFromString('bin/blob.dat', $binary);
        $writer->addFromString('empty.txt', '');

        $zipBytes = $writer->output();

        // 🔒 ساختار ZIP معتبر (magic + EOCD)
        $this->assertTrue(\str_starts_with($zipBytes, "PK\x03\x04"));
        $this->assertStringContainsString("PK\x05\x06", $zipBytes);

        $reader = ZipReader::openString($zipBytes);
        $this->assertSame(['bin/blob.dat', 'empty.txt', 'text/hello.txt'], $reader->entryNames());

        $this->assertSame('سلام ZIP', $reader->extract('text/hello.txt'));
        $this->assertSame($binary, $reader->extract('bin/blob.dat'));   // CRC هم بررسی می‌شود
        $this->assertSame('', $reader->extract('empty.txt'));
        $this->assertNull($reader->extract('missing.txt'));
        $reader->close();
    }

    public function testBackupArchiveIsReadableZipAfterDecrypt(): void
    {
        $manifest = $this->backup()->run();

        $disk = $this->app->make('filesystem')->disk('backuptest');
        $blob = (string) $disk->get($manifest->archiveName());

        // 🔒 blob خام ZIP نیست (رمزنگاری‌شده)
        $this->assertTrue(\str_starts_with($blob, "TVNBKP1\n"));

        // پس از رمزگشایی → ZIP معتبر با manifest.json داخلی
        $zipBytes = $this->backup()->encrypter()->decrypt($blob);
        $reader = ZipReader::openString($zipBytes);

        $this->assertTrue($reader->has('manifest.json'));
        $this->assertTrue($reader->has('database.sql'));

        $innerRaw = $reader->extract('manifest.json');
        $inner = \json_decode((string) $innerRaw, true);
        $this->assertSame($manifest->id, $inner['id'] ?? '');
        $this->assertArrayHasKey('filemap', $inner);
        $reader->close();
    }

    public function testUnencryptedBackupWhenDisabled(): void
    {
        $this->app->make('config')->set('backup.encrypt', false);
        $manager = $this->app->make(BackupManager::class);

        $manifest = $manager->database();

        $this->assertFalse($manifest->encrypted);

        // blob مستقیماً ZIP است
        $disk = $this->app->make('filesystem')->disk('backuptest');
        $blob = (string) $disk->get($manifest->archiveName());
        $this->assertTrue(\str_starts_with($blob, "PK\x03\x04"));
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
            \is_dir($path) && !\is_link($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }
}
