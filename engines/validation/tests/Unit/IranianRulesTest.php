<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Tests\Unit;

use Tavaan\Engine\Validation\Support\IranianChecks;
use Tavaan\Engine\Validation\Validator;
use Tavaan\Testing\TestCase;

/**
 * تست قواعد ایرانی — DOC-051 §11 T-02/T-03 (AC-VAL-02).
 */
final class IranianRulesTest extends TestCase
{
    /** @param array<string, string|list<mixed>> $rules */
    private function check(mixed $value, string $rule): bool
    {
        return (new Validator(['a' => $value], ['a' => $rule]))->passes();
    }

    public function testIranMobile(): void
    {
        foreach (['09121234567', '09351234567', '09011234567', '09191234567'] as $ok) {
            $this->assertTrue($this->check($ok, 'iran_mobile'), $ok . ' must be valid');
        }
        // فرمت‌های معادل
        $this->assertTrue($this->check('+989121234567', 'iran_mobile'));
        $this->assertTrue($this->check('989121234567', 'iran_mobile'));
        $this->assertTrue($this->check('9121234567', 'iran_mobile'));
        $this->assertTrue($this->check('۰۹۱۲۱۲۳۴۵۶۷', 'iran_mobile'));   // ارقام فارسی

        foreach (['08121234567', '0912123456', '091212345678', '12345678901', '00000000000', 'abc'] as $bad) {
            $this->assertFalse($this->check($bad, 'iran_mobile'), $bad . ' must be invalid');
        }
    }

    public function testIranNationalCode(): void
    {
        // ۱۰ نمونهٔ معتبر (checksum-verified)
        $valid = ['0084575948', '1234567891', '2356593810', '0499370899', '0790419904',
            '4672286479', '6523209938', '8471151790', '9700845052', '0796886016'];

        foreach ($valid as $code) {
            $this->assertTrue($this->check($code, 'iran_national_code'), 'valid code rejected: ' . $code);
        }

        // نامعتبر: ارقام یکسان، check غلط، طول غلط
        foreach (['1111111111', '0000000000', '2222222222', '9999999999',
            '0084575949', '1234567890', '12345', 'abcdefghij'] as $bad) {
            $this->assertFalse($this->check($bad, 'iran_national_code'), 'invalid code accepted: ' . $bad);
        }

        // رشتهٔ خالی: بدون required → skip (قرارداد)؛ با required → رد
        $this->assertFalse(IranianChecks::nationalCode(''));
        $this->assertFalse($this->check('', 'required|iran_national_code'));

        // ارقام فارسی + کوتاه (۹ رقمی با leading zero)
        $this->assertTrue($this->check('۰۰۸۴۵۷۵۹۴۸', 'iran_national_code'));
        $this->assertTrue(IranianChecks::nationalCode('84575948'));   // 8-digit padded
    }

    public function testIranSheba(): void
    {
        // نمونهٔ معتبر شناخته‌شده + تولیدشده با mod-97
        $this->assertTrue($this->check('IR820540102680020817909002', 'iran_sheba'));
        $this->assertTrue($this->check('IR550540102680020817909003', 'iran_sheba'));
        $this->assertTrue($this->check('ir820540102680020817909002', 'iran_sheba'));   // case-insensitive

        foreach (['IR830540102680020817909002', 'IR82054010268002081790900', 'IR8205401026800208179090021',
            '820540102680020817909002', 'XX820540102680020817909002', 'IR000000000000000000000000'] as $bad) {
            $this->assertFalse($this->check($bad, 'iran_sheba'), 'invalid sheba accepted: ' . $bad);
        }
    }

    public function testIranCardNumber(): void
    {
        $this->assertTrue($this->check('6037701689095443', 'iran_card_number'));   // Luhn-valid
        $this->assertTrue($this->check('6037991234567893', 'iran_card_number'));
        $this->assertTrue($this->check('6037-9912-3456-7893', 'iran_card_number'));   // با جداکننده

        foreach (['6037701689095444', '6037997599445540', '1111111111111111',
            '60379912345678', 'abcdefghijklmnop'] as $bad) {
            $this->assertFalse($this->check($bad, 'iran_card_number'), 'invalid card accepted: ' . $bad);
        }
        $this->assertFalse(IranianChecks::cardNumber(''));
        $this->assertFalse($this->check('', 'required|iran_card_number'));
    }

