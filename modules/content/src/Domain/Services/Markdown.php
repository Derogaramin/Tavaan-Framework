<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * Markdown — تبدیل زیرمجموعهٔ Markdown به HTML با طراحی escape-first.
 */

namespace Modules\Content\Domain\Services;

/**
 * Markdown — مبدل امن زیرمجموعهٔ CommonMark (DOC-091 §6: ویرایشگر Markdown با Sanitizer).
 *
 * 🔒 امنیت: کل ورودی پیش از هر تبدیل دیگری htmlspecialchars می‌شود؛ بنابراین
 * تزریق HTML/Attribute از مبدأ ممکن نیست (AC-BLOG-03). افزون بر این، لینک‌ها/
 * تصاویر فقط با scheme مجاز (http/https/mailto/tel یا نسبی) رندر می‌شوند.
 *
 * پشتیبانی: heading، bold، italic، strikethrough، inline code، fenced code،
 * لیست ترتیبی/غیرترتیبی، blockquote، خط جدا، لینک، تصویر، پاراگراف.
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class Markdown
{
    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function __construct(private readonly bool $allowImages = true)
    {
    }

    /** Markdown → HTML امن. */
    public function toHtml(string $text): string
    {
        $text = \str_replace(["\r\n", "\r"], "\n", $text);

        // ۱) بلوک‌های کد fenced را جدا و محافظت کن (محتوا escaped)
        $fences = [];
        $text = \preg_replace_callback(
            '/^```([a-zA-Z0-9_-]*)\n(.*?)^```\s*$/ms',
            static function (array $m) use (&$fences): string {
                $lang = $m[1] !== '' ? ' class="language-' . self::escape($m[1]) . '"' : '';
                $fences[] = '<pre><code' . $lang . '>' . self::escape(\rtrim($m[2], "\n")) . '</code></pre>';

                return "\x01FENCE" . (\count($fences) - 1) . "\x01";
            },
            $text,
        ) ?? $text;

        // ۲) escape کل باقی‌مانده (escape-first)
        $text = self::escape($text);

        // ۳) inline code را محافظت کن (پس از escape)
        $codes = [];
        $text = \preg_replace_callback(
            '/`([^`\n]+)`/',
            static function (array $m) use (&$codes): string {
                $codes[] = '<code>' . $m[1] . '</code>';

                return "\x01CODE" . (\count($codes) - 1) . "\x01";
            },
            $text,
        ) ?? $text;

        // ۴) ساختارهای بلوکی (خط‌محور)
        $html = $this->blocks($text);

        // ۵) بازگردانی محافظ‌ها
        $html = \preg_replace_callback(
            '/\x01CODE(\d+)\x01/',
            static fn (array $m): string => $codes[(int) $m[1]] ?? '',
            $html,
        ) ?? $html;
        $html = \preg_replace_callback(
            '/\x01FENCE(\d+)\x01/',
            static fn (array $m): string => $fences[(int) $m[1]] ?? '',
            $html,
        ) ?? $html;

        return \trim($html);
    }

    /** پردازش بلوک‌ها: heading/list/quote/hr/paragraph. */
    private function blocks(string $text): string
    {
        $inlineFn = fn (string $t): string => $this->inline($t);
        $lines = \explode("\n", $text);
        $out = '';
        $paragraph = [];
        $listType = null;      // 'ul' | 'ol' | null
        $quote = [];

        $flushParagraph = static function () use (&$paragraph, &$out, $inlineFn): void {
            if ($paragraph !== []) {
                $inline = $inlineFn(\implode(' ', $paragraph));
                $out .= '<p>' . $inline . '</p>' . "\n";
                $paragraph = [];
            }
        };
        $flushList = static function () use (&$listType, &$out): void {
            if ($listType !== null) {
                $out .= '</' . $listType . '>' . "\n";
                $listType = null;
            }
        };
        $flushQuote = static function () use (&$quote, &$out, $inlineFn): void {
            if ($quote !== []) {
                $out .= '<blockquote><p>' . $inlineFn(\implode(' ', $quote)) . '</p></blockquote>' . "\n";
                $quote = [];
            }
        };

        foreach ($lines as $line) {
            $trim = \trim($line);

            if ($trim === '') {
                $flushParagraph();
                $flushList();
                $flushQuote();
                continue;
            }

            // بلوک کد fenced (placeholder خام — بدون <p>)
            if (\preg_match('/^\x01FENCE\d+\x01$/', $trim) === 1) {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $out .= $trim . "\n";
                continue;
            }

            // خط جدا (--- *** ___)
            if (\preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trim) === 1) {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $out .= '<hr />' . "\n";
                continue;
            }

            // heading
            if (\preg_match('/^(#{1,6})\s+(.*)$/', $trim, $m) === 1) {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $level = \strlen($m[1]);
                $out .= '<h' . $level . '>' . $inlineFn($m[2]) . '</h' . $level . '>' . "\n";
                continue;
            }

            // blockquote
            if (\str_starts_with($trim, '&gt;')) {
                $flushParagraph();
                $flushList();
                $quote[] = \trim(\substr($trim, 4));
                continue;
            }
            $flushQuote();

            // لیست غیرترتیبی
            if (\preg_match('/^[-*+]\s+(.*)$/', $trim, $m) === 1) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $flushList();
                    $out .= '<ul>' . "\n";
                    $listType = 'ul';
                }
                $out .= '<li>' . $inlineFn($m[1]) . '</li>' . "\n";
                continue;
            }

            // لیست ترتیبی
            if (\preg_match('/^\d+[.)]\s+(.*)$/', $trim, $m) === 1) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $flushList();
                    $out .= '<ol>' . "\n";
                    $listType = 'ol';
                }
                $out .= '<li>' . $inlineFn($m[1]) . '</li>' . "\n";
                continue;
            }

            $flushList();
            $paragraph[] = $trim;
        }

        $flushParagraph();
        $flushList();
        $flushQuote();

        return $out;
    }

    /** پردازش inline: تصویر، لینک، bold، italic، strike. (ورودی از قبل escaped) */
    public function inline(string $text): string
    {
        // تصویر: ![alt](url) — فقط در صورت allowImages
        if ($this->allowImages) {
            $text = \preg_replace_callback(
                '/!\[([^\]]*)\]\(([^)\s]+)\)/',
                static function (array $m): string {
                    $url = self::safeUrl($m[2]);

                    return $url === null
                        ? $m[1]
                        : '<img src="' . $url . '" alt="' . $m[1] . '" loading="lazy" />';
                },
                $text,
            ) ?? $text;
        } else {
            // حذف سینتکس تصویر، نمایش alt
            $text = \preg_replace('/!\[([^\]]*)\]\([^)\s]+\)/', '$1', $text) ?? $text;
        }

        // لینک: [text](url)
        $text = \preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            static function (array $m): string {
                $url = self::safeUrl($m[2]);

                return $url === null
                    ? $m[1]
                    : '<a href="' . $url . '" rel="nofollow noopener">' . $m[1] . '</a>';
            },
            $text,
        ) ?? $text;

        // bold: **x** یا __x__
        $text = \preg_replace('/\*\*([^\n*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = \preg_replace('/__([^\n_]+)__/', '<strong>$1</strong>', $text) ?? $text;

        // italic: *x* یا _x_ (با مرز کلمه برای _)
        $text = \preg_replace('/\*([^\n*]+)\*/', '<em>$1</em>', $text) ?? $text;
        $text = \preg_replace('/(?<![\p{L}\p{N}])_([^\n_]+)_(?![\p{L}\p{N}])/u', '<em>$1</em>', $text) ?? $text;

        // strikethrough: ~~x~~
        $text = \preg_replace('/~~([^\n~]+)~~/', '<del>$1</del>', $text) ?? $text;

        return $text;
    }

    /**
     * بررسی امنیت URL: فقط scheme مجاز یا مسیر نسبی.
     *
     * ورودی از قبل escaped است (&amp; و …) — برای بررسی scheme ابتدا decode می‌شود.
     */
    private static function safeUrl(string $escapedUrl): ?string
    {
        $url = \html_entity_decode($escapedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = \trim($url);

        if ($url === '' || \str_contains($url, "\n") || \str_contains($url, ' ')) {
            return null;
        }

        // نسبی: / x، #anchor، ./path — بدون scheme
        if (\preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url) === 1) {
            $scheme = \strtolower((string) \substr($url, 0, (int) \strpos($url, ':')));
            if (!\in_array($scheme, self::ALLOWED_SCHEMES, true)) {
                return null;   // javascript:، data: و … → حذف لینک، نمایش متن
            }
        }

        return $escapedUrl;
    }

    private static function escape(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
