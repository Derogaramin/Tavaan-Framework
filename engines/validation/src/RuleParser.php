<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Validation\RuleInterface;

/**
 * تَوان | Tavaan — Validation
 * RuleParser — تجزیهٔ rule string/array به ParsedRule (DOC-051 §2.1).
 *
 * جداساز: `|`؛ پارامتر: `:`؛ پارامتر چندگانه: `,`.
 * 🔒 قواعد regex/nested-regex با delimiter-aware splitting سالم می‌مانند.
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class RuleParser
{
    /**
     * @param string|list<mixed> $rules
     *
     * @return list<ParsedRule>
     */
    public static function parse(string|array $rules): array
    {
        if (\is_string($rules)) {
            $rules = self::split($rules);
        }

        $parsed = [];
        foreach ($rules as $rule) {
            if ($rule instanceof ParsedRule) {
                $parsed[] = $rule;
                continue;
            }
            if ($rule instanceof RuleInterface) {
                $parsed[] = new ParsedRule(self::objectRuleName($rule), object: $rule);
                continue;
            }
            if ($rule instanceof \Closure) {
                $parsed[] = new ParsedRule('callback', callback: $rule);
                continue;
            }
            if (!\is_string($rule)) {
                throw new \InvalidArgumentException('Rule must be string, RuleInterface or Closure.');
            }

            $rule = \trim($rule);
            if ($rule === '') {
                continue;
            }

            [$name, $paramString] = \array_pad(\explode(':', $rule, 2), 2, '');
            $name = \strtolower(\trim($name));

            // 🔒 الگوی regex یک‌پارچه است (`,` و `|` درون آن جداکننده نیستند)
            if (\in_array($name, ['regex', 'not_regex'], true)) {
                $parameters = $paramString === '' ? [] : [$paramString];
            } else {
                $parameters = $paramString === '' ? [] : \explode(',', $paramString);
            }

            $parsed[] = new ParsedRule($name, $parameters);
        }

        return $parsed;
    }

    /**
     * split با `|` — اما الگوی regex:/…/ را نمی‌شکند (delimiter-aware).
     *
     * @return list<string>
     */
    public static function split(string $rules): array
    {
        $segments = [];
        $current = '';
        $length = \strlen($rules);
        $inRegex = false;
        $delimiter = '';
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $rules[$i];

            if ($inRegex) {
                $current .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $delimiter) {
                    // modifierها تا کاراکتر غیرحرفی ادامه دارند
                    $inRegex = false;
                }
                continue;
            }

            if ($char === '|' && $current !== '') {
                $segments[] = $current;
                $current = '';
                continue;
            }
            if ($char === '|' && $current === '') {
                continue;
            }

            $current .= $char;

            // ورود به regex:/…/
            if (\preg_match('/(?:^|:)((?:not_)?regex|jalali_date_format|date_format):(.)$/', $current, $m) === 1
                && \in_array($m[1], ['regex', 'not_regex'], true)) {
                $inRegex = true;
                $delimiter = $m[2];
            }
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    private static function objectRuleName(RuleInterface $rule): string
    {
        $class = $rule::class;
        $short = \substr($class, (int) \strrpos($class, '\\') + 1);

        return \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $short) ?? $short);
    }
}
