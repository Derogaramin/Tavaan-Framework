<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Unit;

use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Services\DurationParser;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست واحد DurationParser — ISO-8601/انسانی (DOC-098 §3/§9).
 */
final class DurationParserTest extends TestCase
{
    public function testIso8601Durations(): void
    {
        $p2d = DurationParser::parse('P2D');
        self::assertSame(2, $p2d->d);

        $pt30m = DurationParser::parse('PT30M');
        self::assertSame(30, $pt30m->i);

        $complex = DurationParser::parse('P1DT2H30M');
        self::assertSame(1, $complex->d);
        self::assertSame(2, $complex->h);
        self::assertSame(30, $complex->i);

        $zero = DurationParser::parse('P0D');
        self::assertSame(0, $zero->d);
    }

    public function testHumanDurations(): void
    {
        self::assertSame(172800, DurationParser::parse('2 days')->s);
        self::assertSame(10800, DurationParser::parse('+3 hours')->s);
        self::assertSame(604800, DurationParser::parse('1 week')->s);
        self::assertSame(60, DurationParser::parse('1 minute')->s);
    }

    public function testAddToMoment(): void
    {
        Clock::setTestNow('2026-10-26 08:00:00');
        try {
            $due = DurationParser::add(Clock::now(), 'P2D');
            self::assertSame('2026-10-28 08:00:00', $due->format('Y-m-d H:i:s'));

            $dueMinutes = DurationParser::add(Clock::now(), 'PT30M');
            self::assertSame('2026-10-26 08:30:00', $dueMinutes->format('Y-m-d H:i:s'));
        } finally {
            Clock::setTestNow(null);
        }
    }

    public function testInvalidDurationsAreRejected(): void
    {
        foreach (['', '   ', 'PXY', 'abc', '2 parsecs', 'P2D; DROP', 'yesterday', 'now', '+5'] as $bad) {
            self::assertFalse(DurationParser::isValid($bad), 'باید نامعتبر باشد: ' . $bad);
            try {
                DurationParser::parse($bad);
                self::fail('باید خطا می‌داد: ' . $bad);
            } catch (BpmsException $e) {
                self::assertSame('invalid_expression', $e->reason());
                self::assertSame('invalid_duration', $e->context()['detail'] ?? '');
            }
        }
    }

    public function testValidDurationsAccepted(): void
    {
        foreach (['P2D', 'PT30M', 'P1W', 'P1DT2H30M', '3 days', '+2 hours', '1 minute'] as $good) {
            self::assertTrue(DurationParser::isValid($good), 'باید معتبر باشد: ' . $good);
        }
    }
}
