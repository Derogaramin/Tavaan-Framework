<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * Slug — Value Object شناسهٔ یونیکد-امن URL (فارسی-اول).
 */

namespace Modules\Content\Domain\ValueObjects;

use Tavaan\Core\Exceptions\Validation\ValidationException;

/**
 * Slug — VO تغییرناپذیر با نرمال‌سازی فارسی (ی/ک، ارقام، نیم‌فاصله).
 *
 * الگوریتم هم‌راستا با slugify موتور Admin (ValueTransformer) با افزودنی‌های
 * فارسی: یکی‌سازی ی/ک و تبدیل ارقام فارسی/عربی به لاتین (جلوگیری از slug تکراری).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class Slug
{
    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const AR_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private function __construct(public readonly string $value)
    {
    }

    /** ساخت slug از عنوان/متن دلخواه (slugify کامل). */
    public static function of(string $title): self
    {
        $slug = self::slugify($title);
        if ($slug === '') {
            throw new ValidationException(
                errors: ['slug' => ['عنوان قابل تبدیل به slug نیست.']],
                messageId: 'content::messages.slug_empty',
                hint: 'عنوان باید حداقل یک حرف یا رقم داشته باشد.',
            );
        }

        return new self($slug);
    }

    /**
     * ساخت از رشتهٔ slug-مانند واردشده (فقط نرمال‌سازی؛ در صورت خالی‌شدن خطا).
     */
    public static function fromRaw(string $slug): self
    {
        $normalized = self::slugify($slug);
        if ($normalized === '') {
            throw new ValidationException(
                errors: ['slug' => ['slug معتبر نیست.']],
                messageId: 'content::messages.slug_empty',
            );
        }

        return new self($normalized);
    }

    /**
     * slug یکتا با پسوند عددی: `base`، `base-2`، `base-3`…
     *
     * @param callable(string): bool $exists بررسی وجود (مثلاً در مخزن)
     */
    public static function unique(string $base, callable $exists, int $maxSuffix = 1000): self
    {
        $slug = self::slugify($base);
        if ($slug === '') {
            $slug = 'item';
        }

        $candidate = $slug;
        $suffix = 2;
        while ($exists($candidate) && $suffix <= $maxSuffix) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return new self($candidate);
    }

    /**
     * slugify یونیکد-امن: حروف/ارقام (شامل فارسی) حفظ، بقیه → `-`.
     *
     * ۱) ارقام فارسی/عربی → لاتین  ۲) ی/ک عربی → فارسی  ۳) lowercase
     * ۴) حذف حرکات (اِعراب) — «تَوان» = «توان»  ۵) هر دنباله کاراکتر غیر
     * حرف/رقم (شامل نیم‌فاصله) → یک `-`  ۶) trim.
     */
    public static function slugify(string $value): string
    {
        $value = \str_replace([...self::FA_DIGITS, ...self::AR_DIGITS], self::LATIN_DIGITS, $value);
        $value = \str_replace(['ي', 'ك', 'ة', "\u{200c}", "\u{200f}", "\u{200e}"], ['ی', 'ک', 'ه', ' ', '', ''], $value);
        $value = \mb_strtolower(\trim($value));
        $value = (string) \preg_replace('/\p{M}+/u', '', $value);          // حرکات/اِعراب حذف
        $value = (string) \preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);

        return \trim($value, '-');
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
