<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config;

use Tavaan\Engine\Config\Exceptions\EnvParseException;

/**
 * تَوان | Tavaan — Config
 * EnvLoader — پارس فایل `.env` بدون putenv (DOC-027 §4).
 *
 * پشتیبانی: نقل‌قول دوتایی (با escape)، نقل‌قول تکی (literal)، کامنت درون‌خطی،
 * مقادیر true/false/null/empty و cast خودکار int/float.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class EnvLoader
{
    /**
     * پارس فایل .env.
     *
     * @return array<string, string|bool|int|float|null>
     *
     * @throws EnvParseException کلید نامعتبر
     */
    public function load(string $file, bool $override = false): array
    {
        if (!\is_file($file) || !\is_readable($file)) {
            return [];
        }

        $vars = [];
        $lines = \file($file, FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $lineNumber => $line) {
            $line = \trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = \strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = \trim(\substr($line, 0, $pos));
            $value = $this->parseValue(\trim(\substr($line, $pos + 1)));

            if (!$this->isValidKey($key)) {
                throw EnvParseException::invalidKey($lineNumber + 1, $key);
            }

            if ($override || !\array_key_exists($key, $vars)) {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    /** پارس مقدار با quote/escape/inline-comment و cast نوع. */
    private function parseValue(string $raw): string|bool|int|float|null
    {
        if (\strlen($raw) >= 2) {
            $first = $raw[0];
            $last = $raw[-1];
            if ($first === '"' && $last === '"') {
                return \stripcslashes(\substr($raw, 1, -1));
            }
            if ($first === "'" && $last === "'") {
                return \substr($raw, 1, -1);
            }
        }

        // کامنت درون‌خطی (فقط خارج از quote)
        $hash = \strpos($raw, ' #');
        if ($hash !== false) {
            $raw = \rtrim(\substr($raw, 0, $hash));
        }

        return match (\strtolower($raw)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => \preg_match('/^-?\d+$/', $raw) === 1
                ? (int) $raw
                : (\is_numeric($raw) ? (float) $raw : $raw),
        };
    }

    /** 🔒 ENV Injection: فقط `[A-Z_][A-Z0-9_]*` مجاز است. */
    private function isValidKey(string $key): bool
    {
        return \preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) === 1;
    }
}
