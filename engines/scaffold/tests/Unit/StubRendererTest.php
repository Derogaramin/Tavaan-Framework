<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Tests\Unit;

use Tavaan\Engine\Scaffold\ScaffoldException;
use Tavaan\Engine\Scaffold\StubRenderer;
use Tavaan\Testing\TestCase;

/**
 * تست StubRenderer خودنوشته — var/if/unless/each (DOC-077 §9 T-01).
 */
final class StubRendererTest extends TestCase
{
    private StubRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new StubRenderer(\sys_get_temp_dir() . '/tvn-stubs-nonexistent');
    }

    // ─── {{var}} ──────────────────────────────────────────────────────────

    public function testVarSubstitution(): void
    {
        $out = $this->renderer->renderString('namespace {{ ns }}; class {{class}} {}', [
            'ns' => 'App\\Models',
            'class' => 'Product',
        ]);

        self::assertSame('namespace App\\Models; class Product {}', $out);
    }

    public function testUnknownVar_LeftIntact(): void
    {
        $out = $this->renderer->renderString('{{known}} {{unknown}}', ['known' => 'a']);

        self::assertSame('a {{unknown}}', $out);
    }

    public function testScalarAndBoolStringify(): void
    {
        $out = $this->renderer->renderString('{{i}}|{{f}}|{{t}}|{{n}}', [
            'i' => 42, 'f' => 1.5, 't' => true, 'n' => null,
        ]);

        self::assertSame('42|1.5|true|', $out);
    }

    public function testArrayVar_BecomesPhpLiteral(): void
    {
        $out = $this->renderer->renderString('$fillable = {{fillable}};', [
            'fillable' => ['name', 'price'],
        ]);

        self::assertSame("\$fillable = ['name', 'price'];", $out);
        self::assertSame('[]', $this->renderer->renderString('{{e}}', ['e' => []]));
    }

    // ─── {{#if}} / {{#unless}} ────────────────────────────────────────────

    public function testIfBlock_TrueKeepsBodyWithoutStrayBlankLines(): void
    {
        $out = $this->renderer->renderString("A\n{{#if flag}}\nB\nC\n{{/if}}\nD\n", ['flag' => true]);

        self::assertSame("A\nB\nC\nD\n", $out);
    }

    public function testIfBlock_FalseRemovesWholeLines(): void
    {
        $out = $this->renderer->renderString("A\n{{#if flag}}\nB\n{{/if}}\nD\n", ['flag' => false]);

        self::assertSame("A\nD\n", $out);
    }

    public function testUnlessBlock_IsNegatedIf(): void
    {
        $tpl = "A\n{{#unless api}}\nWEB\n{{/unless}}\n{{#if api}}\nJSON\n{{/if}}\n";

        self::assertSame("A\nWEB\n", $this->renderer->renderString($tpl, ['api' => false]));
        self::assertSame("A\nJSON\n", $this->renderer->renderString($tpl, ['api' => true]));
    }

    public function testTruthySemantics(): void
    {
        $tpl = "{{#if v}}\nyes\n{{/if}}";

        self::assertSame('', $this->renderer->renderString($tpl, ['v' => '']));
        self::assertSame('', $this->renderer->renderString($tpl, ['v' => '0']));
        self::assertSame('', $this->renderer->renderString($tpl, ['v' => 'false']));
        self::assertSame('', $this->renderer->renderString($tpl, ['v' => []]));
        self::assertSame('', $this->renderer->renderString($tpl, ['v' => null]));
        self::assertSame("yes\n", $this->renderer->renderString($tpl, ['v' => 'x']));
        self::assertSame("yes\n", $this->renderer->renderString($tpl, ['v' => ['a']]));
    }

    // ─── {{#each}} ────────────────────────────────────────────────────────

    public function testEach_ScalarItems(): void
    {
        $out = $this->renderer->renderString("{{#each items}}\n- {{this}}\n{{/each}}", [
            'items' => ['a', 'b'],
        ]);

        self::assertSame("- a\n- b\n", $out);
    }

    public function testEach_AssocItems_ExposesKeysAndIndex(): void
    {
        $out = $this->renderer->renderString("{{#each columns}}\n\$t->{{type}}('{{name}}'); // {{index}}\n{{/each}}", [
            'columns' => [
                ['name' => 'total', 'type' => 'bigInteger'],
                ['name' => 'paid', 'type' => 'boolean'],
            ],
        ]);

        self::assertSame("\$t->bigInteger('total'); // 0\n\$t->boolean('paid'); // 1\n", $out);
    }

    public function testEach_EmptyList_RendersNothing(): void
    {
        $out = $this->renderer->renderString("A\n{{#each items}}\nX\n{{/each}}\nB\n", ['items' => []]);

        self::assertSame("A\nB\n", $out);
    }

    public function testNestedIfInsideEach(): void
    {
        $tpl = "{{#each rows}}\n{{#if mark}}\n[{{this}}*]\n{{/if}}\n{{#unless mark}}\n[{{this}}]\n{{/unless}}\n{{/each}}";

        self::assertSame("[a*]\n[b*]\n", $this->renderer->renderString($tpl, ['rows' => ['a', 'b'], 'mark' => true]));
        self::assertSame("[a]\n[b]\n", $this->renderer->renderString($tpl, ['rows' => ['a', 'b'], 'mark' => false]));
    }

    // ─── فایل stub و امنیت ────────────────────────────────────────────────

    public function testRender_RealStub_File(): void
    {
        $renderer = new StubRenderer($this->frameworkRoot() . '/resources/stubs');
        $out = $renderer->render('model.stub', [
            'namespace' => 'App\\Models',
            'class' => 'Product',
            'table' => 'products',
            'fillable' => "['name']",
        ]);

        self::assertStringContainsString('namespace App\\Models;', $out);
        self::assertStringContainsString('final class Product extends Model', $out);
        self::assertStringContainsString("protected string \$table = 'products';", $out);
        self::assertSame(0, \preg_match('/\{\{/', $out), 'جایگزین‌نشده باقی نماند');
    }

    public function testRender_MissingStub_Throws(): void
    {
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessageId('scaffold.stub_missing');
        $this->renderer->render('no-such-stub', []);
    }

    public function testPath_SanitizesTraversal(): void
    {
        $renderer = new StubRenderer('/base/stubs');
        $path = \str_replace('\\', '/', $renderer->path('../../etc/passwd'));

        self::assertStringContainsString('/base/stubs/', $path);
        self::assertStringNotContainsString('..', $path);
    }

    private function frameworkRoot(): string
    {
        return \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 4);
    }
}
