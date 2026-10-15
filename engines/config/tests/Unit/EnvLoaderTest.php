<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Tests\Unit;

use Tavaan\Engine\Config\EnvLoader;
use Tavaan\Engine\Config\Exceptions\EnvParseException;
use Tavaan\Testing\TestCase;

/**
 * تست EnvLoader — سناریوهای DOC-027 §4 (AC-BOOT-05).
 */
final class EnvLoaderTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = \sys_get_temp_dir() . '/tvn-env-' . \getmypid() . '.env';
    }

    protected function tearDown(): void
    {
        @\unlink($this->file);
    }

    /** T-04: quote/escape/inline comment/true-false-null-empty */
    public function testParsesValuesCorrectly(): void
    {
        \file_put_contents($this->file, <<<'ENV'
# comment line
TVN_PLAIN=hello
TVN_QUOTED="double quoted"
TVN_ESCAPED="line\nbreak"
TVN_LITERAL='no \escape here'
TVN_INLINE=value # trailing comment
TVN_TRUE=true
TVN_FALSE=(false)
TVN_NULL=null
TVN_EMPTY=(empty)
TVN_INT=42
TVN_NEG=-7
TVN_FLOAT=1.5
TVN_URL="http://example.com?a=1&b=2"
ENV);

        $vars = (new EnvLoader())->load($this->file);

        $this->assertSame('hello', $vars['TVN_PLAIN']);
        $this->assertSame('double quoted', $vars['TVN_QUOTED']);
        $this->assertSame("line\nbreak", $vars['TVN_ESCAPED']);
        $this->assertSame('no \\escape here', $vars['TVN_LITERAL']);
        $this->assertSame('value', $vars['TVN_INLINE']);
        $this->assertTrue($vars['TVN_TRUE']);
        $this->assertFalse($vars['TVN_FALSE']);
        $this->assertNull($vars['TVN_NULL']);
        $this->assertSame('', $vars['TVN_EMPTY']);
        $this->assertSame(42, $vars['TVN_INT']);
        $this->assertSame(-7, $vars['TVN_NEG']);
        $this->assertSame(1.5, $vars['TVN_FLOAT']);
        $this->assertSame('http://example.com?a=1&b=2', $vars['TVN_URL']);
    }

    /** T-05: کلید نامعتبر → EnvParseException با شمارهٔ خط */
    public function testInvalidKeyThrowsWithLineNumber(): void
    {
        \file_put_contents($this->file, "GOOD=1\nbad-key=2\n");
        try {
            (new EnvLoader())->load($this->file);
            self::fail('Expected EnvParseException');
        } catch (EnvParseException $e) {
            $this->assertSame('core.env.parse_failed', $e->messageId);
            $this->assertSame(2, $e->context['line']);
        }
    }

    /** فایل ناموجود → آرایهٔ خالی (بدون خطا) */
    public function testMissingFileReturnsEmpty(): void
    {
        $this->assertSame([], (new EnvLoader())->load('/nonexistent/.env'));
    }
}
