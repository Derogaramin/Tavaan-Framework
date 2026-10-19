<?php

declare(strict_types=1);

namespace Tavaan\Testing\Database;

use Tavaan\Engine\I18n\Jalali\Jalali;

/**
 * تَوان | Tavaan — Testing
 * Faker — دادهٔ ساختگی خودنوشته و **Deterministic** (DOC-084E §6.1).
 *
 * بدون fzaninotto/faker؛ با seed (پیش‌فرض ثابت) توالی خروجی کاملاً تکرارپذیر
 * است → تست‌ها به random بدون seed وابسته نیستند (DoD §1.2).
 *
 * PRNG: LCG هم‌نهشتی با حساب ۶۴ بیتی امن (بدون سرریز به float) —
 * `state = (state * 1103515245 + 12345) mod 2^31`.
 *
 * ```php
 * $faker = new Faker(seed: 42);
 * $faker->nationalCode();   // کد ملی معتبر (checksum صحیح)
 * $faker->unique()->email(); // بدون تکرار
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class Faker
{
    /** seed پیش‌فرض → حتی بدون seed صریح، خروجی قطعی است. */
    public const DEFAULT_SEED = 14031399;

    private const MODULUS = 2147483648;   // 2^31

    private const EPOCH = '2024-01-01 00:00:00';

    /** @var list<array{fa: string, en: string}> */
    private const FIRST_NAMES = [
        ['fa' => 'علی', 'en' => 'ali'], ['fa' => 'محمد', 'en' => 'mohammad'],
        ['fa' => 'حسین', 'en' => 'hossein'], ['fa' => 'رضا', 'en' => 'reza'],
        ['fa' => 'مهدی', 'en' => 'mehdi'], ['fa' => 'حسن', 'en' => 'hasan'],
        ['fa' => 'جواد', 'en' => 'javad'], ['fa' => 'مصطفی', 'en' => 'mostafa'],
        ['fa' => 'امیر', 'en' => 'amir'], ['fa' => 'سعید', 'en' => 'saeid'],
        ['fa' => 'زهرا', 'en' => 'zahra'], ['fa' => 'فاطمه', 'en' => 'fatemeh'],
        ['fa' => 'مریم', 'en' => 'maryam'], ['fa' => 'سارا', 'en' => 'sara'],
        ['fa' => 'نرگس', 'en' => 'narges'], ['fa' => 'زینب', 'en' => 'zeinab'],
        ['fa' => 'لیلا', 'en' => 'leila'], ['fa' => 'الهه', 'en' => 'elaheh'],
        ['fa' => 'مینا', 'en' => 'mina'], ['fa' => 'پریسا', 'en' => 'parisa'],
    ];

    /** @var list<array{fa: string, en: string}> */
    private const LAST_NAMES = [
        ['fa' => 'محمدی', 'en' => 'mohammadi'], ['fa' => 'حسینی', 'en' => 'hosseini'],
        ['fa' => 'رضایی', 'en' => 'rezaei'], ['fa' => 'کریمی', 'en' => 'karimi'],
        ['fa' => 'موسوی', 'en' => 'mousavi'], ['fa' => 'هاشمی', 'en' => 'hashemi'],
        ['fa' => 'صادقی', 'en' => 'sadeghi'], ['fa' => 'جعفری', 'en' => 'jafari'],
        ['fa' => 'رحیمی', 'en' => 'rahimi'], ['fa' => 'احمدی', 'en' => 'ahmadi'],
        ['fa' => 'اکبری', 'en' => 'akbari'], ['fa' => 'قاسمی', 'en' => 'ghasemi'],
        ['fa' => 'شریفی', 'en' => 'sharifi'], ['fa' => 'نوری', 'en' => 'noori'],
        ['fa' => 'کاظمی', 'en' => 'kazemi'],
    ];

    /** @var list<string> */
    private const LOREM_WORDS = [
        'متن', 'آزمایشی', 'سامانه', 'داده', 'کاربر', 'گزارش', 'سیستم', 'مدیریت',
        'پردازش', 'اطلاعات', 'فهرست', 'جزئیات', 'عملیات', 'سرعت', 'دقت', 'کیفیت',
        'توسعه', 'پیاده‌سازی', 'معماری', 'ماژول', 'هسته', 'موتور', 'پیکربندی',
        'مسیر', 'درخواست', 'پاسخ', 'خطا', 'رویداد', 'صف', 'حافظه',
    ];

    /** @var list<string> */
    private const EMAIL_DOMAINS = ['example.com', 'example.org', 'example.net', 'test.local', 'mail.example.ir'];

    private int $state;

    private bool $uniqueMode = false;

    /** @var array<string, array<string, true>> */
    private array $seen = [];

    public function __construct(?int $seed = null)
    {
        $this->state = ((($seed ?? self::DEFAULT_SEED) % (self::MODULUS - 1)) + self::MODULUS) % (self::MODULUS - 1) + 1;
    }

    // ─── افراد ────────────────────────────────────────────────────────────

    /** نام و نام خانوادگی فارسی. */
    public function name(): string
    {
        return $this->roll('name', fn (): string => $this->firstName() . ' ' . $this->lastName());
    }

    public function firstName(): string
    {
        return $this->randomElement(self::FIRST_NAMES)['fa'];
    }

    public function lastName(): string
    {
        return $this->randomElement(self::LAST_NAMES)['fa'];
    }

    /** ایمیل ASCII (ساختگی — دامنه‌های example). */
    public function email(): string
    {
        return $this->roll('email', function (): string {
            $first = $this->randomElement(self::FIRST_NAMES)['en'];
            $last = $this->randomElement(self::LAST_NAMES)['en'];
            $domain = $this->randomElement(self::EMAIL_DOMAINS);

            return $first . '.' . $last . $this->numberBetween(1, 999) . '@' . $domain;
        });
    }

    // ─── دادهٔ ایرانی ─────────────────────────────────────────────────────

    /** شمارهٔ موبایل (`09xxxxxxxxx`). */
    public function mobile(): string
    {
        return $this->roll('mobile', fn (): string => '09' . $this->numberBetween(100000000, 999999999));
    }

    /** کد ملی ۱۰ رقمی با checksum معتبر (الگوریتم رسمی). */
    public function nationalCode(): string
    {
        return $this->roll('nationalCode', function (): string {
            do {
                $base = '';
                for ($i = 0; $i < 9; $i++) {
                    $base .= (string) $this->numberBetween(0, 9);
                }
            } while (\count(\array_unique(\str_split($base))) === 1);   // ارقام یکسان نامعتبر است

            $sum = 0;
            foreach (\str_split($base) as $index => $digit) {
                $sum += (int) $digit * (10 - $index);
            }
            $remainder = $sum % 11;
            $check = $remainder < 2 ? $remainder : 11 - $remainder;

            return $base . (string) $check;
        });
    }

    /** تاریخ جلالی (`1403/04/12`) از dateTime() جاری. */
    public function jalaliDate(): string
    {
        $date = $this->dateTime();
        [$jy, $jm, $jd] = Jalali::toJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        return \sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    // ─── عمومی ────────────────────────────────────────────────────────────

    public function numberBetween(int $min, int $max): int
    {
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
        $span = $max - $min + 1;

        return $min + $this->nextInt() % $span;
    }

    /** @param list<mixed> $items */
    public function randomElement(array $items): mixed
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Faker::randomElement requires a non-empty list.');
        }

        return $items[$this->numberBetween(0, \count($items) - 1)];
    }

    public function boolean(int $chanceOfTrue = 50): bool
    {
        return $this->roll('boolean', fn (): bool => $this->numberBetween(1, 100) <= $chanceOfTrue);
    }

    public function word(): string
    {
        return $this->randomElement(self::LOREM_WORDS);
    }

    /** متن فارسی تا حداکثر طول داده‌شده (برش روی مرز واژه). */
    public function text(int $maxChars = 200): string
    {
        $maxChars = \max(1, $maxChars);
        $words = [];
        $length = 0;

        do {
            $word = $this->word();
            $add = \mb_strlen($word) + ($words === [] ? 0 : 1);
            if ($length + $add > $maxChars) {
                break;
            }
            $words[] = $word;
            $length += $add;
        } while ($length < $maxChars);

        if ($words === []) {
            return \mb_substr($this->word(), 0, $maxChars);
        }

        return \implode(' ', $words);
    }

    /**
     * زمان deterministic: بازهٔ ±۳ سال حول epoch ثابت (UTC).
     */
    public function dateTime(): \DateTimeImmutable
    {
        $epoch = (new \DateTimeImmutable(self::EPOCH, new \DateTimeZone('UTC')))->getTimestamp();
        $offset = $this->numberBetween(-3 * 365 * 86400, 3 * 365 * 86400);

        return (new \DateTimeImmutable('@' . ($epoch + $offset)))->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * نسخهٔ «بدون تکرار» — هر مقدار تولیدی برای همان متد یکتا می‌ماند.
     *
     * (clone → زنجیره‌های جدا unique حافظهٔ جدا دارند؛ deterministic)
     */
    public function unique(): self
    {
        $clone = clone $this;
        $clone->uniqueMode = true;

        return $clone;
    }

    // ─── internals ────────────────────────────────────────────────────────

    /** یک گام LCG — خروجی `0 .. 2^31-1`. */
    private function nextInt(): int
    {
        // 🔒 max(state)*1103515245 < 2^62 → بدون سرریز int به float
        $this->state = ($this->state * 1103515245 + 12345) % self::MODULUS;

        return $this->state;
    }

    /**
     * تولید مقدار با اعمال حالت unique (حداکثر ۱۰۰۰ تلاش).
     */
    private function roll(string $key, \Closure $generator): mixed
    {
        if (!$this->uniqueMode) {
            return $generator();
        }

        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $value = $generator();
            $hash = \is_scalar($value) ? (string) $value : \serialize($value);
            if (!isset($this->seen[$key][$hash])) {
                $this->seen[$key][$hash] = true;

                return $value;
            }
        }

        throw new \RuntimeException(\sprintf('Faker::unique() exhausted after 1000 attempts for [%s].', $key));
    }
}
