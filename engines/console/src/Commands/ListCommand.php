<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Engine\Console\Command;

/**
 * فهرست همهٔ دستورات ثبت‌شده (AC-CLI-01).
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
final class ListCommand extends Command
{
    protected string $signature = 'list {--raw : فقط نام دستورات}';

    protected string $description = 'فهرست دستورات موجود';

    public function handle(): int
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->app->make(KernelInterface::class);
        $commands = $kernel->commands();

        if ($this->option('raw') === true || $this->option('raw') === 'true') {
            foreach (\array_keys($commands) as $name) {
                $this->line($name);
            }

            return self::SUCCESS;
        }

        $this->newLine();
        $this->output->writeln('  تَوان | Tavaan ' . (\defined('TVN_VERSION') ? TVN_VERSION : ''), 'bold');
        $this->comment('  Usage: tvn <command> [options] [arguments]');
        $this->newLine();

        $groups = [];
        foreach ($commands as $name => $class) {
            $group = \str_contains($name, ':') ? \explode(':', $name)[0] : 'general';
            $instance = $kernel->resolve($name);
            $groups[$group][$name] = $instance->description();
        }
        \ksort($groups);

        foreach ($groups as $group => $items) {
            $this->output->writeln('  ' . $group, 'info');
            \ksort($items);
            foreach ($items as $name => $description) {
                $this->line(\sprintf('    %-28s %s', $name, $description));
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
