<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tavaan\Testing\TestCase;

/**
 * تست CLI — اجرای واقعی bin/tvn (AC-CLI-01/02).
 */
final class CliTest extends TestCase
{
    /**
     * @return array{exit: int, out: string}
     */
    private function tvn(string $args): array
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 2);
        $cmd = \PHP_BINARY . ' ' . \escapeshellarg($base . '/bin/tvn') . ' ' . $args . ' 2>&1';

        $output = [];
        $exit = 0;
        \exec($cmd, $output, $exit);

        return ['exit' => $exit, 'out' => \implode("\n", $output)];
    }

    public function testVersionCommand(): void
    {
        $result = $this->tvn('version');
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('Tavaan', $result['out']);
        $this->assertStringContainsString(PHP_VERSION, $result['out']);
    }

    /** AC-CLI-01: بدون آرگومان → فهرست دستورات */
    public function testNoArgumentsShowsList(): void
    {
        $result = $this->tvn('');
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('about', $result['out']);
        $this->assertStringContainsString('optimize', $result['out']);
    }

    /** AC-CLI-02: about --json */
    public function testAboutJson(): void
    {
        $result = $this->tvn('about --json');
        $this->assertSame(0, $result['exit']);
        $data = \json_decode($result['out'], true);
        $this->assertNotNull($data, 'output must be valid JSON: ' . \substr($result['out'], 0, 200));
        $this->assertSame(PHP_VERSION, $data['php']);
        $this->assertArrayHasKey('engines', $data);
        // AC-CFG-04: هیچ secret خامی در about --json
        $this->assertStringNotContainsString('TVN_APP_KEY=', $result['out']);
    }

    public function testUnknownCommandExitsNonZeroWithSuggestion(): void
    {
        $result = $this->tvn('versoin');   // typo
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('version', $result['out']);
    }

    public function testModuleListCommand(): void
    {
        $result = $this->tvn('module:list');
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('tavaan.engine.container', $result['out']);
    }

    public function testDoctorCommand(): void
    {
        $result = $this->tvn('doctor');
        $this->assertStringContainsString('doctor', $result['out']);
        $this->assertStringContainsString('mbstring', $result['out']);
    }

    public function testOptimizeRoundtrip(): void
    {
        // 🔒 تغییرات زیرفرایند برای statcache والد نامرئی است → clearstatcache
        $cache = $this->tvn('config:cache');
        $this->assertSame(0, $cache['exit'], $cache['out']);
        \clearstatcache();
        $this->assertTrue(\is_file(TVN_BASE_PATH . '/storage/cache/config.php'));

        $clear = $this->tvn('config:clear');
        $this->assertSame(0, $clear['exit']);
        \clearstatcache();
        $this->assertFalse(\is_file(TVN_BASE_PATH . '/storage/cache/config.php'));

        $autoload = $this->tvn('autoload:cache');
        $this->assertSame(0, $autoload['exit'], $autoload['out']);
        \clearstatcache();
        $this->assertTrue(\is_file(TVN_BASE_PATH . '/storage/cache/classmap.php'));

        $clean = $this->tvn('autoload:cache --clear');
        $this->assertSame(0, $clean['exit']);
        \clearstatcache();
        $this->assertFalse(\is_file(TVN_BASE_PATH . '/storage/cache/classmap.php'));
    }
}
