<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console;

use Tavaan\Core\Contracts\Console\CommandInterface;
use Tavaan\Core\Contracts\Console\InputInterface;
use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Core\Contracts\Console\OutputInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Console\Input\ArgvInput;
use Tavaan\Engine\Console\Output\Output;

/**
 * تَوان | Tavaan — Console
 * ConsoleKernel — نقطهٔ ورود CLI: کشف/ثبت دستورها، dispatch، کد خروج.
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
final class ConsoleKernel implements KernelInterface
{
    /** @var array<string, CommandInterface|class-string> */
    private array $commands = [];

    private SignatureParser $parser;

    private bool $discovered = false;

    public function __construct(private readonly ContainerInterface $app)
    {
        $this->parser = new SignatureParser();
        $this->registerBuiltins();
    }

    public function register(CommandInterface|string $command): void
    {
        $signature = $command instanceof CommandInterface ? $command->signature() : (new \ReflectionClass($command))->getDefaultProperties()['signature'] ?? '';
        $name = $this->parser->name((string) $signature);
        if ($name === '') {
            return;
        }

        $this->commands[$name] = $command;
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $input->rawTokens();
        $name = \array_shift($tokens) ?? 'list';

        if ($name === '--help' || $name === '-h') {
            $name = 'list';
        }

        $this->discover();

        if (!isset($this->commands[$name])) {
            $output->writeln("  دستور «{$name}» یافت نشد.", 'error');
            $suggestion = $this->suggest($name);
            if ($suggestion !== null) {
                $output->writeln('  شاید منظور شما: ' . $suggestion, 'comment');
            }
            $output->writeln('  برای فهرست دستورات: tvn list', 'dim');

            return self::INVALID_EXIT;
        }

        return $this->runCommand($name, $tokens, $output);
    }

    public function call(string $command, array $arguments = []): int
    {
        $this->discover();

        $tokens = [];
        foreach ($arguments as $key => $value) {
            if (\is_string($key) && \str_starts_with($key, '--')) {
                $tokens[] = $value === true ? $key : $key . '=' . $value;
                continue;
            }
            $tokens[] = (string) $value;
        }

        return $this->runCommand($command, $tokens, new Output());
    }

    /** @return array<string, string> نام → class (برای list) */
    public function commands(): array
    {
        $this->discover();

        $out = [];
        foreach ($this->commands as $name => $command) {
            $out[$name] = \is_string($command) ? $command : $command::class;
        }
        \ksort($out);

        return $out;
    }

    public function resolve(string $name): CommandInterface
    {
        $this->discover();

        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            throw new \InvalidArgumentException(\sprintf('Command [%s] not registered.', $name));
        }

        return \is_string($command) ? $this->app->make($command) : $command;
    }

    private const INVALID_EXIT = 2;

    /** @param list<string> $tokens */
    private function runCommand(string $name, array $tokens, OutputInterface $output): int
    {
        try {
            $command = $this->resolve($name);

            if ($command instanceof Command) {
                return $command->run($this->app, $tokens, $output);
            }

            $command->handle();

            return 0;
        } catch (\Throwable $e) {
            if ($this->app->bound(\Tavaan\Engine\Error\ExceptionHandler::class)) {
                $handler = $this->app->make(\Tavaan\Engine\Error\ExceptionHandler::class);
                $handler->report($e);
                $output->writeln($handler->render($e, 'cli')->body);

                return 1;
            }

            $output->writeln('  [' . $e::class . '] ' . $e->getMessage(), 'error');
            $output->writeln('  at ' . $e->getFile() . ':' . $e->getLine(), 'dim');

            return 1;
        }
    }

    private function registerBuiltins(): void
    {
        $builtins = [
            Commands\ListCommand::class,
            Commands\AboutCommand::class,
            Commands\VersionCommand::class,
            Commands\EnvCommand::class,
            Commands\DoctorCommand::class,
            Commands\OptimizeCommand::class,
            Commands\OptimizeClearCommand::class,
            Commands\ConfigCacheCommand::class,
            Commands\ConfigClearCommand::class,
            Commands\AutoloadCacheCommand::class,
            Commands\ModuleListCommand::class,
            Commands\EventListCommand::class,
            Commands\TestCommand::class,
        ];

        foreach ($builtins as $class) {
            $this->register($class);
        }
    }

    /** کشف خودکار Commandها از engines و app (بدون cache در dev). */
    private function discover(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        // دستورات اضافی از config
        if ($this->app->bound('config')) {
            foreach ((array) $this->app->make('config')->get('console.commands', []) as $class) {
                if (\is_string($class) && \class_exists($class)) {
                    $this->register($class);
                }
            }
        }

        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : null;
        if ($base === null) {
            return;
        }

        $patterns = [
            $base . '/app/src/Console/Commands/*Command.php',
            $base . '/modules/*/src/Console/Commands/*Command.php',
            $base . '/engines/*/src/Console/*Command.php',
            $base . '/engines/*/src/Console/Commands/*Command.php',
        ];

        foreach ($patterns as $pattern) {
            foreach (\glob($pattern) ?: [] as $file) {
                $class = $this->classFromFile($file, $base);
                if ($class !== null && \class_exists($class)) {
                    $this->register($class);
                }
            }
        }
    }

    private function classFromFile(string $file, string $base): ?string
    {
        $contents = (string) \file_get_contents($file);
        $ns = \preg_match('/namespace\s+([^;]+);/', $contents, $m) === 1 ? \trim($m[1]) . '\\' : '';
        $cls = \preg_match('/class\s+(\w+)/', $contents, $m2) === 1 ? $m2[1] : null;

        return $cls === null ? null : $ns . $cls;
    }

    private function suggest(string $name): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach (\array_keys($this->commands) as $candidate) {
            $distance = \levenshtein($name, $candidate);
            if ($distance < $bestDistance && $distance <= 4) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }
}
