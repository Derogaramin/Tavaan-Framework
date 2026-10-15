<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Tests\Unit;

use Tavaan\Engine\Config\ConfigCacher;
use Tavaan\Engine\Config\ConfigLoader;
use Tavaan\Engine\Config\Exceptions\ConfigFrozenException;
use Tavaan\Engine\Config\Exceptions\ConfigNotCacheableException;
use Tavaan\Engine\Config\Exceptions\MissingConfigKeyException;
use Tavaan\Engine\Config\Repository;
use Tavaan\Support\Arr;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Config — سناریوهای DOC-032 §12 (AC-CFG-01).
 */
final class RepositoryTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-config-test-' . \getmypid() . '-' . \bin2hex(\random_bytes(3));
        \mkdir($this->tmp . '/config', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    /** T-01: Dot-get در عمق ۱/۲/۵ */
    public function testDotGetAtVariousDepths(): void
    {
        $r = new Repository(['app' => ['name' => 'Tavaan', 'db' => ['main' => ['host' => ['primary' => ['ip' => '127.0.0.1']]]]]]);
        $this->assertSame('Tavaan', $r->get('app.name'));
        $this->assertSame('127.0.0.1', $r->get('app.db.main.host.primary.ip'));
        $this->assertNull($r->get('app.missing'));
        $this->assertSame('def', $r->get('app.missing', 'def'));
        $this->assertTrue($r->has('app.name'));
        $this->assertTrue($r->missing('app.nope'));
    }

    /** T-02: set روی مسیر ناموجود → ساخت خودکار */
    public function testSetCreatesMissingPath(): void
    {
        $r = new Repository();
        $r->set('a.b.c', 42);
        $this->assertSame(42, $r->get('a.b.c'));
        $r->set(['x.y' => 1, 'x.z' => 2]);
        $this->assertSame(1, $r->get('x.y'));
        $this->assertSame(2, $r->get('x.z'));
    }

    /** forget/pull/push/prepend */
    public function testForgetPullPushPrepend(): void
    {
        $r = new Repository(['list' => ['b']]);
        $r->push('list', 'c');
        $r->prepend('list', 'a');
        $this->assertSame(['a', 'b', 'c'], $r->get('list'));
        $this->assertSame('a', $r->pull('list.0'));
        $this->assertFalse($r->has('list.0'));
        $r->forget('list');
        $this->assertNull($r->get('list'));
    }

    /** only/except/namespace */
    public function testOnlyExceptNamespace(): void
    {
        $r = new Repository(['app' => ['name' => 'X', 'env' => 'local'], 'db' => ['x' => 1]]);
        $this->assertSame(['app' => ['name' => 'X']], $r->only(['app.name']));
        $this->assertArrayNotHasKey('db', $r->except(['db']));
        $this->assertSame(['name' => 'X', 'env' => 'local'], $r->namespace('app'));
    }

    /** T-03: Merge — list جایگزینی، map ادغام */
    public function testMergeListReplacesMapMerges(): void
    {
        $base = ['mw' => ['a', 'b'], 'nested' => ['x' => 1, 'y' => 2]];
        $override = ['mw' => ['c'], 'nested' => ['y' => 20, 'z' => 30]];
        $merged = Arr::mergeDeep($base, $override);

        $this->assertSame(['c'], $merged['mw'], 'list must be replaced');
        $this->assertSame(['x' => 1, 'y' => 20, 'z' => 30], $merged['nested'], 'map must be merged');
    }

    /** T-06: Config Cache roundtrip یکسان */
    public function testCacheRoundtrip(): void
    {
        $r = new Repository(['app' => ['name' => 'توان', 'list' => [1, 2, 3]], 'db' => ['x' => true]]);
        $cacher = new ConfigCacher($r);
        $file = $this->tmp . '/config-cache.php';
        $cacher->cache($file);

        $loaded = $cacher->load($file);
        $this->assertSame($r->all(), $loaded);
        $this->assertTrue($cacher->isCached($file));
        $cacher->clear($file);
        $this->assertFalse($cacher->isCached($file));
    }

    /** T-07: Closure در config → ConfigNotCacheableException */
    public function testClosureInConfigNotCacheable(): void
    {
        $r = new Repository(['bad' => ['fn' => static fn (): int => 1]]);
        $cacher = new ConfigCacher($r);
        $this->expectException(ConfigNotCacheableException::class);
        $cacher->cache($this->tmp . '/bad.php');
    }

    /** T-08: Frozen + set → خطا */
    public function testFrozenRejectsSet(): void
    {
        $r = new Repository(['app' => ['name' => 'X']]);
        $r->freeze();
        $this->assertTrue($r->isFrozen());
        $this->expectException(ConfigFrozenException::class);
        $r->set('app.name', 'Y');
    }

    /** T-10: Masking در toJson */
    public function testToJsonMasksSecrets(): void
    {
        $r = new Repository(['db' => ['mysql' => ['password' => 's3cret!', 'host' => '127.0.0.1']], 'app' => ['key' => 'base64:abc']]);
        $json = $r->toJson();
        $this->assertStringNotContainsString('s3cret', $json);
        $this->assertStringContainsString('********', $json);
        $this->assertStringContainsString('127.0.0.1', $json);
        $this->assertStringNotContainsString('base64:abc', $r->toJson());
        $this->assertStringContainsString('base64:abc', $r->toJson(false));
    }

    /** requiredKeys → MissingConfigKeyException */
    public function testRequiredKeysThrows(): void
    {
        $r = new Repository(['app' => ['name' => 'X']]);
        $this->expectException(MissingConfigKeyException::class);
        $r->requiredKeys(['app.name', 'app.key']);
    }

    /** ConfigLoader با اولویت engine < app */
    public function testLoaderPriorityAppOverEngines(): void
    {
        // engines/x/config/sample.php (پایه)
        \mkdir($this->tmp . '/engines/x/config', 0775, true);
        \file_put_contents($this->tmp . '/engines/x/config/sample.php', "<?php return ['a' => 1, 'b' => ['c' => 2, 'd' => 3]];");
        // app config (override)
        \file_put_contents($this->tmp . '/config/sample.php', "<?php return ['a' => 100, 'b' => ['c' => 200]];");

        $items = (new ConfigLoader($this->tmp))->load();
        $this->assertSame(100, $items['sample']['a']);
        $this->assertSame(200, $items['sample']['b']['c']);
        $this->assertSame(3, $items['sample']['b']['d'], 'map merge keeps engine default');
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir . '/' . $f;
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }
}
