<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Support;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Engine\Admin\Form\Field;
use Tavaan\Engine\I18n\Jalali\Jalali;
use Tavaan\Engine\Security\Sanitize\HtmlSanitizer;

/**
 * تَوان | Tavaan — Admin
 * ValueTransformer — تبدیل دوطرفهٔ مقدار فیلد (فرم ↔ ذخیره‌گاه).
 *
 * - `jalali_date`: UI جلالی ↔ DB میلادی UTC (DOC-078 §6.1)
 * - `password`: hash (و در update مقدار خالی = تغییر نکرد)
 * - `richtext`: Sanitizer با پروفایل config (XSS — §7)
 * - `slug`: ساخت خودکار از فیلد مبدأ (`->from('title')`)
 * - `json`/`tags`/`switch`/`checkbox`/`money`: نرمال‌سازی ورودی
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class ValueTransformer
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * ورودی فرم → مقدار قابل‌ذخیره.
     *
     * @param array<string, mixed> $all کل دادهٔ فرم (برای slug-from)
     *
     * @return array{value: mixed, skip: bool} skip=true → فیلد ذخیره نشود
     */
    public function toStorage(Field $field, mixed $value, array $all): array
    {
        switch ($field->type) {
            case 'password':
                if (!\is_string($value) || $value === '') {
                    return ['value' => null, 'skip' => true];   // تغییر نکرد
                }

                return ['value' => $this->hash($value), 'skip' => false];

            case 'richtext':
                return ['value' => \is_string($value) ? $this->sanitize($value, $field->sanitizerProfile() ?? 'rich_text') : $value, 'skip' => false];

            case 'jalali_date':
                if (!\is_string($value) || \trim($value) === '') {
                    return ['value' => null, 'skip' => false];
                }

                return ['value' => self::jalaliToGregorian($value), 'skip' => false];

            case 'switch':
            case 'checkbox':
                return ['value' => \in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true), 'skip' => false];

            case 'json':
                if (\is_string($value) && $value !== '') {
                    $decoded = \json_decode($value, true);
                    $value = \json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                }

                return ['value' => $value, 'skip' => false];

            case 'tags':
                if (\is_string($value)) {
                    $value = \array_values(\array_filter(\array_map('trim', \explode(',', $value)), static fn (string $t): bool => $t !== ''));
                }

                return ['value' => $value, 'skip' => false];

            case 'slug':
                if ((!\is_string($value) || \trim($value) === '') && $field->slugFrom() !== null) {
                    $source = $all[$field->slugFrom()] ?? '';
                    $value = \is_string($source) ? self::slugify($source) : '';
                }

                return ['value' => \is_string($value) ? self::slugify($value) : $value, 'skip' => false];

            case 'money':
                if (\is_string($value)) {
                    $cleaned = \str_replace([',', ' ', "\u{200C}"], '', $value);
                    if (\is_numeric($cleaned)) {
                        $value = $cleaned + 0;
                    }
                }

                return ['value' => $value, 'skip' => false];

            case 'number':
                if (\is_string($value) && \is_numeric($value)) {
                    $value = $value + 0;
                }

                return ['value' => $value, 'skip' => false];

            default:
                return ['value' => $value, 'skip' => false];
        }
    }

    /**
     * مقدار ذخیره‌شده → نمایش (فرم/فهرست/جزئیات).
     */
    public function toDisplay(string $type, mixed $value, string $jalaliFormat = 'Y/m/d'): mixed
    {
        if ($type === 'jalali_date' && \is_string($value) && $value !== '') {
            return self::gregorianToJalali($value, $jalaliFormat);
        }
        if ($type === 'json' && !\is_string($value) && $value !== null) {
            $encoded = \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            return $encoded === false ? $value : $encoded;
        }
        if ($type === 'tags' && \is_array($value)) {
            return \implode(', ', \array_map('strval', $value));
        }
        if (\is_bool($value)) {
            return $value ? '✓' : '—';
        }

        return $value;
    }

    // ─── تبدیلگرهای ایستا ─────────────────────────────────────────────────

    /** `1403/04/12` (یا با ساعت) → `2024-07-02` میلادی UTC. */
    public static function jalaliToGregorian(string $jalali): ?string
    {
        $jalali = \trim($jalali);
        $timePart = '';
        if (\preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}:\d{2}(?::\d{2})?))?$/', $jalali, $m) !== 1) {
            return null;
        }
        if (isset($m[4]) && $m[4] !== '') {
            $timePart = ' ' . (\strlen($m[4]) === 5 ? $m[4] . ':00' : $m[4]);
        }

        [$gy, $gm, $gd] = Jalali::jalaliToGregorian((int) $m[1], (int) $m[2], (int) $m[3]);

        return \sprintf('%04d-%02d-%02d', $gy, $gm, $gd) . $timePart;
    }

    /** میلادی → جلالی با فرمت داده‌شده. */
    public static function gregorianToJalali(string $gregorian, string $format = 'Y/m/d'): string
    {
        $timestamp = \strtotime($gregorian);
        if ($timestamp === false) {
            return $gregorian;
        }

        [$jy, $jm, $jd] = Jalali::toJalali((int) \date('Y', $timestamp), (int) \date('n', $timestamp), (int) \date('j', $timestamp));

        return \str_replace(
            ['Y', 'm', 'd'],
            [\sprintf('%04d', $jy), \sprintf('%02d', $jm), \sprintf('%02d', $jd)],
            $format,
        );
    }

    /** slug یونیکد-امن (حروف فارسی مجاز — مانند الگوی روت‌های پروژه). */
    public static function slugify(string $value): string
    {
        $value = \mb_strtolower(\trim($value));
        $value = (string) \preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);

        return \trim($value, '-');
    }

    private function hash(string $value): string
    {
        if ($this->container->bound(HasherInterface::class)) {
            $hasher = $this->container->make(HasherInterface::class);
            if ($hasher instanceof HasherInterface) {
                return $hasher->make($value);
            }
        }

        return (string) \password_hash($value, PASSWORD_ARGON2ID);
    }

    private function sanitize(string $html, string $profile): string
    {
        $profiles = [];
        if ($this->container->bound('config')) {
            $profiles = (array) ($this->container->make('config')->get('security.sanitizers', []) ?? []);
        }
        $config = (array) ($profiles[$profile] ?? ($profiles['rich_text'] ?? []));

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
