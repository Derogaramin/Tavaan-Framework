<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Config\EnvRepository;
use Tavaan\Engine\Console\Command;

/**
 * نمایش متغیرهای محیطی (mask شده).
 *
 * @api 1.0
 * @see docs/04-structure/config-reference.md
 */
final class EnvCommand extends Command
{
    protected string $signature = 'env {key? : فقط یک کلید}';

    protected string $description = 'نمایش متغیرهای محیطی (secretها mask می‌شوند)';

    public function handle(): int
    {
        $env = EnvRepository::instance();

        $key = $this->argument('key');
        if (\is_string($key) && $key !== '') {
            $value = $env->get($key);
            $isSecret = \str_contains(\strtolower($key), 'key') || \str_contains(\strtolower($key), 'secret')
                || \str_contains(\strtolower($key), 'password') || \str_contains(\strtolower($key), 'token');
            $this->line($key . '=' . ($isSecret && $value !== null ? '********' : \var_export($value, true)));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($env->all(masked: true) as $name => $value) {
            $rows[] = [$name, \is_scalar($value) ? \var_export($value, true) : \json_encode($value, JSON_UNESCAPED_UNICODE)];
        }
        \usort($rows, static fn (array $a, array $b): int => \strcmp($a[0], $b[0]));

        $this->table(['Key', 'Value'], $rows);

        return self::SUCCESS;
    }
}
