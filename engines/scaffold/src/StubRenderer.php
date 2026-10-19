<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold;

/**
 * تَوان | Tavaan — Scaffold
 * StubRenderer — موتور قالب خودنوشته (بدون Twig/Handlebars — DOC-077 §3).
 *
 * سه سازوکار:
 * - `{{ var }}`               جایگزینی متغیر (ناشناخته‌ها دست‌نخورده می‌مانند)
 * - `{{#if flag}} … {{/if}}`  بلوک شرطی (+ `{{#unless flag}} … {{/unless}}`)
 * - `{{#each items}} … {{/each}}` حلقه روی list (دسترسی `{{this}}` یا کلیدهای آرایه)
 *
 * 🔒 نشانگرهای بلوک باید تنها در خط خود باشند (خط نشانگر کامل مصرف می‌شود تا
 * خط خالی باقی نماند). تودرتویی انواع متفاوت مجاز است (if داخل each) اما
 * تودرتویی دو بلوک هم‌نوع (if داخل if) پشتیبانی نمی‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class StubRenderer
{
    public function __construct(private readonly string $stubsPath)
    {
    }

    /**
     * رندر یک فایل stub.
     *
     * @param array<string, mixed> $vars
     *
     * @throws ScaffoldException اگر stub وجود نداشته باشد
     */
    public function render(string $stubName, array $vars): string
    {
        $path = $this->path($stubName);
        if (!\is_file($path)) {
            throw ScaffoldException::stubMissing($stubName);
        }
        $content = \file_get_contents($path);
        if ($content === false) {
            throw ScaffoldException::stubMissing($stubName);
        }

        return $this->renderString($content, $vars);
    }

    /**
     * رندر رشتهٔ قالب (برای تست و قالب‌های درون‌کدی).
     *
     * @param array<string, mixed> $vars
     */
    public function renderString(string $content, array $vars): string
    {
        return $this->replaceVars($this->renderBlocks($content, $vars), $vars);
    }

    /** مسیر مطلق یک stub (از sandbox قالب‌ها خارج نمی‌شود). */
    public function path(string $stubName): string
    {
        $name = \str_replace(['\\', '..'], ['', ''], $stubName);
        if (!\str_ends_with($name, '.stub')) {
            $name .= '.stub';
        }

        return \rtrim($this->stubsPath, '/\\') . '/' . \ltrim($name, '/\\');
    }

    /**
     * پردازش بلوک‌های if/unless/each (بازگشتی برای تودرتویی).
     *
     * @param array<string, mixed> $vars
     */
    private function renderBlocks(string $content, array $vars): string
    {
        $pattern = '/^[ \t]*\{\{#(if|unless|each)[ \t]+([A-Za-z_][A-Za-z0-9_]*)[ \t]*\}\}[ \t]*\r?\n(.*?)^[ \t]*\{\{\/\1\}\}[ \t]*\r?\n?/sm';

        $previous = null;
        $current = $content;

        // تکرار تا نقطهٔ ثابت: بلوک‌های هم‌سطح پشت‌سرهم و تودرتو پوشش داده می‌شوند
        while ($current !== $previous) {
            $previous = $current;
            $current = (string) \preg_replace_callback(
                $pattern,
                fn (array $m): string => $this->renderBlock($m[1], $m[2], $m[3], $vars),
                $current,
            );
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderBlock(string $type, string $key, string $body, array $vars): string
    {
        $value = $vars[$key] ?? null;

        return match ($type) {
            'if' => $this->truthy($value) ? $this->renderBlocks($body, $vars) : '',
            'unless' => $this->truthy($value) ? '' : $this->renderBlocks($body, $vars),
            'each' => $this->renderEach($key, $body, $value, $vars),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderEach(string $key, string $body, mixed $items, array $vars): string
    {
        if (!\is_array($items) || $items === []) {
            return '';
        }

        $out = '';
        $index = 0;
        foreach ($items as $itemKey => $item) {
            $scope = $vars;
            if (\is_array($item)) {
                foreach ($item as $k => $v) {
                    if (\is_string($k)) {
                        $scope[$k] = $v;
                    }
                }
            }
            $scope['this'] = $item;
            $scope[$key] = $item;
            $scope['index'] = $index;
            $scope['key'] = (string) $itemKey;

            $out .= $this->replaceVars($this->renderBlocks($body, $scope), $scope);
            $index++;
        }

        return $out;
    }

    /**
     * جایگزینی `{{ var }}` — کلید ناشناخته دست‌نخورده می‌ماند (DOC-077 §3).
     *
     * @param array<string, mixed> $vars
     */
    private function replaceVars(string $content, array $vars): string
    {
        return (string) \preg_replace_callback(
            '/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/',
            static function (array $m) use ($vars): string {
                $key = $m[1];
                if (!\array_key_exists($key, $vars)) {
                    return $m[0];
                }

                return self::stringify($vars[$key]);
            },
            $content,
        );
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            \is_string($value) => $value,
            \is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            \is_array($value) => self::exportArray($value),
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * تبدیل آرایه به لیترال PHP (برای `{{fillable}}` و مانند آن).
     *
     * @param array<array-key, mixed> $value
     */
    private static function exportArray(array $value): string
    {
        if ($value === []) {
            return '[]';
        }

        $isList = \array_is_list($value);
        $parts = [];
        foreach ($value as $k => $v) {
            $rendered = \is_array($v) ? self::exportArray($v) : \var_export($v, true);
            $parts[] = $isList ? $rendered : \var_export((string) $k, true) . ' => ' . $rendered;
        }

        return '[' . \implode(', ', $parts) . ']';
    }

    private function truthy(mixed $value): bool
    {
        if (\is_string($value)) {
            return $value !== '' && $value !== '0' && \strtolower($value) !== 'false';
        }

        return (bool) $value;
    }
}
