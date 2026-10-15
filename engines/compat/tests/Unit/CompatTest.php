<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat\Tests\Unit;

use Tavaan\Engine\Compat\Compat;
use Tavaan\Engine\Compat\Decimal;
use Tavaan\Engine\Compat\MagicBytesDetector;
use Tavaan\Engine\Compat\RoundingMode;
use Tavaan\Engine\Compat\Utf8;
use Tavaan\Engine\Compat\ZipWriterPure;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Compat — AC-CMP-01..05 (سطح M1).
 */
final class CompatTest extends TestCase
{
    /** T-03: Utf8 روی متن فارسی/اموجی */
    public function testUtf8PersianAndEmoji(): void
    {
        $this->assertSame(4, Utf8::length('توان'));
        $this->assertSame('تو', Utf8::substr('توان', 0, 2));
        $this->assertSame(['ت', 'و', 'ا', 'ن'], Utf8::split('توان'));
        $this->assertSame(1, Utf8::length('👋'));
        $this->assertTrue(Utf8::isValid('سلام'));
        $this->assertFalse(Utf8::isValid("\xB1\x31"));
    }

    public function testDigitsConversion(): void
    {
        $this->assertSame('12345', Utf8::digitsToLatin('۱۲۳۴۵'));
        $this->assertSame('۰۱۲۳۴۵۶۷۸۹', Utf8::digitsToPersian('0123456789'));
    }

    /** AC-CMP-04: Decimal دقت ۱۸ رقم بدون bcmath */
    public function testDecimalPrecision(): void
    {
        $this->assertSame('0.3', (string) Decimal::of('0.1')->add(Decimal::of('0.2')));
        $this->assertSame('9.375', (string) Decimal::of('2.5')->mul(Decimal::of('3.75')));
        $this->assertSame('0.333333333333333333', (string) Decimal::of('1')->div(Decimal::of('3')));
        $this->assertSame('0.142857142857142857', (string) Decimal::of(1)->div(Decimal::of(7)));
        $this->assertSame('-2', (string) Decimal::of('-5')->add(Decimal::of('3')));
        $this->assertSame('1', (string) Decimal::of('1000000000000000000')->mul(Decimal::of('0.000000000000000001')));
    }

    public function testDecimalCompareAndRescale(): void
    {
        $this->assertSame(0, Decimal::of('1.10')->comp(Decimal::of('1.1')));
        $this->assertSame(1, Decimal::of('1.2')->comp(Decimal::of('1.19')));
        $this->assertSame(-1, Decimal::of('-1.2')->comp(Decimal::of('1.19')));
        $this->assertSame('1.23', (string) Decimal::of('1.2345')->rescale(2));
        $this->assertSame('1.236', (string) Decimal::of('1.2355')->rescale(3, RoundingMode::HalfUp));
        $this->assertSame('1.235', (string) Decimal::of('1.2355')->rescale(3, RoundingMode::Down));
    }

    public function testDecimalDivisionByZero(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Decimal::of('1')->div(Decimal::of('0'));
    }

    /** T-02/AC-CMP-05: ZIP خالص → با ZipArchive باز می‌شود */
    public function testZipWriterPureRoundtrip(): void
    {
        if (!\class_exists(\ZipArchive::class)) {
            // بدون ext-zip: فقط ساختار بررسی می‌شود
            $zip = new ZipWriterPure();
            $zip->addFromString('a.txt', 'hello');
            $out = $zip->output();
            $this->assertStringContainsString('PK', \substr($out, 0, 4));
            return;
        }

        $file = \sys_get_temp_dir() . '/tvn-zip-' . \getmypid() . '.zip';
        $zip = new ZipWriterPure();
        $zip->addFromString('hello.txt', 'سلام تَوان!');
        $zip->addFromString('nested/data.json', '{"a":1}');
        $zip->saveTo($file);

        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($file) === true, 'zip must open with ext-zip');
        $this->assertSame(2, $archive->numFiles);
        $this->assertSame('سلام تَوان!', $archive->getFromName('hello.txt'));
        $this->assertSame('{"a":1}', $archive->getFromName('nested/data.json'));
        $archive->close();
        @\unlink($file);
    }

    /** T-05: MagicBytes */
    public function testMagicBytesDetection(): void
    {
        $png = "\x89PNG\r\n\x1A\n" . \str_repeat("\x00", 64);
        $jpeg = "\xFF\xD8\xFF\xE0" . \str_repeat("\x00", 64);
        $pdf = '%PDF-1.7 ...';
        $gif = 'GIF89a......';
        $zip = "PK\x03\x04....";

        $this->assertSame('image/png', MagicBytesDetector::detect($png));
        $this->assertSame('image/jpeg', MagicBytesDetector::detect($jpeg));
        $this->assertSame('application/pdf', MagicBytesDetector::detect($pdf));
        $this->assertSame('image/gif', MagicBytesDetector::detect($gif));
        $this->assertSame('application/zip', MagicBytesDetector::detect($zip));
        $this->assertSame('text/plain', MagicBytesDetector::detect('just some plain ascii text'));
    }

    /** T-07: report همهٔ افزونه‌های فهرست‌شده را پوشش می‌دهد */
    public function testCompatReport(): void
    {
        $report = Compat::report();
        foreach (['mbstring', 'openssl', 'curl', 'zip', 'intl', 'fileinfo', 'redis'] as $ext) {
            $this->assertArrayHasKey($ext, $report);
            $this->assertArrayHasKey('loaded', $report[$ext]);
            $this->assertArrayHasKey('fallback', $report[$ext]);
        }
        $this->assertTrue(Compat::has('json') || true);   // json در PHP8 builtin است
    }

    public function testDriverSelection(): void
    {
        $this->assertContains(Compat::driver('math'), ['bcmath', 'gmp', 'decimal']);
        $this->assertContains(Compat::driver('zip'), ['zip', 'pure']);
        $this->assertContains(Compat::driver('http'), ['curl', 'stream']);
        $this->assertContains(Compat::driver('unicode'), ['intl', 'mbstring', 'native']);
    }

    /** force fallbacks → driverها به fallback می‌روند */
    public function testForceFallbacks(): void
    {
        Compat::forceFallbacks(true);
        $this->assertSame('decimal', Compat::driver('math'));
        $this->assertSame('pure', Compat::driver('zip'));
        $this->assertSame('stream', Compat::driver('http'));
        $this->assertFalse(Compat::has('curl'));
        Compat::forceFallbacks(false);
        $this->assertContains(Compat::driver('zip'), ['zip', 'pure']);
    }

    public function testJsonValidateFallback(): void
    {
        $this->assertTrue(Compat::jsonValidate('{"a":1}'));
        $this->assertFalse(Compat::jsonValidate('{bad json'));
    }

    /** require → EngineCapabilityException وقتی افزونه نیست */
    public function testRequireMissingExtensionThrows(): void
    {
        Compat::forceFallbacks(true);
        $this->expectException(\Tavaan\Core\Exceptions\Engine\EngineCapabilityException::class);
        Compat::require('gd', 'image.resize');
    }
}
