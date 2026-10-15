<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Input;

use Tavaan\Core\Contracts\Console\InputInterface;

/**
 * تَوان | Tavaan — Console
 * ArgvInput — پارس argv بر اساس امضای دستور + ورودی تعاملی.
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
final class ArgvInput implements InputInterface
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    /** @var array<string, mixed> */
    private array $options = [];

    /** @var list<string> صف ورودی تعاملی (تست) */
    private array $interactiveQueue = [];

    /**
     * @param list<string>       $tokens توکن‌ها بدون نام دستور
     * @param array<string, array{optional: bool, array: bool, description: ?string}> $argumentSpec
     * @param array<string, array{default: mixed, flag: bool, description: ?string}> $optionSpec
     */
    public function __construct(
        private readonly array $tokens = [],
        private readonly array $argumentSpec = [],
        private readonly array $optionSpec = [],
    ) {
        $this->parse();
    }

    public function argument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $this->argumentSpec[$name]['default'] ?? $default;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        if (\array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return $this->optionSpec[$name]['default'] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options) || isset($this->optionSpec[$name]);
    }

    /** @return array<string, mixed> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return $this->options;
    }

    /** @return list<string> */
    public function rawTokens(): array
    {
        return $this->tokens;
    }

    public function setInteractiveInput(string $value): void
    {
        $this->interactiveQueue[] = $value;
    }

    public function readLine(string $prompt = ''): ?string
    {
        if ($this->interactiveQueue !== []) {
            return \array_shift($this->interactiveQueue);
        }

        if ($prompt !== '') {
            \fwrite(STDOUT, $prompt);
        }

        $line = \fgets(STDIN);

        return $line === false ? null : \rtrim($line, "\r\n");
    }

    private function parse(): void
    {
        $argNames = \array_keys($this->argumentSpec);
        $positional = [];
        $onlyPositional = false;

        foreach ($this->tokens as $token) {
            if ($token === '--') {
                $onlyPositional = true;
                continue;
            }

            if (!$onlyPositional && \str_starts_with($token, '--')) {
                $body = \substr($token, 2);
                if (\str_contains($body, '=')) {
                    [$key, $value] = \explode('=', $body, 2);
                    $this->options[$key] = $value;
                    continue;
                }
                $this->options[$body] = true;
                continue;
            }

            $positional[] = $token;
        }

        // نگاشت positional → argumentها (array argument بقیه را می‌بلعد)
        $i = 0;
        foreach ($argNames as $index => $argName) {
            $spec = $this->argumentSpec[$argName];

            if ($spec['array']) {
                $this->arguments[$argName] = \array_slice($positional, $index);
                $i = \count($positional);
                break;
            }

            if (isset($positional[$index])) {
                $this->arguments[$argName] = $positional[$index];
                $i = $index + 1;
            }
        }

        // optionهای پیش‌فرض
        foreach ($this->optionSpec as $name => $spec) {
            if (!\array_key_exists($name, $this->options) && $spec['default'] !== null) {
                $this->options[$name] = $spec['default'];
            }
        }
    }
}
