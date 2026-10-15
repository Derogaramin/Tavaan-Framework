<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tavaan\Core\Autoloading\AutoloadException;
use Tavaan\Core\Autoloading\Autoloader;
use Tavaan\Core\Autoloading\AutoloaderRegistry;
use Tavaan\Testing\TestCase;

/**
 * تست Autoloader — TVN-0001 / DOC-027 (AC-BOOT-02..04).
 */
final class AutoloaderTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 2);
    }

    /** AC-BOOT-02: کلاس‌ها از prefix دقیق و wildcard پیدا می‌شوند (temp tree) */
    public function testResolvesCoreAndEngineClasses(): void
    {
        $tmp = \sys_get_temp_dir() . '/tvn-autoload-tree-' . \getmypid();
        @\mkdir($tmp . '/src/Foo', 0775, true);
        @\mkdir($tmp . '/engines/demo/src', 0775, true);
        \file_put_contents($tmp . '/src/Foo/Bar.php', "<?php\nnamespace Vendor\Test\Foo;\nfinal class Bar { public static function hi(): string { return 'hi'; } }\n");
        \file_put_contents($tmp . '/engines/demo/src/WildThing.php', "<?php\nnamespace Tavaan\Engine\Demo;\nfinal class WildThing { public static function hi(): string { return 'wild'; } }\n");

        $autoloader = Autoloader::create($tmp);
        $autoloader->addNamespace('Vendor\\Test\\Foo\\', 'src/Foo/');
        $autoloader->addNamespace('Tavaan\\Engine\\', 'engines/*/src/');

        $this->assertTrue($autoloader->loadClass('Vendor\Test\Foo\Bar'));
        $this->assertTrue(\class_exists('Vendor\Test\Foo\Bar', false));
        $this->assertSame('hi', \Vendor\Test\Foo\Bar::hi());

        // wildcard: Demo → demo (kebab)
        $this->assertTrue($autoloader->loadClass('Tavaan\Engine\Demo\WildThing'));
        $this->assertSame('wild', \Tavaan\Engine\Demo\WildThing::hi());

        $stats = $autoloader->stats();
        $this->assertGreaterThan(0, $stats->prefixHits);
        $this->assertGreaterThan(0, $stats->wildcardHits);

        // پاکسازی
        @\unlink($tmp . '/src/Foo/Bar.php');
        @\unlink($tmp . '/engines/demo/src/WildThing.php');
        @\rmdir($tmp . '/src/Foo');
        @\rmdir($tmp . '/src');
        @\rmdir($tmp . '/engines/demo/src');
        @\rmdir($tmp . '/engines/demo');
        @\rmdir($tmp . '/engines');
        @\rmdir($tmp);
    }

    /** AC-BOOT-03: نام ناامن → AutoloadException */
    public function testUnsafeClassNameThrows(): void
    {
        $autoloader = Autoloader::create($this->base);
        $this->expectException(AutoloadException::class);
        $autoloader->loadClass('..\\..\\evil');
    }

    public function testNullByteClassNameThrows(): void
    {
        $autoloader = Autoloader::create($this->base);
        $this->expectException(AutoloadException::class);
        $autoloader->loadClass("Evil\0Class");
    }

    public function testTooLongClassNameThrows(): void
    {
        $autoloader = Autoloader::create($this->base);
        $this->expectException(AutoloadException::class);
        $autoloader->loadClass(\str_repeat('A', 256));
    }

    /** locked prefix → override نادیده گرفته می‌شود */
    public function testLockedPrefixCannotBeOverridden(): void
    {
        $autoloader = Autoloader::create($this->base);
        $autoloader->addNamespace('Tavaan\\Core\\', 'core/');
        $autoloader->lockPrefix('Tavaan\\Core\\');
        $autoloader->addNamespace('Tavaan\\Core\\', 'evil/');

        $prefixes = $autoloader->prefixes();
        $this->assertSame(['core'], \array_map(static fn (string $p): string => \basename($p), $prefixes['Tavaan\\Core\\']));
    }

    /** longest-prefix-first */
    public function testLongestPrefixWins(): void
    {
        $autoloader = Autoloader::create($this->base);
        $autoloader->addNamespace('Tavaan\\', 'wrong/');
        $autoloader->addNamespace('Tavaan\\Support\\', 'core/Support/');

        $prefixes = \array_keys($autoloader->prefixes());
        $this->assertSame('Tavaan\\Support\\', $prefixes[0], 'longer prefix must be sorted first');
    }

    /** AC-BOOT-04: dumpClassMap + writePhpArray roundtrip */
    public function testClassMapDumpRoundtrip(): void
    {
        $autoloader = Autoloader::create($this->base);
        $autoloader->addNamespace('Tavaan\\Engine\\', 'engines/*/src/');

        $file = \sys_get_temp_dir() . '/tvn-classmap-' . \getmypid() . '.php';
        $map = $autoloader->dumpClassMap($file, ['tests/*']);

        $this->assertArrayHasKey('Tavaan\\Engine\\Compat\\Utf8', $map);
        $this->assertArrayNotHasKey('Tavaan\\Engine\\Compat\\Tests\\Anything', $map);

        /** @var array<string, string> $loaded */
        $loaded = require $file;
        $this->assertSame($map, $loaded);

        // classmap باید کلاس را resolve کند
        $autoloader2 = Autoloader::create($this->base);
        $autoloader2->addClassMap($loaded);
        $this->assertTrue($autoloader2->loadClass('Tavaan\\Engine\\Compat\\ZipWriterPure') || \class_exists('Tavaan\\Engine\\Compat\\ZipWriterPure', false));

        @\unlink($file);
    }

    /** Registry */
    public function testRegistryHoldsBootstrapAutoloader(): void
    {
        $this->assertTrue(AutoloaderRegistry::has());
        $this->assertInstanceOf(Autoloader::class, AutoloaderRegistry::get());
        $this->assertSame($this->base, AutoloaderRegistry::get()->basePath());
    }
}
