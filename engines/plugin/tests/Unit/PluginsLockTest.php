<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Tests\Unit;

use Tavaan\Engine\Plugin\PluginsLock;
use Tavaan\Testing\TestCase;

/**
 * تست PluginsLock — ثبت/خواندن/hash اتمیک (DOC-079 §4 — T-02).
 */
final class PluginsLockTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-lock-test-' . \getmypid();
        if (!\is_dir($this->tmp)) {
            \mkdir($this->tmp, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    public function testEntryLifecycle(): void
    {
        $lock = new PluginsLock($this->tmp . '/plugins.lock');

        self::assertSame([], $lock->read());
        self::assertFalse($lock->has('acme.demo'));

        $lock->put('acme.demo', ['version' => '1.0.0', 'enabled' => true, 'files' => ['a.php' => 'h1']]);
        self::assertTrue($lock->has('acme.demo'));
        self::assertSame('1.0.0', $lock->entry('acme.demo')['version']);

        $lock->setEnabled('acme.demo', false);
        self::assertFalse((bool) $lock->entry('acme.demo')['enabled']);

        $lock->remove('acme.demo');
        self::assertFalse($lock->has('acme.demo'));
        self::assertSame([], $lock->read());
    }

    public function testPersistedAsValidJson_SortedById(): void
    {
        $file = $this->tmp . '/plugins.lock';
        $lock = new PluginsLock($file);
        $lock->put('z.plugin', ['version' => '1.0.0']);
        $lock->put('a.plugin', ['version' => '2.0.0']);

        $decoded = \json_decode((string) \file_get_contents($file), true);
        self::assertNotNull($decoded);
        self::assertSame(['a.plugin', 'z.plugin'], \array_keys((array) $decoded));
    }

    public function testCorruptLockFile_ReadsAsEmpty(): void
    {
        $file = $this->tmp . '/plugins.lock';
        \file_put_contents($file, 'not json at all');

        self::assertSame([], (new PluginsLock($file))->read());
    }

    public function testHashDirectory_DeterministicSortedAndBakFree(): void
    {
        $dir = $this->tmp . '/plug';
        \mkdir($dir . '/src', 0775, true);
        \file_put_contents($dir . '/plugin.json', '{"id":"a.b"}');
        \file_put_contents($dir . '/src/B.php', "<?php\n");
        \file_put_contents($dir . '/src/A.php', "<?php\n");
        \file_put_contents($dir . '/src/A.php.bak', 'backup');

        $first = PluginsLock::hashDirectory($dir);
        $second = PluginsLock::hashDirectory($dir);

        self::assertSame($first, $second);   // قطعی
        self::assertSame(['plugin.json', 'src/A.php', 'src/B.php'], \array_keys($first));
        self::assertSame(\hash('sha256', '{"id":"a.b"}'), $first['plugin.json']);

        // دستکاری → hash عوض می‌شود
        \file_put_contents($dir . '/src/A.php', "<?php\n// tampered\n");
        $third = PluginsLock::hashDirectory($dir);
        self::assertTrue($third['src/A.php'] !== $first['src/A.php']);
    }

    public function testHashDirectory_MissingDir_Empty(): void
    {
        self::assertSame([], PluginsLock::hashDirectory($this->tmp . '/nonexistent'));
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($dir);
    }
}
