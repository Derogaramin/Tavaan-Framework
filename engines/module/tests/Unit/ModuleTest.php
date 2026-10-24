<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module\Tests\Unit;

use Tavaan\Engine\Module\Exceptions\CircularModuleDependencyException;
use Tavaan\Engine\Module\Exceptions\ModuleException;
use Tavaan\Engine\Module\Manifest;
use Tavaan\Engine\Module\ManifestParser;
use Tavaan\Engine\Module\Module;
use Tavaan\Engine\Module\ModuleRegistry;
use Tavaan\Engine\Module\TopologicalSorter;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Module — DOC-038 §8 (AC-MOD).
 */
final class ModuleTest extends TestCase
{
    private function module(string $id, array $deps = []): Module
    {
        $manifest = Manifest::fromArray([
            'id' => $id,
            'name' => $id,
            'version' => '1.0.0',
            'autoload' => [],
            'providers' => [],
            'requires' => ['engines' => $deps],
        ], '/tmp/' . $id);

        return new Module($manifest);
    }

    /** T-02: Topological sort با ۵ ماژول (خروجی قطعی) */
    public function testTopologicalSortDeterministic(): void
    {
        $modules = [
            'e' => $this->module('v.e', ['c', 'd']),
            'd' => $this->module('v.d', ['b']),
            'c' => $this->module('v.c', ['a']),
            'b' => $this->module('v.b', ['a']),
            'a' => $this->module('v.a', []),
        ];

        $sorter = new TopologicalSorter();
        $first = \array_map(static fn (Module $m): string => $m->id(), $sorter->sort($modules));
        $second = \array_map(static fn (Module $m): string => $m->id(), $sorter->sort($modules));

        $this->assertSame($first, $second, 'sort must be deterministic');
        $this->assertSame('v.a', $first[0]);
        $this->assertSame('v.e', $first[4]);
        // b قبل از d و c قبل از e
        $this->assertTrue(\array_search('v.b', $first) < \array_search('v.d', $first));
        $this->assertTrue(\array_search('v.c', $first) < \array_search('v.e', $first));
    }

    /** M9: ماژول هم‌نام با موتور (tavaan.media / tavaan.engine.media) → چرخهٔ کاذب نباید بسازد */
    public function testSorterSameShortNameAsEngineNoFalseCycle(): void
    {
        $modules = [
            'tavaan.engine.media' => $this->module('tavaan.engine.media', ['config']),
            'tavaan.engine.config' => $this->module('tavaan.engine.config', ['container']),
            'tavaan.engine.container' => $this->module('tavaan.engine.container', []),
            'tavaan.media' => $this->module('tavaan.media', ['media']),
        ];

        $sorted = (new TopologicalSorter())->sort($modules);
        $ids = \array_map(static fn (Module $m): string => $m->id(), $sorted);

        $this->assertCount(4, $ids);
        // requires.engines: ['media'] باید به موتور resolve شود نه ماژول هم‌نام
        $this->assertTrue(\array_search('tavaan.engine.media', $ids) < \array_search('tavaan.media', $ids));
        $this->assertTrue(\array_search('tavaan.engine.container', $ids) < \array_search('tavaan.engine.config', $ids));
    }

    /** M9: وابستگی ماژول→ماژول (requires.modules با شناسهٔ کامل) در ترتیب boot لحاظ شود */
    public function testSorterModuleToModuleDependency(): void
    {
        $blog = Manifest::fromArray([
            'id' => 'tavaan.blog',
            'name' => 'Blog',
            'version' => '1.0.0',
            'autoload' => [],
            'providers' => [],
            'requires' => ['engines' => ['view'], 'modules' => ['tavaan.content', 'tavaan.comment']],
        ], '/tmp/tavaan.blog');

        $modules = [
            'tavaan.blog' => new Module($blog),
            'tavaan.content' => $this->module('tavaan.content', []),
            'tavaan.comment' => $this->module('tavaan.comment', []),
            'tavaan.engine.view' => $this->module('tavaan.engine.view', []),
        ];

        $ids = \array_map(static fn (Module $m): string => $m->id(), (new TopologicalSorter())->sort($modules));

        $this->assertTrue(\array_search('tavaan.content', $ids) < \array_search('tavaan.blog', $ids));
        $this->assertTrue(\array_search('tavaan.comment', $ids) < \array_search('tavaan.blog', $ids));
        $this->assertTrue(\array_search('tavaan.engine.view', $ids) < \array_search('tavaan.blog', $ids));
    }

