<?php

declare(strict_types=1);

namespace Tavaan\Engine\View\Tests\Unit;

use Tavaan\Engine\View\Exceptions\ViewException;
use Tavaan\Engine\View\ViewFactory;
use Tavaan\Testing\TestCase;

/**
 * تست موتور View — سناریوهای DOC-043 §12 (AC-VIEW-01/02).
 */
final class ViewTest extends TestCase
{
    private string $viewsDir;

    private string $compiledDir;

    protected function setUp(): void
    {
        $base = \sys_get_temp_dir() . '/tvn-view-test-' . \getmypid();
        $this->viewsDir = $base . '/views';
        $this->compiledDir = $base . '/compiled';
        @\mkdir($this->viewsDir . '/layouts', 0775, true);
        @\mkdir($this->viewsDir . '/components', 0775, true);
        @\mkdir($this->compiledDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir(\sys_get_temp_dir() . '/tvn-view-test-' . \getmypid());
    }

    private function factory(): ViewFactory
    {
        return new ViewFactory(null, [
            'paths' => [$this->viewsDir],
            'compiled' => $this->compiledDir,
            'max_depth' => 5,
        ]);
    }

    private function put(string $name, string $content): void
    {
        \file_put_contents($this->viewsDir . '/' . \str_replace('.', '/', $name) . '.tv.php', $content);
    }

    /** T-01: {{ }} روی script → escaped */
    public function testAutoEscape(): void
    {
        $this->put('t', '<p>{{ $x }}</p>');
        $out = $this->factory()->render('t', ['x' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    /** XSS fuzzing mini (T-18 subset) */
    public function testXssPayloadsAllEscaped(): void
    {
        $payloads = [
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            "' onmouseover='alert(1)",
            '<svg/onload=alert(1)>',
            'javascript:alert(1)',
            '<iframe src="javascript:alert(1)">',
            "\"><script>alert(String.fromCharCode(88,83,83))</script>",
            '<body onload=alert(1)>',
            '{{constructor.constructor("alert(1)")()}}',
            '<a href="‖ onmouseover="alert(1)">x</a>',
        ];

        $this->put('fuzz', '{{ $p }}');
        $factory = $this->factory();
        foreach ($payloads as $payload) {
            $out = $factory->render('fuzz', ['p' => $payload]);
            // هیچ‌گاه نباید تگ اجرایی خام ظاهر شود
            $this->assertFalse(
                (bool) \preg_match('/<\s*(script|iframe|svg|img|body)[^>]*>/i', $out),
                'Payload must not produce raw tags: ' . $payload,
            );
        }
    }

    /** attribute escape (ENT_QUOTES) */
    public function testAttributeEscape(): void
    {
        $this->put('attr', '<input value="{{ $v }}">');
        $out = $this->factory()->render('attr', ['v' => '" onfocus="alert(1)']);
        $this->assertStringNotContainsString('" onfocus=', $out);
        $this->assertStringContainsString('&quot;', $out);
    }

    /** T-02: {!! !!} → raw */
    public function testRawEcho(): void
    {
        $this->put('raw', '<div>{!! $html !!}</div>');
        $out = $this->factory()->render('raw', ['html' => '<b>bold</b>']);
        $this->assertStringContainsString('<b>bold</b>', $out);
    }

    /** T-17: @verbatim */
    public function testVerbatim(): void
    {
        $this->put('verb', '@verbatim{{ vueVar }}@endverbatim and {{ $php }}');
        $out = $this->factory()->render('verb', ['php' => 'ok']);
        $this->assertStringContainsString('{{ vueVar }}', $out);
        $this->assertStringContainsString('ok', $out);
    }

    /** @{{ escape */
    public function testAtBraceEscape(): void
    {
        $this->put('atbrace', '@{{ keep }}');
        $out = $this->factory()->render('atbrace');
        $this->assertStringContainsString('{{ keep }}', $out);
    }

    /** T-03: if/elseif/else */
    public function testConditionals(): void
    {
        $this->put('cond', '@if ($n > 10)big' . "\n" . '@elseif ($n > 5)mid' . "\n" . '@else' . "\n" . 'small' . "\n" . '@endif');
        $f = $this->factory();
        $this->assertStringContainsString('big', $f->render('cond', ['n' => 20]));
        $this->assertStringContainsString('mid', $f->render('cond', ['n' => 7]));
        $this->assertStringContainsString('small', $f->render('cond', ['n' => 1]));
    }

    /** T-04: foreach/forelse */
    public function testForeachAndForelse(): void
    {
        $this->put('loop', '@foreach ($items as $i)[{{ $i }}]' . "\n" . '@endforeach');
        $this->put('felse', '@forelse ($items as $i){{ $i }},' . "\n" . '@empty' . "\n" . 'NONE' . "\n" . '@endforelse');
        $f = $this->factory();
        $this->assertStringContainsString('[a]', $f->render('loop', ['items' => ['a', 'b']]));
        $this->assertStringContainsString('[b]', $f->render('loop', ['items' => ['a', 'b']]));
        $felseOut = $f->render('felse', ['items' => [1, 2]]);
        $this->assertStringContainsString('1,', $felseOut);
        $this->assertStringContainsString('2,', $felseOut);
        $this->assertStringNotContainsString('NONE', $felseOut);
        $this->assertStringContainsString('NONE', $f->render('felse', ['items' => []]));
    }

    public function testSwitchDirective(): void
    {
        $this->put('sw', '@switch ($s)' . "\n" . '@case("a") ALPHA @break' . "\n" . '@case("b") BETA @break' . "\n" . '@default OTHER' . "\n" . '@endswitch');
        $f = $this->factory();
        $this->assertStringContainsString('ALPHA', $f->render('sw', ['s' => 'a']));
        $this->assertStringContainsString('OTHER', $f->render('sw', ['s' => 'z']));
    }

    /** T-05: extends + section + yield */
    public function testLayoutInheritance(): void
    {
        $this->put('layouts.base', '<html><title>@yield("title", "def")</title><body>@yield("content")</body><footer>@yield("foot")</footer></html>');
        $this->put('page', '@extends("layouts.base")@section("title", "My Title")@section("content")<h1>Hi</h1>@endsection');

        $out = $this->factory()->render('page');
        $this->assertStringContainsString('<title>My Title</title>', $out);
        $this->assertStringContainsString('<h1>Hi</h1>', $out);
        $this->assertStringContainsString('<footer></footer>', $out);
    }

    /** T-06: nested layout (۲ سطح) */
    public function testNestedLayout(): void
    {
        $this->put('layouts.root', 'ROOT[@yield("content")]');
        $this->put('layouts.mid', '@extends("layouts.root")' . "\n" . '@section("content")MID(@yield("inner"))@endsection');
        $this->put('leaf', '@extends("layouts.mid")' . "\n" . '@section("inner")LEAF' . "\n" . '@endsection');

        $out = $this->factory()->render('leaf');
        $this->assertStringContainsString('ROOT[MID(LEAF', $out);
        $this->assertStringContainsString(')]', $out);
    }

    /** T-07: push/stack */
    public function testPushStack(): void
    {
        $this->put('layouts.s', '<head>@stack("styles")</head>@yield("c")');
        $this->put('p', '@extends("layouts.s")' . "\n" . '@section("c")' . "\n" . '@push("styles")<style>a{}</style>@endpush' . "\n" . '@push("styles")<style>b{}</style>@endpush' . "\n" . 'X' . "\n" . '@endsection');

        $out = $this->factory()->render('p');
        $this->assertStringContainsString('<style>a{}</style><style>b{}</style>', $out);
    }

    /** T-08: include */
    public function testIncludeWithData(): void
    {
        $this->put('partial', '[{{ $name }}]');
        $this->put('host', 'A' . "\n" . '@include("partial", ["name" => "B"])' . "\n" . 'C');

        $out = $this->factory()->render('host');
        $this->assertStringContainsString('A', $out);
        $this->assertStringContainsString('[B]', $out);
        $this->assertStringContainsString('C', $out);
    }

    public function testIncludeIfMissing(): void
    {
        $this->put('host2', 'X' . "\n" . '@includeIf("no-such-view")' . "\n" . 'Y');
        $out = $this->factory()->render('host2');
        $this->assertStringContainsString('X', $out);
        $this->assertStringContainsString('Y', $out);
    }

    /** T-09: component با props و slot */
    public function testComponentWithPropsAndSlots(): void
    {
        $this->put('components.alert', '@props(["type" => "info"])<div class="alert alert--{{ $type }}">{{ $icon ?? "" }}{{ $slot }}<span {{ $attributes }}>x</span></div>');
        $this->put('usage', '<x-alert type="danger" class="m-2"><x-slot name="icon">⚠️</x-slot>BODY {{ $msg }}</x-alert>');

        $out = $this->factory()->render('usage', ['msg' => 'hi']);
        $this->assertStringContainsString('alert--danger', $out);
        $this->assertStringContainsString('⚠️', $out);
        $this->assertStringContainsString('BODY hi', $out);
        $this->assertStringContainsString('class="m-2"', $out);   // attribute bag
    }

    /** self-closing component */
    public function testSelfClosingComponent(): void
    {
        $this->put('components.badge', '<span class="badge">{{ $label ?? "?" }}</span>');
        $this->put('usage2', '<x-badge label="NEW" />');
        $out = $this->factory()->render('usage2');
        $this->assertStringContainsString('<span class="badge">NEW</span>', $out);
    }

    /** T-13: compile cache invalidation با mtime */
    public function testCompileCacheInvalidation(): void
    {
        $this->put('cached', 'v1');
        $f = $this->factory();
        $this->assertSame('v1', $f->render('cached'));

        // تغییر template (mtime جلو)
        \sleep(1);
        $this->put('cached', 'v2');
        \touch($this->viewsDir . '/cached.tv.php', \time() + 1);

        $this->assertSame('v2', $this->factory()->render('cached'));
    }

    /** T-14: directive نامعتبر */
    public function testUnknownDirectiveThrows(): void
    {
        $this->put('bad', '@nosuchdirective("x")');
        $this->expectException(ViewException::class);
        $this->factory()->render('bad');
    }

    /** T-15: recursive include → max depth */
    public function testRecursiveIncludeHitsMaxDepth(): void
    {
        $this->put('rec', '@include("rec")');
        $this->expectException(ViewException::class);
        $this->factory()->render('rec');
    }

    /** T-16: @json فارسی */
    public function testJsonDirectiveUnicode(): void
    {
        $this->put('j', '<script>var d = @json($data);</script>');
        $out = $this->factory()->render('j', ['data' => ['x' => '</script><script>alert(1)</script>', 'fa' => 'توان']]);
        $this->assertStringContainsString('توان', $out);
        // JSON_HEX_TAG: تگ‌ها داخل payload باید \u003C شوند → فقط یک </script> (پایان قالب)
        $this->assertSame(1, \substr_count($out, '</script>'));
        $this->assertStringContainsString('\\u003C', $out);
    }

    /** directive سفارشی */
    public function testCustomDirective(): void
    {
        $this->put('custom', '@upper($name)');
        $f = $this->factory();
        $f->directive('upper', static fn (?string $expr): string => '<?php echo strtoupper((string) (' . $expr . ')); ?>');
        $this->assertSame('ABC', $f->render('custom', ['name' => 'abc']));
    }

    /** @@ escape و ایمیل */
    public function testAtEscapes(): void
    {
        $this->put('email', 'contact: user@example.com @@if literal');   // ایمیل + escape
        $out = $this->factory()->render('email');
        $this->assertStringContainsString('user@example.com', $out);
        $this->assertStringContainsString('@if literal', $out);
    }

    /** share */
    public function testSharedData(): void
    {
        $this->put('shared', '{{ $appName }}');
        $f = $this->factory();
        $f->share('appName', 'Tavaan');
        $this->assertSame('Tavaan', $f->render('shared'));
    }

    /** view not found */
    public function testViewNotFound(): void
    {
        $this->expectException(ViewException::class);
        $this->factory()->render('does.not.exist');
    }

    /** path traversal در نام view */
    public function testTraversalInNameRejected(): void
    {
        $this->expectException(ViewException::class);
        $this->factory()->render('../../../etc/passwd');
    }

    /**
     * رگرسیون (M9/TVN-0091): `@include` درون section نباید پشتهٔ extends
     * قالب والد را مصرف کند — پیش‌تر کل خروجی صفحه به ۱ بایت سقوط می‌کرد.
     */
    public function testIncludeInsideSectionKeepsParentExtends(): void
    {
        $this->put('layouts.l', '<html>@yield("content")</html>');
        $this->put('partialinc', '<nav>INC</nav>');
        $this->put(
            'page',
            '@extends("layouts.l")' . "\n"
            . '@section("content")' . "\n"
            . '<h1>PAGE</h1>' . "\n"
            . '@include("partialinc")' . "\n"
            . '@endsection',
        );

        $out = $this->factory()->render('page');

        $this->assertStringContainsString('<html>', $out);
        $this->assertStringContainsString('PAGE', $out);
        $this->assertStringContainsString('INC', $out);
    }

    /** include با extends مستقل خودش — چرخهٔ والد دست‌نخورده می‌ماند. */
    public function testIncludeWithOwnExtendsDoesNotLeak(): void
    {
        $this->put('layouts.outer', 'OUTER[@yield("content")]');
        $this->put('layouts.inner', 'INNER(@yield("body"))');
        $this->put('widget', '@extends("layouts.inner")' . "\n" . '@section("body")' . "\n" . 'W' . "\n" . '@endsection');
        $this->put(
            'host',
            '@extends("layouts.outer")' . "\n"
            . '@section("content")' . "\n" . 'C1' . "\n" . '@include("widget")' . "\n" . 'C2' . "\n" . '@endsection',
        );

        $out = $this->factory()->render('host');

        $this->assertStringContainsString('OUTER[', $out);
        $this->assertStringContainsString('C1', $out);
        $this->assertStringContainsString('INNER(', $out);
        $this->assertStringContainsString('W', $out);
        $this->assertStringContainsString('C2', $out);
        // ترتیب: محتوای include بین C1 و C2 و درون layout والد
        $this->assertTrue(
            \strpos($out, 'C1') < \strpos($out, 'INNER(') && \strpos($out, 'INNER(') < \strpos($out, 'C2'),
            'include باید بین C1 و C2 رندر شود',
        );
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
            $p = $dir . '/' . $f;
            \is_dir($p) ? $this->rrmdir($p) : @\unlink($p);
        }
        @\rmdir($dir);
    }
}
