<?php

declare(strict_types=1);

namespace Tavaan\Tests\Unit;

use Tavaan\Support\Str;
use Tavaan\Testing\TestCase;

/**
 * تست Str::plural (افزودنی M8 برای DOC-077 §5 — نام جدول/کلاس).
 */
final class StrTest extends TestCase
{
    public function testPlural_RegularAndCasePreserving(): void
    {
        self::assertSame('products', Str::plural('product'));
        self::assertSame('Products', Str::plural('Product'));
        self::assertSame('orders', Str::plural('order'));
    }

    public function testPlural_SibilantsTakeEs(): void
    {
        self::assertSame('boxes', Str::plural('box'));
        self::assertSame('classes', Str::plural('class'));
        self::assertSame('buses', Str::plural('bus'));
        self::assertSame('dishes', Str::plural('dish'));
        self::assertSame('watches', Str::plural('watch'));
        self::assertSame('quizzes', Str::plural('quiz'));
    }

    public function testPlural_ConsonantYBecomesIes(): void
    {
        self::assertSame('categories', Str::plural('category'));
        self::assertSame('cities', Str::plural('city'));
        // واکه + y → فقط s
        self::assertSame('keys', Str::plural('key'));
        self::assertSame('days', Str::plural('day'));
    }

    public function testPlural_Irregulars(): void
    {
        self::assertSame('people', Str::plural('person'));
        self::assertSame('children', Str::plural('child'));
        self::assertSame('data', Str::plural('datum'));
        self::assertSame('media', Str::plural('medium'));
        self::assertSame('indices', Str::plural('index'));
        self::assertSame('People', Str::plural('Person'));
    }

    public function testPlural_EmptyString(): void
    {
        self::assertSame('', Str::plural(''));
    }
}
