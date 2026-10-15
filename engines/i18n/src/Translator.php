<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n;

use Tavaan\Core\Contracts\I18n\TranslatorInterface;
use Tavaan\Support\Arr;

/**
 * تَوان | Tavaan — I18n
 * Translator — ترجمه با namespace، placeholder، pluralization و fallback chain.
 *
 * کلید: `namespace::group.sub.key` یا `group.sub.key`.
 * Fallback: locale جاری → fallback_locale → خود کلید (+ log).
 *
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
final class Translator implements TranslatorInterface
{
    /** @var array<string, array<string, array<string, mixed>>> locale → namespace → lines */
    private array $loaded = [];

    /** @var array<string, list<string>> namespace → مسیرها */
    private array $namespacePaths = [];

    /** @var list<string> مسیرهای پیش‌فرض (namespace محلی = نام فایل) */
    private array $paths = [];

    private string $locale = 'fa';

    private string $fallback = 'en';

    /** @var list<string> کلیدهای گمشده (برای log در terminate) */
    private array $missing = [];

    private PluralRules $pluralizer;

    public function __construct(array $config = [])
    {
        $this->paths = (array) ($config['paths'] ?? []);
        $this->locale = (string) ($config['locale'] ?? 'fa');
        $this->fallback = (string) ($config['fallback_locale'] ?? 'en');
        foreach ((array) ($config['namespaces'] ?? []) as $ns => $path) {
            $this->namespacePaths[(string) $ns] = [(string) $path];
        }
        $this->pluralizer = new PluralRules();
    }

    // ─── API ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;

        $line = $this->find($key, $locale)
            ?? $this->find($key, $this->fallback);

        if ($line === null) {
            $this->missing[] = $key;

            return $key;
        }

        return $this->replace($line, $replace);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->find($key, $locale ?? $this->locale) !== null;
    }

    /**
     * جمع‌بندی: `{0}خالی|{1}یک|[2,*]:count قلم` یا `one|plural`.
     *
     * @param array<string, mixed> $replace
     */
    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;
        $line = $this->find($key, $locale) ?? $this->find($key, $this->fallback) ?? $key;

        $segments = \explode('|', $line);
        $chosen = $this->extractPlural($segments, $number, $locale);
        $chosen = \str_replace(':count', (string) $number, $chosen);

        return $this->replace($chosen, $replace);
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    /** افزودن namespace (توسط ماژول‌ها). */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespacePaths[$namespace][] = $path;
    }

    /** @return list<string> کلیدهای گمشدهٔ این درخواست */
    public function missingKeys(): array
    {
        return \array_values(\array_unique($this->missing));
    }

    /** RTL بودن locale جاری. */
    public function isRtl(?string $locale = null, array $rtlLocales = ['fa', 'ar', 'he', 'ur']): bool
    {
        return \in_array(\substr($locale ?? $this->locale, 0, 2), $rtlLocales, true);
    }

    /**
     * Accept-Language negotiation (DOC-069 §6).
     *
     * @param list<string> $supported
     */
    public function negotiate(string $header, array $supported, string $default): string
    {
        $prefs = [];
        foreach (\explode(',', $header) as $part) {
            [$lang, $q] = \array_pad(\explode(';q=', \trim($part)), 2, '1.0');
            $prefs[\trim($lang)] = (float) $q;
        }
        \arsort($prefs);

        foreach (\array_keys($prefs) as $lang) {
            $lang = (string) $lang;
            if (\in_array($lang, $supported, true)) {
                return $lang;
            }
            $base = \substr($lang, 0, 2);
            foreach ($supported as $s) {
                if (\str_starts_with($s, $base)) {
                    return $s;
                }
            }
        }

        return $default;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function find(string $key, string $locale): ?string
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $lines = $this->load($locale, $namespace, $group);
        $value = Arr::get($lines, $item);

        // fallback زبانی: fa_IR → fa
        if ($value === null && \str_contains($locale, '_')) {
            $lines = $this->load(\substr($locale, 0, 2), $namespace, $group);
            $value = Arr::get($lines, $item);
        }

        return \is_string($value) ? $value : null;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [namespace, group, item-path]
     */
    private function parseKey(string $key): array
    {
        if (\str_contains($key, '::')) {
            [$namespace, $rest] = \explode('::', $key, 2);
        } else {
            $namespace = '*';
            $rest = $key;
        }

        $segments = \explode('.', $rest);
        $group = \array_shift($segments) ?? '';

        return [$namespace, $group, \implode('.', $segments)];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $locale, string $namespace, string $group): array
    {
        $cacheKey = $namespace === '*' ? $locale : $locale . '|' . $namespace;
        if (isset($this->loaded[$cacheKey][$group])) {
            return $this->loaded[$cacheKey][$group];
        }

        $candidates = [];
        if ($namespace === '*') {
            foreach ($this->paths as $path) {
                $candidates[] = $path . '/' . $locale . '/' . $group . '.php';
            }
        } else {
            foreach ($this->namespacePaths[$namespace] ?? [] as $path) {
                $candidates[] = $path . '/' . $locale . '/' . $group . '.php';
            }
        }

        $lines = [];
        foreach ($candidates as $file) {
            if (\is_file($file)) {
                /** @var mixed $data */
                $data = require $file;
                if (\is_array($data)) {
                    $lines = [...$lines, ...$data];
                }
            }
        }

        return $this->loaded[$cacheKey][$group] = $lines;
    }

    /**
     * @param array<string, mixed> $replace
     */
    private function replace(string $line, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $line = \str_replace([':' . $key, ':' . \ucfirst($key)], [(string) $value, \ucfirst((string) $value)], $line);
        }

        return $line;
    }

    /**
     * @param list<string> $segments
     */
    private function extractPlural(array $segments, int $number, string $locale): string
    {
        // فرم صریح {n} / [a,b] / [a,*]
        foreach ($segments as $segment) {
            $segment = \trim($segment);
            if (\preg_match('/^\{(\d+)\}\s*(.*)$/s', $segment, $m) === 1) {
                if ((int) $m[1] === $number) {
                    return $m[2];
                }
                continue;
            }
            if (\preg_match('/^\[(\d+|\*),(\d+|\*)\]\s*(.*)$/s', $segment, $m) === 1) {
                $low = $m[1] === '*' ? PHP_INT_MIN : (int) $m[1];
                $high = $m[2] === '*' ? PHP_INT_MAX : (int) $m[2];
                if ($number >= $low && $number <= $high) {
                    return $m[3];
                }
            }
        }

        // فرم index-based
        $plain = [];
        foreach ($segments as $segment) {
            $segment = \trim($segment);
            if (!\str_starts_with($segment, '{') && !\str_starts_with($segment, '[')) {
                $plain[] = $segment;
            }
        }
        if ($plain !== []) {
            $index = $this->pluralizer->indexFor($number, $locale);

            return $plain[\min($index, \count($plain) - 1)];
        }

        return $segments[0] ?? '';
    }
}
