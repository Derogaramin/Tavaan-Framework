<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Unit;

use Tavaan\Testing\TestCase;
use Tavaan\Testing\TestSuite;

/**
 * تست کشف/گروه‌بندی/فیلتر/chunk در TestSuite (DOC-084E §7).
 *
 * Fixtureها در زمان اجرا داخل sys_get_temp_dir ساخته می‌شوند تا خودِ
 * Suite اصلی آن‌ها را کشف/اجرا نکند (فایل‌های *Test.php واقعی هستند).
 */
final class TestSuiteTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-suite-fixture-' . \getmypid();
        $this->clean($this->tmp);

        $this->put('engines/demo-engine/tests/Unit/AlphaTest.php', <<<'PHP'
        <?php
        namespace TvnSuiteFixture\Demo\Unit;

        use Tavaan\Testing\Group;
        use Tavaan\Testing\TestCase;

        #[Group('fast')]
        final class AlphaTest extends TestCase
        {
            public function testOne(): void { self::assertTrue(true); }
            public function testTwo(): void { self::assertTrue(true); }
            public function notATest(): void { self::fail('should not run'); }
        }
        PHP);

        $this->put('engines/demo-engine/tests/Feature/BetaTest.php', <<<'PHP'
        <?php
        namespace TvnSuiteFixture\Demo\Feature;

        use Tavaan\Testing\Group;
        use Tavaan\Testing\TestCase;

        final class BetaTest extends TestCase
        {
            #[Group('slow')]
            public function testBeta(): void { self::assertTrue(true); }

            public function testGamma(): void { self::assertTrue(true); }
        }
        PHP);

        $this->put('tests/Unit/DeltaTest.php', <<<'PHP'
        <?php
        namespace TvnSuiteFixture\App;

        use Tavaan\Testing\TestCase;

        final class DeltaTest extends TestCase
        {
            public function testDelta(): void { self::assertTrue(true); }
        }

        abstract class SkippedAbstractTest extends TestCase
        {
            public function testNever(): void { self::fail('abstract should be skipped'); }
        }
        PHP);

        // فایل غیرتست → باید نادیده گرفته شود
        $this->put('engines/demo-engine/tests/Unit/HelperNotATestFile.php', '<?php namespace TvnSuiteFixture; final class HelperNotATestFile {}');
    }

    protected function tearDown(): void
    {
        $this->clean($this->tmp);
    }

    // ─── کشف ──────────────────────────────────────────────────────────────

    public function testDiscover_FindsConcreteTestClassesAndMethods(): void
    {
        $suite = TestSuite::discover([$this->tmp]);

        self::assertSame(3, $suite->countClasses());
        self::assertSame(5, $suite->countMethods());   // 2 + 2 + 1 (notATest و abstract حذف می‌شوند)

        $names = \array_map(
            static fn ($c): string => $c->shortName(),
            $suite->classes(),
        );
        \sort($names);
        self::assertSame(['AlphaTest', 'BetaTest', 'DeltaTest'], $names);
    }

    public function testDiscover_IsIdempotentInSameProcess(): void
    {
        $first = TestSuite::discover([$this->tmp]);
        $second = TestSuite::discover([$this->tmp]);

        self::assertSame($first->countMethods(), $second->countMethods());
        self::assertSame(3, $second->countClasses());
    }

    public function testDiscoverFiles_SortedUniqueOnlyTestPhp(): void
    {
        $files = TestSuite::discoverFiles([$this->tmp, $this->tmp . '/tests/Unit/DeltaTest.php']);

        self::assertCount(3, $files);
        $sorted = $files;
        \sort($sorted);
        self::assertSame($sorted, $files);
        foreach ($files as $file) {
            self::assertTrue(\str_ends_with($file, 'Test.php'));
        }
    }

    // ─── گروه‌ها ──────────────────────────────────────────────────────────

    public function testImplicitGroups_FromEnginePathAndLayer(): void
    {
        $suite = TestSuite::discover([$this->tmp]);
        $alpha = $this->findClass($suite, 'AlphaTest');

        self::assertContains('demo-engine', $alpha->methods[0]->groups);
        self::assertContains('unit', $alpha->methods[0]->groups);
        self::assertContains('fast', $alpha->methods[0]->groups);   // #[Group] کلاس

        $beta = $this->findClass($suite, 'BetaTest');
        $betaMethod = $beta->methods[0];   // testBeta — #[Group('slow')] متدی
        self::assertContains('demo-engine', $betaMethod->groups);
        self::assertContains('feature', $betaMethod->groups);
        self::assertContains('slow', $betaMethod->groups);

        $delta = $this->findClass($suite, 'DeltaTest');
        self::assertContains('app', $delta->methods[0]->groups);
        self::assertContains('unit', $delta->methods[0]->groups);
    }

    public function testFilterByGroup_NarrowsMethods(): void
    {
        $suite = TestSuite::discover([$this->tmp]);

        $fast = $suite->filter(null, 'fast');
        self::assertSame(1, $fast->countClasses());
        self::assertSame(2, $fast->countMethods());

        $slow = $suite->filter(null, 'slow');
        self::assertSame(1, $slow->countClasses());
        self::assertSame(1, $slow->countMethods());   // فقط testBeta

        $engine = $suite->filter(null, 'demo-engine');
        self::assertSame(2, $engine->countClasses());
        self::assertSame(4, $engine->countMethods());

        self::assertSame(0, $suite->filter(null, 'nonexistent-group')->countClasses());
    }

    // ─── فیلتر نام ────────────────────────────────────────────────────────

    public function testFilterByName_MatchesClassOrMethod(): void
    {
        $suite = TestSuite::discover([$this->tmp]);

        self::assertSame(1, $suite->filter('testOne')->countMethods());
        self::assertSame(2, $suite->filter('BetaTest')->countMethods());
        self::assertSame('AlphaTest', $suite->filter('alpha')->classes()[0]->shortName());
        self::assertSame(0, $suite->filter('no-such-test')->countClasses());
    }

    public function testFilterCombined_NameAndGroup(): void
    {
        $suite = TestSuite::discover([$this->tmp]);
        $filtered = $suite->filter('Beta', 'slow');

        self::assertSame(1, $filtered->countMethods());
        self::assertSame('testBeta', $filtered->classes()[0]->methods[0]->name);
    }

    // ─── chunk (اجرای موازی) ──────────────────────────────────────────────

    public function testChunkFiles_RoundRobinDeterministic(): void
    {
        $suite = TestSuite::discover([$this->tmp]);
        $files = $suite->files();
        \sort($files);

        $chunks = $suite->chunkFiles(2);
        self::assertCount(2, $chunks);
        self::assertSame([$files[0], $files[2]], $chunks[0]);
        self::assertSame([$files[1]], $chunks[1]);

        // قطعی: دو بار فراخوانی → خروجی یکسان
        self::assertSame($chunks, $suite->chunkFiles(2));

        // دسته‌های خالی حذف می‌شوند
        self::assertCount(3, $suite->chunkFiles(10));

        $flat = [];
        foreach ($chunks as $chunk) {
            foreach ($chunk as $file) {
                $flat[] = $file;
            }
        }
        \sort($flat);
        self::assertSame($files, $flat);   // پوشش کامل بدون تکرار
    }

    public function testFromFiles_BuildsSuiteFromExplicitList(): void
    {
        $files = TestSuite::discoverFiles([$this->tmp]);
        $suite = TestSuite::fromFiles([$files[0]]);

        self::assertSame(1, $suite->countClasses());
        self::assertSame($files, TestSuite::fromFiles($files)->files());
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    private function findClass(TestSuite $suite, string $shortName): \Tavaan\Testing\TestClassDescriptor
    {
        foreach ($suite->classes() as $class) {
            if ($class->shortName() === $shortName) {
                return $class;
            }
        }

        self::fail('Class [' . $shortName . '] not found in suite.');
    }

    private function put(string $relative, string $contents): void
    {
        $path = $this->tmp . '/' . $relative;
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }
        \file_put_contents($path, $this->dedent($contents));
    }

    /** حذف تورفتگی مشترک heredoc (تمیز نگه‌داشتن fixtureها). */
    private function dedent(string $text): string
    {
        $lines = \explode("\n", $text);
        $out = [];
        foreach ($lines as $line) {
            $out[] = \preg_replace('/^ {8}/', '', $line) ?? $line;
        }

        return \implode("\n", $out);
    }

    private function clean(string $dir): void
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
