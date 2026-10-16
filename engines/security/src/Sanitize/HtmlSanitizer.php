<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Sanitize;

/**
 * تَوان | Tavaan — Security
 * HtmlSanitizer — پاک‌سازی HTML با allowlist (پارسر خالص — DOC-059 §2.4).
 *
 * الگوریتم: پارس توکنی → حذف گره‌های غیرمجاز (script/style/iframe با محتوا)
 * → فیلتر attributeها (حذف on* و javascript: URI) → escape مقدارها → بازسازی.
 *
 * 🔒 متن خروجی همیشه escaped-safe است (stray `<` و `&`).
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class HtmlSanitizer
{
    /** تگ‌هایی که در صورت remove_scripts با «محتوا» حذف می‌شوند */
    private const DANGEROUS_CONTENT_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'applet', 'noscript', 'template', 'svg', 'math'];

    /** عناصر void (بدون closing tag) */
    private const VOID_TAGS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /** attributeهایی که URI دارند (کنترل پروتکل) */
    private const URI_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'xlink:href', 'data', 'poster', 'background'];

    /**
     * @param array<string, mixed> $config allowed_tags/allowed_attributes/allowed_protocols/strip_comments/remove_scripts/escape_all
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function sanitize(string $html): string
    {
        if ((bool) ($this->config['escape_all'] ?? false)) {
            return \htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        $allowedTags = \array_map('strtolower', (array) ($this->config['allowed_tags'] ?? []));
        $removeScripts = (bool) ($this->config['remove_scripts'] ?? true);
        $stripComments = (bool) ($this->config['strip_comments'] ?? true);

        $out = '';
        $length = \strlen($html);
        $pos = 0;

        while ($pos < $length) {
            $lt = \strpos($html, '<', $pos);

            if ($lt === false) {
                $out .= $this->escapeText(\substr($html, $pos));
                break;
            }

            // متن قبل از '<'
            if ($lt > $pos) {
                $out .= $this->escapeText(\substr($html, $pos, $lt - $pos));
            }

            // comment
            if (\substr($html, $lt, 4) === '<!--') {
                $end = \strpos($html, '-->', $lt + 4);
                $pos = $end === false ? $length : $end + 3;
                if (!$stripComments) {
                    $out .= '<!-- -->';   // حتی در حالت keep، محتوا حذف شود (conditional comment attack)
                }
                continue;
            }

            // doctype / CDATA / processing instruction → حذف
            if (\preg_match('/^<(?:!|\?)/', \substr($html, $lt, 2)) === 1) {
                $end = \strpos($html, '>', $lt);
                $pos = $end === false ? $length : $end + 1;
                continue;
            }

            // تگ بسته
            if (\preg_match('/^<\/\s*([A-Za-z][A-Za-z0-9-]*)\s*>?/', \substr($html, $lt), $m) === 1) {
                $name = \strtolower($m[1]);
                $pos = $lt + \strlen($m[0]);
                if ($pos < $length && ($html[$pos - 1] ?? '') !== '>') {
                    $gt = \strpos($html, '>', $lt);
                    $pos = $gt === false ? $length : $gt + 1;
                }
                if (\in_array($name, $allowedTags, true) && !\in_array($name, self::VOID_TAGS, true)) {
                    $out .= '</' . $name . '>';
                }
                continue;
            }

            // تگ باز
            $tagEnd = $this->findTagEnd($html, $lt);
            if ($tagEnd === null) {
                // '<' تنها → escape
                $out .= '&lt;';
                $pos = $lt + 1;
                continue;
            }

            $tagSource = \substr($html, $lt, $tagEnd - $lt + 1);
            $pos = $tagEnd + 1;

            if (\preg_match('/^<\s*([A-Za-z][A-Za-z0-9-]*)/', $tagSource, $m) !== 1) {
                $out .= '&lt;';
                $pos = $lt + 1;
                continue;
            }

            $name = \strtolower($m[1]);

            // 🔒 حذف تگ خطرناک با کل محتوا
            if ($removeScripts && \in_array($name, self::DANGEROUS_CONTENT_TAGS, true)) {
                $pos = $this->skipContent($html, $pos, $name);
                continue;
            }

            if (!\in_array($name, $allowedTags, true)) {
                continue;   // تگ حذف، محتوا نگه داشته می‌شود
            }

            $out .= $this->renderTag($name, $tagSource);
        }

        return $out;
    }

    /** انتهای تگ (با احترام به '>' درون رشتهٔ attribute). */
    private function findTagEnd(string $html, int $start): ?int
    {
        $length = \strlen($html);
        $quote = null;

        for ($i = $start + 1; $i < $length; $i++) {
            $char = $html[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return $i;
            }
        }

        return null;
    }

    /** رد شدن از محتوای تگ خطرناک تا </name>. */
    private function skipContent(string $html, int $pos, string $name): int
    {
        $pattern = '/<\\/' . \preg_quote($name, '/') . '\\s*>/i';
        if (\preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE, $pos) === 1) {
            return (int) $m[0][1] + \strlen($m[0][0]);
        }

        return \strlen($html);   // بدون close → بقیه حذف
    }

    /** ساخت تگ امن با attributeهای فیلترشده. */
    private function renderTag(string $name, string $source): string
    {
        /** @var array<string, list<string>> $allowedAttributes */
        $allowedAttributes = (array) ($this->config['allowed_attributes'] ?? []);
        /** @var list<string> $allowedProtocols */
        $allowedProtocols = \array_map('strtolower', (array) ($this->config['allowed_protocols'] ?? ['http', 'https', 'mailto']));

        $tagAllowed = \array_map('strtolower', $allowedAttributes[$name] ?? $allowedAttributes['*'] ?? []);

        $attributes = $this->parseAttributes($source);
        $rendered = '';

        foreach ($attributes as $attrName => $attrValue) {
            $attrName = \strtolower($attrName);

            // 🔒 on* event handlers همیشه حذف
            if (\str_starts_with($attrName, 'on')) {
                continue;
            }
            if (!\in_array($attrName, $tagAllowed, true)) {
                continue;
            }

            // 🔒 کنترل پروتکل URI
            if (\in_array($attrName, self::URI_ATTRIBUTES, true)) {
                if (!$this->protocolAllowed($attrValue, $allowedProtocols)) {
                    continue;
                }
            }

            $rendered .= ' ' . $attrName . '="' . \htmlspecialchars($attrValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
        }

        if (\in_array($name, self::VOID_TAGS, true)) {
            return '<' . $name . $rendered . '>';
        }

        return '<' . $name . $rendered . '>';
    }

    /** @return array<string, string> */
    private function parseAttributes(string $tagSource): array
    {
        // حذف نام تگ و '>' انتهایی
        $body = \preg_replace('/^<\s*[A-Za-z][A-Za-z0-9-]*/', '', $tagSource) ?? '';
        $body = \rtrim($body, '>');
        $body = \rtrim($body);
        if (\str_ends_with($body, '/')) {
            $body = \rtrim(\substr($body, 0, -1));
        }

        $attributes = [];
        $pattern = '/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*(?:=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/';

        if (\preg_match_all($pattern, $body, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $name = $match[1];
                $value = $match[2] ?? $match[3] ?? $match[4] ?? '';
                $attributes[\strtolower($name)] = $this->decodeEntities($value);
            }
        }

        return $attributes;
    }

    private function decodeEntities(string $value): string
    {
        // 🔒 entity-decode (java&#115;cript: → javascript: — سپس در protocolAllowed بررسی می‌شود)
        $decoded = \html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // فقط null byte حذف شود (فاصله‌های مشروع مقدار حفظ می‌شوند)
        return \str_replace("\0", '', $decoded);
    }

    /** @param list<string> $allowedProtocols */
    private function protocolAllowed(string $value, array $allowedProtocols): bool
    {
        // 🔒 بررسی روی نسخهٔ تهاجمی‌زدایی‌شده (حذف control/whitespace درون scheme — java\tscript:)
        $stripped = (string) \preg_replace('/[\x00-\x20\x7F]+/', '', $value);
        if (\preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $stripped, $sm) === 1
            && !\in_array(\strtolower($sm[1]), $allowedProtocols, true)
            && !\str_starts_with(\strtolower($stripped), 'data:image/')) {
            return false;
        }

        $trimmed = \trim($value);

        // نسبی یا fragment → مجاز
        if ($trimmed === '' || \str_starts_with($trimmed, '/') || \str_starts_with($trimmed, '#') || \str_starts_with($trimmed, '?')) {
            return true;
        }

        // scheme دارد؟
        if (\preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $trimmed, $m) === 1) {
            return \in_array(\strtolower($m[1]), $allowedProtocols, true);
        }

        // data: برای img (اختیاری و فقط image/*)
        if (\preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $trimmed) === 1) {
            return \in_array('data', $allowedProtocols, true);
        }

        return true;   // بدون scheme (نسبی)
    }

    /** escape متن: stray `<` و `&` (entityهای معتبر حفظ می‌شوند). */
    private function escapeText(string $text): string
    {
        // & معتبر (entity) حفظ، بقیه escape
        $text = (string) \preg_replace('/&(?!(?:#[0-9]+|#x[0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]{1,31});)/', '&amp;', $text);

        // '<' تنها (تگ ناقص) → escape
        return \str_replace('<', '&lt;', $text);
    }
}
