<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Unit;

use Tavaan\Testing\Database\Faker;
use Tavaan\Testing\TestCase;

/**
 * تست Faker خودنوشته — Deterministic با seed (DOC-084E §6.1 — AC-TEST-04).
 */
final class FakerTest extends TestCase
{
    // ─── Determinism ──────────────────────────────────────────────────────

    public function testSameSeed_ProducesIdenticalSequence(): void
    {
        $a = new Faker(42);
        $b = new Faker(42);

        for ($i = 0; $i < 20; $i++) {
            self::assertSame($a->name(), $b->name());
            self::assertSame($a->email(), $b->email());
            self::assertSame($a->mobile(), $b->mobile());
            self::assertSame($a->nationalCode(), $b->nationalCode());
            self::assertSame($a->numberBetween(0, 1000), $b->numberBetween(0, 1000));
            self::assertSame($a->text(60), $b->text(60));
            self::assertSame($a->dateTime()->format('c'), $b->dateTime()->format('c'));
            self::assertSame($a->jalaliDate(), $b->jalaliDate());
        }
    }

    public function testDefaultSeed_IsDeterministicAcrossInstances(): void
    {
        $a = new Faker();
        $b = new Faker();

        self::assertSame($a->email(), $b->email());
        self::assertSame($a->nationalCode(), $b->nationalCode());
    }

    public function testDifferentSeeds_ProduceDifferentSequences(): void
    {
        $a = new Faker(1);
        $b = new Faker(2);

        $valuesA = [$a->mobile(), $a->email(), (string) $a->numberBetween(0, PHP_INT_MAX >> 1)];
        $valuesB = [$b->mobile(), $b->email(), (string) $b->numberBetween(0, PHP_INT_MAX >> 1)];

        self::assertNotSame($valuesA, $valuesB);
    }

    // ─── قالب داده‌ها ─────────────────────────────────────────────────────

    public function testMobile_HasIranianFormat(): void
    {
        $faker = new Faker(7);
        for ($i = 0; $i < 50; $i++) {
            self::assertMatchesRegex('/^09\d{9}$/', $faker->mobile());
        }
    }

    public function testNationalCode_HasValidChecksum(): void
    {
        $faker = new Faker(11);
        for ($i = 0; $i < 50; $i++) {
            $code = $faker->nationalCode();
            self::assertMatchesRegex('/^\d{10}$/', $code);
            self::assertGreaterThan(1, \count(\array_unique(\str_split($code))), 'ارقام یکسان نامعتبر است');
            self::assertTrue(self::nationalCodeIsValid($code), 'checksum نامعتبر: ' . $code);
        }
    }

    public function testEmail_IsValidAscii(): void
    {
        $faker = new Faker(3);
        for ($i = 0; $i < 30; $i++) {
            $email = $faker->email();
            self::assertTrue(\filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'ایمیل نامعتبر: ' . $email);
            self::assertMatchesRegex('/^[\x20-\x7E]+$/', $email);
        }
    }

    public function testName_HasTwoPersianParts(): void
    {
        $faker = new Faker(5);
        $name = $faker->name();
        $parts = \explode(' ', $name);

        self::assertCount(2, $parts);
        self::assertMatchesRegex('/^[\p{Arabic}\p{L}]+$/u', $parts[0]);
        self::assertMatchesRegex('/^[\p{Arabic}\p{L}‌]+$/u', $parts[1]);
    }

    public function testText_RespectsMaxLength(): void
    {
        $faker = new Faker(17);

        self::assertNotEmpty($faker->text(1));
        foreach ([5, 50, 200] as $max) {
            self::assertTrue(\mb_strlen($faker->text($max)) <= $max, 'text(' . $max . ') طولانی‌تر از حد');
        }
    }

    public function testNumberBetween_StaysInBounds(): void
    {
        $faker = new Faker(23);
        for ($i = 0; $i < 200; $i++) {
            $n = $faker->numberBetween(5, 9);
            self::assertTrue($n >= 5 && $n <= 9);
        }

        // آرگومان‌های وارونه نیز پذیرفته می‌شوند
        $swapped = $faker->numberBetween(9, 5);
        self::assertTrue($swapped >= 5 && $swapped <= 9);
    }

    public function testRandomElement_ReturnsListMember(): void
    {
        $faker = new Faker(29);
        $items = ['a', 'b', 'c'];
        for ($i = 0; $i < 20; $i++) {
            self::assertContains($faker->randomElement($items), $items);
        }
    }

    public function testRandomElement_WithEmptyList_Throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Faker())->randomElement([]);
    }

    public function testBoolean_RespectsChance(): void
    {
        $faker = new Faker(31);
        self::assertTrue($faker->boolean(100));
        self::assertFalse($faker->boolean(0));
    }

    public function testDateTime_IsUtcInDeterministicWindow(): void
    {
        $faker = new Faker(37);
        $epoch = new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC'));

        for ($i = 0; $i < 20; $i++) {
            $dt = $faker->dateTime();
            self::assertSame('UTC', $dt->getTimezone()->getName());
            $diffYears = \abs($epoch->getTimestamp() - $dt->getTimestamp()) / (365 * 86400);
            self::assertTrue($diffYears <= 3.01, 'dateTime بیرون از بازهٔ ±۳ سال');
        }
    }

    public function testJalaliDate_HasValidFormat(): void
    {
        $faker = new Faker(41);
        for ($i = 0; $i < 20; $i++) {
            $jalali = $faker->jalaliDate();
            self::assertMatchesRegex('/^\d{4}\/\d{2}\/\d{2}$/', $jalali);
            [$jy, $jm, $jd] = \array_map('intval', \explode('/', $jalali));
            self::assertTrue($jy >= 1398 && $jy <= 1406, 'سال جلالی بیرون بازه: ' . $jalali);
            self::assertTrue($jm >= 1 && $jm <= 12);
            self::assertTrue($jd >= 1 && $jd <= 31);
        }
    }

    // ─── unique ───────────────────────────────────────────────────────────

    public function testUnique_DoesNotRepeat(): void
    {
        $faker = (new Faker(53))->unique();
        $emails = [];
        for ($i = 0; $i < 100; $i++) {
            $emails[] = $faker->email();
        }

        self::assertCount(100, \array_unique($emails));
    }

    public function testUnique_ExhaustionThrows(): void
    {
        $faker = (new Faker(59))->unique();
        $faker->boolean();
        $faker->boolean();

        // فضای مقدار boolean دو تاست → تلاش سوم باید پس از ۱۰۰۰ کوشش شکست بخورد
        $this->expectException(\RuntimeException::class);
        $faker->boolean();
    }

    public function testUnique_CloneIsIndependentFromParent(): void
    {
        $faker = new Faker(61);
        $unique = $faker->unique();

        self::assertNotSame($faker, $unique);

        // فراخوانی والد پس از clone، توالی clone را جابه‌جا نمی‌کند
        $faker->mobile();
        self::assertSame((new Faker(61))->unique()->mobile(), $unique->mobile());
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    /** الگوریتم رسمی checksum کد ملی. */
    private static function nationalCodeIsValid(string $code): bool
    {
        if (\preg_match('/^\d{10}$/', $code) !== 1) {
            return false;
        }

        $sum = 0;
        foreach (\str_split(\substr($code, 0, 9)) as $index => $digit) {
            $sum += (int) $digit * (10 - $index);
        }
        $remainder = $sum % 11;
        $expected = $remainder < 2 ? $remainder : 11 - $remainder;

        return (int) $code[9] === $expected;
    }
}
