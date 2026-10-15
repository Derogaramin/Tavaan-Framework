<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console;

/**
 * تَوان | Tavaan — Console
 * SignatureParser — پارس الگوی امضای دستور (DOC-076 §4).
 *
 * الگو: `command:name {required} {optional?} {array*} {--option} {--opt=default} {--flag}`
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
final class SignatureParser
{
    /**
     * @return array{name: string, description: ?string, arguments: array<string, array{optional: bool, array: bool, description: ?string}>, options: array<string, array{default: mixed, flag: bool, description: ?string}>}
     */
    public function parse(string $signature): array
    {
        $parts = \preg_split('/\s+/', \trim($signature), 2);
        $name = $parts[0];
        $description = null;

        \preg_match_all('/\{([^}]+)\}/', $signature, $matches);

        $arguments = [];
        $options = [];

        foreach ($matches[1] as $token) {
            [$token, $desc] = \array_pad(\explode(' : ', $token, 2), 2, null);
            $token = \trim($token);

            if (\str_starts_with($token, '--')) {
                $token = \substr($token, 2);
                $default = null;
                $isFlag = true;
                if (\str_contains($token, '=')) {
                    [$token, $default] = \explode('=', $token, 2);
                    $isFlag = false;
                }
                $options[$token] = ['default' => $default === '' ? null : $default, 'flag' => $isFlag, 'description' => $desc];
                continue;
            }

            $isArray = \str_ends_with($token, '*');
            $token = \rtrim($token, '*');
            $optional = \str_ends_with($token, '?');
            $token = \rtrim($token, '?');
            $arguments[$token] = ['optional' => $optional, 'array' => $isArray, 'description' => $desc];
        }

        return ['name' => $name, 'description' => $description, 'arguments' => $arguments, 'options' => $options];
    }

    /** نام دستور از امضا. */
    public function name(string $signature): string
    {
        $parts = \preg_split('/\s+/', \trim($signature), 2);

        return $parts[0];
    }
}