    /** M9: Registry پروژهٔ واقعی — موتور media و ماژول tavaan.media هم‌زمان در sorted() حاضرند */
    public function testRegistrySortedKeepsEngineAndModuleWithSameShortName(): void
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 5);
        $modulesDir = $base . '/modules';
        if (!\is_dir($modulesDir . '/media')) {
            $this->assertTrue(true);   // ماژول media نصب نیست — skip
            return;
        }

        $registry = new ModuleRegistry([$base . '/engines', $modulesDir]);
        $registry->discover();

        $ids = \array_map(static fn (Module $m): string => $m->id(), $registry->sorted());

        $this->assertTrue(\in_array('tavaan.engine.media', $ids, true));
        $this->assertTrue(\in_array('tavaan.media', $ids, true));
        $this->assertTrue(\array_search('tavaan.engine.media', $ids) < \array_search('tavaan.media', $ids));
    }

    /** T-03: چرخه → exception با فهرست ماژول‌ها */
    public function testCircularDependencyThrows(): void
    {
        $modules = [
            'x' => $this->module('v.x', ['y']),
            'y' => $this->module('v.y', ['z']),
            'z' => $this->module('v.z', ['x']),
            'free' => $this->module('v.free', []),
        ];

        try {
            (new TopologicalSorter())->sort($modules);
            self::fail('expected CircularModuleDependencyException');
        } catch (CircularModuleDependencyException $e) {
            $this->assertSame('module.circular_dependency', $e->messageId);
            $this->assertCount(3, $e->context['modules']);
        }
    }

    /** T-01: parse manifest معتبر (فایل واقعی موتور container) */
    public function testParseRealManifest(): void
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 5);
        $manifest = (new ManifestParser())->parse($base . '/engines/config/module.json');

        $this->assertSame('tavaan.engine.config', $manifest->id);
        $this->assertSame('engine', $manifest->type);
        $this->assertContains('container', $manifest->engineDependencies());
    }

    public function testParseInvalidJsonThrows(): void
    {
        $file = \sys_get_temp_dir() . '/tvn-bad-manifest-' . \getmypid() . '.json';
        \file_put_contents($file, '{not json!!');
        try {
            (new ManifestParser())->parse($file);
            self::fail('expected exception');
        } catch (ModuleException $e) {
            $this->assertSame('module.manifest_invalid_json', $e->messageId);
        } finally {
            @\unlink($file);
        }
    }

    public function testParseMissingFieldThrows(): void
    {
        $file = \sys_get_temp_dir() . '/tvn-missing-manifest-' . \getmypid() . '.json';
        \file_put_contents($file, \json_encode(['name' => 'x']));
        try {
            (new ManifestParser())->parse($file);
            self::fail('expected exception');
        } catch (ModuleException $e) {
            $this->assertSame('module.manifest_missing_field', $e->messageId);
        } finally {
            @\unlink($file);
        }
    }

    public function testInvalidIdAndVersionRejected(): void
    {
        $parser = new ManifestParser();
        $file = \sys_get_temp_dir() . '/tvn-id-manifest-' . \getmypid() . '.json';

        \file_put_contents($file, \json_encode(['id' => 'BADID', 'name' => 'x', 'version' => '1.0.0', 'autoload' => [], 'providers' => []]));
        try {
            $parser->parse($file);
            self::fail('expected invalid id');
        } catch (ModuleException $e) {
            $this->assertSame('module.invalid_id', $e->messageId);
        }

        \file_put_contents($file, \json_encode(['id' => 'v.x', 'name' => 'x', 'version' => 'not-semver', 'autoload' => [], 'providers' => []]));
        try {
            $parser->parse($file);
            self::fail('expected invalid version');
        } catch (ModuleException $e) {
            $this->assertSame('module.invalid_version', $e->messageId);
        } finally {
            @\unlink($file);
        }
    }

    /** Registry روی موتورهای واقعی پروژه */
    public function testRegistryDiscoversProjectEngines(): void
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 5);
        $registry = new ModuleRegistry([$base . '/engines']);
        $registry->discover();

        $all = $registry->all();
        $this->assertGreaterThan(9, \count($all));
        $this->assertTrue($registry->has('tavaan.engine.container'));
        $this->assertSame('0.1.0', $registry->get('tavaan.engine.container')->version());

        $sorted = $registry->sorted();
        $ids = \array_map(static fn (Module $m): string => $m->id(), $sorted);
        // config بعد از container (وابستگی)
        $this->assertTrue(\array_search('tavaan.engine.container', $ids) < \array_search('tavaan.engine.config', $ids));
        // event بعد از config
        $this->assertTrue(\array_search('tavaan.engine.config', $ids) < \array_search('tavaan.engine.event', $ids));
    }

    /** disable با dependent فعال → رد */
    public function testDisableBlockedByDependents(): void
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 5);
        $registry = new ModuleRegistry([$base . '/engines']);
        $manager = new \Tavaan\Engine\Module\ModuleManager($registry, \sys_get_temp_dir() . '/tvn-status-test.php');

        $this->expectException(ModuleException::class);
        $manager->disable('tavaan.engine.container');
    }
}
