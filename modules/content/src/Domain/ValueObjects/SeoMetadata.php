<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * SeoMetadata — VO متادیتای سئو (meta + Open Graph) — DOC-091 §6.
 */

namespace Modules\Content\Domain\ValueObjects;

/**
 * SeoMetadata — تغییرناپذیر، با سقف طول استاندارد (title ≤ 60، description ≤ 160).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class SeoMetadata
{
    public function __construct(
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly string $keywords = '',
        public readonly string $canonical = '',
        public readonly string $ogType = 'article',
        public readonly string $ogImage = '',
    ) {
    }

    /**
     * ساخت با پرکردن خودکار از عنوان/بدنه در نبود مقادیر صریح.
     *
     * @param string $title       عنوان محتوا
     * @param string $plainBody   متن بدون HTML (برای description)
     * @param string $description توضیح صریح (اولویت دارد)
     */
    public static function make(
        string $title,
        string $plainBody = '',
        string $description = '',
        string $keywords = '',
        string $canonical = '',
        string $ogType = 'article',
        string $ogImage = '',
    ): self {
        $description = \trim($description) !== '' ? $description : self::truncate($plainBody, 160);

        return new self(
            title: self::truncate($title, 60),
            description: $description,
            keywords: $keywords,
            canonical: $canonical,
            ogType: $ogType,
            ogImage: $ogImage,
        );
    }

    /**
     * تگ‌های meta برای رندر در View.
     *
     * @return array<string, string> name → content
     */
    public function metaTags(): array
    {
        $tags = [];
        if ($this->title !== '') {
            $tags['title'] = $this->title;
        }
        if ($this->description !== '') {
            $tags['description'] = $this->description;
        }
        if ($this->keywords !== '') {
            $tags['keywords'] = $this->keywords;
        }

        return $tags;
    }

    /**
     * تگ‌های Open Graph.
     *
     * @return array<string, string> property → content
     */
    public function openGraph(): array
    {
        $og = [];
        if ($this->title !== '') {
            $og['og:title'] = $this->title;
        }
        if ($this->description !== '') {
            $og['og:description'] = $this->description;
        }
        $og['og:type'] = $this->ogType;
        if ($this->canonical !== '') {
            $og['og:url'] = $this->canonical;
        }
        if ($this->ogImage !== '') {
            $og['og:image'] = $this->ogImage;
        }

        return $og;
    }

    public function isEmpty(): bool
    {
        return $this->title === '' && $this->description === '' && $this->keywords === '';
    }

    /** کوتاه‌سازی mb-امن در مرز کلمه + «…». */
    public static function truncate(string $text, int $max): string
    {
        $text = \trim((string) \preg_replace('/\s+/u', ' ', $text));
        if (\mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = \mb_substr($text, 0, $max);
        $lastSpace = \mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $max * 0.6) {
            $cut = \mb_substr($cut, 0, $lastSpace);
        }

        return \rtrim($cut, ' .،,;:') . '…';
    }
}
