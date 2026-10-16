<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Support;

/**
 * تَوان | Tavaan — Validation
 * IranianChecks — الگوریتم‌های اعتبارسنجی بومی ایران 🇮🇷 (DOC-051 §3.7).
 *
 * کد ملی (checksum وزن ۱۰-i)، شبا (ISO 7064 mod-97)، کارت (Luhn)،
 * کد پستی، شناسهٔ ملی شخص حقوقی، تاریخ جلالی و ارقام فارسی.
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class IranianChecks
{
    /** هش sha256 رایج‌ترین رمزهای لو‌رفته (برای not_compromised — مقایسهٔ constant-time). */
    public const COMMON_PASSWORD_HASHES = [
        '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92',   // 123456
        '15e2b0d3c33891ebb0f1ef609ec419420c20e320ce94c65fbc8c3312448eb225',   // 123456789
        '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8',   // password
        'ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f',   // 12345678
        '65e84be33532fb784c48129675f9eff3a682b27168c0ea744b2cf58ee02337c5',   // qwerty
        '8bb0cf6eb9b17d0f7d22b456f121257dc1254e1f01665370476383ea776df414',   // 1234567
        'bcb15f821479b4d5772bd0ca866c00ad5f926e3580720659cc80d39c9d09802a',   // 111111
        '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5',   // 12345
        '0b14d501a594442a01c6859541bcb3e8164d183d32937b851835442f69d5c94e',   // password1
        '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',   // admin
        '96cae35ce8a9b0244178bf28e4966c2ce1b8385723a96a6b838858cdd6ca0a1e',   // 123123
        '6ca13d52ca70c883e0f0bb101e425a89e8624de51db2d2392593af6a84118090',   // abc123
        'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646',   // 1234567890
        '1c8bfe8f801d79745c4631d09fff36c82aa37fc4cce4fc946683d7b336b63032',   // letmein
        '280d44ab1e9f79b5cce2dd4f58f5fe91f0fbacdac9f7447dffc318ceb79f2d02',   // welcome
        '000c285457fc971f862a79b786476c78812c8897063c6fa9c045f579a3b2d63f',   // monkey
        'fc613b4dfd6736a7bd268c8a0e74ed0d1c04a959f59dd74ef2874983fd443fc9',   // master
        'a9c43be948c5cabd56ef2bacffb77cdaa5eec49dd5eb0cc4129cf3eda5f0e74c',   // dragon
        '428821350e9691491f616b754cd8315fb86d797ab35d843479e732ef90665324',   // login
        '04e77bf8f95cb3e1a36a59d1e93857c411930db646b46c218a0352e432023cf2',   // princess
        'daaad6e5604e8e17bd9f108d91e26afe6281dac8fda0091040a7a6d7bd9b43b5',   // qwerty123
        '5364f2f2fc4f54e9d47ad29cfb08ef430c8153394bf2a0dff5cbe77a0ffef861',   // solo
        '8f0e2f76e22b43e2855189877e7dc1e1e7d98c226c95db247cd1d547928334a9',   // passw0rd
        '74fca0325b5fdb3a34badb40a2581cfbd5344187e8d3432952a5abc0929c1246',   // starwars
        '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9',   // admin123
        '4813494d137e1631bba301d5acab6e7bb7aa74ce1185d456565ef51d737677b2',   // root
        'ce5ca673d13b36118d54a7cf13aeb0ca012383bf771e713421b4d1fd841f539a',   // toor
        'd74ff0ee8da3b9806b18c877dbf29bbde50b5bd8e4dad7a3a725000feb82e8f1',   // pass
        '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',   // test
        '84983c60f7daadc1cb8698621f802c0d9f9a3c3c295c810748fb048115c186ec',   // guest
    ];

    private function __construct()
    {
    }

    /** موبایل ایران: 09xxxxxxxxx (با پذیرش +98/98/0098 و ارقام فارسی/عربی). */
    public static function mobile(string $value): bool
    {
        $value = self::toLatinDigits(\trim($value));
        $value = \preg_replace('/[\s-]/', '', $value) ?? $value;

        if (\str_starts_with($value, '+98')) {
            $value = '0' . \substr($value, 3);
        } elseif (\str_starts_with($value, '0098')) {
            $value = '0' . \substr($value, 4);
        } elseif (\preg_match('/^98(9\d{9})$/', $value, $m) === 1) {
            $value = '0' . $m[1];
        } elseif (\preg_match('/^(9\d{9})$/', $value, $m) === 1) {
            $value = '0' . $m[1];
        }

        return \preg_match('/^09(?:0[1-5]|1[0-9]|2[0-2]|3[0-9]|4[1-9]|[5-9]\d)\d{7}$/', $value) === 1;
    }

    /** کد ملی ۱۰ رقمی با checksum (DOC-051 §4.2). */
    public static function nationalCode(string $value): bool
    {
        $value = self::toLatinDigits(\trim($value));
        $value = \str_pad($value, 10, '0', STR_PAD_LEFT);

        if (\preg_match('/^\d{10}$/', $value) !== 1) {
            return false;
        }

        // همه ارقام یکسان → نامعتبر
        if (\preg_match('/^(\d)\1{9}$/', $value) === 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $value[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $check = (int) $value[9];

        return ($remainder < 2 && $check === $remainder)
            || ($remainder >= 2 && $check === 11 - $remainder);
    }

    /** کد پستی ۱۰ رقمی ایران. */
    public static function postalCode(string $value): bool
    {
        $value = self::toLatinDigits(\trim($value));
        $value = \str_replace(['-', ' '], '', $value);

        if (\preg_match('/^[13456789]\d{9}$/', $value) !== 1) {
            return false;
        }

        // ارقام یکسان → نامعتبر
        return \preg_match('/^(\d)\1{9}$/', $value) !== 1;
    }

    /** شمارهٔ شبا: IR + ۲۴ رقم با checksum ISO 7064 mod-97. */
    public static function sheba(string $value): bool
    {
        $value = \strtoupper(self::toLatinDigits(\trim($value)));
        $value = \str_replace([' ', '-'], '', $value);

        if (\preg_match('/^IR\d{24}$/', $value) !== 1) {
            return false;
        }

        // جابه‌جایی: BBAN + 'IR'(→1827) + check digits
        $rearranged = \substr($value, 4) . '1827' . \substr($value, 2, 2);

        return self::mod97($rearranged) === 1;
    }

    /** mod-97 روی رشتهٔ رقمی بزرگ (بدون bcmath). */
    private static function mod97(string $digits): int
    {
        $remainder = 0;
        $length = \strlen($digits);
        for ($i = 0; $i < $length; $i++) {
            $remainder = ($remainder * 10 + (int) $digits[$i]) % 97;
        }

        return $remainder;
    }

    /** شمارهٔ کارت ۱۶ رقمی با Luhn. */
    public static function cardNumber(string $value): bool
    {
        $value = self::toLatinDigits(\trim($value));
        $value = \str_replace(['-', ' '], '', $value);

        if (\preg_match('/^\d{16}$/', $value) !== 1) {
            return false;
        }
        if (\preg_match('/^(\d)\1{15}$/', $value) === 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 16; $i++) {
            $digit = (int) $value[$i];
            $weight = ($i % 2 === 0) ? 2 : 1;
            $product = $digit * $weight;
            $sum += $product > 9 ? $product - 9 : $product;
        }

        return $sum % 10 === 0;
    }

    /** شناسهٔ ملی شخص حقوقی (۱۱ رقم). */
    public static function companyId(string $value): bool
    {
        $value = self::toLatinDigits(\trim($value));

        if (\preg_match('/^\d{11}$/', $value) !== 1) {
            return false;
        }
        if (\preg_match('/^(\d)\1{10}$/', $value) === 1) {
            return false;
        }

        $control = (int) $value[10];
        $offset = (int) $value[9] + 2;
        $weights = [29, 27, 23, 19, 17, 29, 27, 23, 19, 17];

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $value[$i] + $offset) * $weights[$i];
        }

        $remainder = $sum % 11;
        $expected = $remainder < 2 ? $remainder : $remainder - 2;

        return $control === $expected;
    }

    /** تاریخ جلالی معتبر (Y/m/d یا Y-m-d — با day-of-month دقیق). */
    public static function jalaliDate(string $value, string $format = 'Y/m/d'): bool
    {
        $value = self::toLatinDigits(\trim($value));
        $separator = $format === 'Y-m-d' ? '-' : '/';

        $parts = \explode($separator, $value);
        if (\count($parts) !== 3) {
            return false;
        }

        foreach ($parts as $part) {
            if (\preg_match('/^\d+$/', $part) !== 1) {
                return false;
            }
        }

        [$year, $month, $day] = [(int) $parts[0], (int) $parts[1], (int) $parts[2]];

        if ($year < 1 || $year > 3000 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        $daysInMonth = $month <= 6 ? 31 : ($month <= 11 ? 30 : (self::isJalaliLeap($year) ? 30 : 29));

        return $day <= $daysInMonth;
    }

    /** کبیسهٔ جلالی (breaks-based — هم‌راستا با موتور jalali). */
    public static function isJalaliLeap(int $year): bool
    {
        $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
            1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
        $jp = $breaks[0];
        $jump = 0;
        foreach ($breaks as $i => $break) {
            if ($i === 0) {
                continue;
            }
            $jm = $break;
            $jump = $jm - $jp;
            if ($year < $jm) {
                break;
            }
            $jp = $jm;
        }
        $n = $year - $jp;

        if ($jump - $n < 6) {
            $n = $n - $jump + (int) (($jump + 4) / 33) * 33;
        }
        $leap = ((($n + 1) % 33 - 1) % 4);
        if ($leap === -1) {
            $leap = 4;
        }

        return $leap === 0;
    }

    /** ارقام فارسی: همهٔ رقم‌های رشته فارسی (۰-۹) باشند و حداقل یک رقم وجود داشته باشد. */
    public static function persianDigits(string $value): bool
    {
        $value = \trim($value);
        $hasAnyDigit = \preg_match('/[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $value) === 1;
        if (!$hasAnyDigit) {
            return false;
        }

        // بدون رقم لاتین یا عربی-هندی
        return \preg_match('/[0-9\x{0660}-\x{0669}]/u', $value) !== 1;
    }

    /** URL امن (SSRF-lite): http(s) + میزبان عمومی (نه private/loopback/link-local). */
    public static function safeUrl(string $url): bool
    {
        $parts = \parse_url(\trim($url));
        if ($parts === false) {
            return false;
        }

        $scheme = \strtolower((string) ($parts['scheme'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return false;
        }

        // IP لیترال
        if (\filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return !self::isPrivateIp($host);
        }

        // نام دامنه → resolve و بررسی همهٔ IPها
        $ips = self::resolveHost($host);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public static function resolveHost(string $host): array
    {
        $ips = [];

        $v4 = \gethostbynamel($host);
        if (\is_array($v4)) {
            $ips = \array_merge($ips, $v4);
        }

        $records = @\dns_get_record($host, DNS_AAAA);
        if (\is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        return $ips;
    }

    public static function isPrivateIp(string $ip): bool
    {
        if (\filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;   // نامعتبر → مسدود
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return \filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }

    /** یکسان‌سازی ارقام عربی-هندی/فارسی → لاتین. */
    public static function toLatinDigits(string $value): string
    {
        return \strtr(
            $value,
            [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ],
        );
    }
}