    public function testIranPostalCode(): void
    {
        $this->assertTrue($this->check('1234567890', 'iran_postal_code'));
        $this->assertTrue($this->check('1619735744', 'iran_postal_code'));
        $this->assertTrue($this->check('۱۶۱۹۷۳۵۷۴۴', 'iran_postal_code'));   // فارسی

        foreach (['0234567890', '2234567890', '1111111111', '123456789', '12345678901', 'abcdef'] as $bad) {
            $this->assertFalse($this->check($bad, 'iran_postal_code'), 'invalid postal accepted: ' . $bad);
        }
    }

    public function testIranCompanyId(): void
    {
        // نمونه‌های checksum-verified با الگوریتم (وزن ۲۹..۱۷ + offset d9+2)
        $this->assertTrue($this->check('10380127960', 'iran_company_id'));
        $this->assertTrue($this->check('14004184048', 'iran_company_id'));

        foreach (['10380127961', '11111111111', '1038012796', '103801279601', 'abc'] as $bad) {
            $this->assertFalse($this->check($bad, 'iran_company_id'), 'invalid company id accepted: ' . $bad);
        }
    }

    public function testJalaliDate(): void
    {
        $this->assertTrue($this->check('1404/01/01', 'jalali_date'));
        $this->assertTrue($this->check('1403/12/30', 'jalali_date'));    // ۱۴۰۳ کبیسه است
        $this->assertTrue($this->check('1402/12/29', 'jalali_date'));
        $this->assertTrue($this->check('1404-07-24', 'jalali_date_format:Y-m-d'));
        $this->assertTrue($this->check('۱۴۰۴/۰۱/۰۱', 'jalali_date'));   // ارقام فارسی

        $this->assertFalse($this->check('1402/12/30', 'jalali_date'));  // ۱۴۰۲ کبیسه نیست
        $this->assertFalse($this->check('1404/13/01', 'jalali_date'));
        $this->assertFalse($this->check('1404/00/01', 'jalali_date'));
        $this->assertFalse($this->check('1404/07/32', 'jalali_date'));
        $this->assertFalse($this->check('2026-01-01', 'jalali_date'));
    }

    public function testJalaliLeapConsistency(): void
    {
        // کبیسه‌های شناخته‌شدهٔ جلالی در بازهٔ اخیر
        foreach ([1399, 1403, 1408, 1412] as $leap) {
            $this->assertTrue(IranianChecks::isJalaliLeap($leap), (string) $leap . ' must be leap');
        }
        foreach ([1400, 1401, 1402, 1404, 1405] as $common) {
            $this->assertFalse(IranianChecks::isJalaliLeap($common), (string) $common . ' must not be leap');
        }
    }

    public function testPersianDigits(): void
    {
        $this->assertTrue($this->check('۱۲۳۴۵', 'persian_digits'));
        $this->assertTrue($this->check('کد ۱۲۳', 'persian_digits'));
        $this->assertFalse($this->check('12345', 'persian_digits'));
        $this->assertFalse($this->check('۱2۳', 'persian_digits'));       // مخلوط
        $this->assertFalse($this->check('١٢٣٤٥', 'persian_digits'));      // عربی-هندی
        $this->assertFalse($this->check('بدون رقم', 'persian_digits'));
    }

    public function testSafeUrlPrivateRanges(): void
    {
        foreach (['http://10.0.0.1', 'http://192.168.1.1/x', 'http://172.16.0.1',
            'http://169.254.169.254/latest/meta-data/', 'http://[::1]/', 'ftp://example.com'] as $bad) {
            $this->assertFalse(IranianChecks::safeUrl($bad), $bad . ' must be blocked');
        }
        $this->assertTrue(IranianChecks::safeUrl('https://8.8.8.8/dns'));
    }
}
