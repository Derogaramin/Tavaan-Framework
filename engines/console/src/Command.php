<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console;

use Tavaan\Core\Contracts\Console\CommandInterface;
use Tavaan\Core\Contracts\Console\InputInterface;
use Tavaan\Core\Contracts\Console\OutputInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Console\Input\ArgvInput;

/**
 * تَوان | Tavaan — Console
 * Command — پایهٔ همهٔ دستورات CLI (Signature + I/O رنگی + تعامل).
 *
 * ### مثال
 * ```php
 * final class MyCommand extends Command
 * {
 *     protected string $signature = 'my:cmd {name?} {--force}';
 *     protected string $description = 'توضیح';
 *     public function handle(): int
 *     {
 *         $this->info('سلام ' . $this->argument('name'));
 *         return self::SUCCESS;
 *     }
 * }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
abstract class Command implements CommandInterface
{
    public const SUCCESS = 0;

    public const FAILURE = 1;

    public const INVALID = 2;

    protected string $signature = '';

    protected string $description = '';

    protected ContainerInterface $app;

    protected InputInterface $input;

    protected OutputInterface $output;

    /** @var array{name: string, description: ?string, arguments: array<string, array{optional: bool, array: bool, description: ?string}>, options: array<string, array{default: mixed, flag: bool, description: ?string}>}|null */
    private ?array $spec = null;

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** نام دستور (بدون پارامترها). */
    public function name(): string
    {
        return $this->spec()['name'];
    }

    /** اجرا با ورودی خام (توسط Kernel). @param list<string> $tokens */
    public function run(ContainerInterface $app, array $tokens, OutputInterface $output): int
    {
        $this->app = $app;
        $this->output = $output;

        $spec = $this->spec();
        $this->input = new ArgvInput($tokens, $spec['arguments'], $spec['options']);

        if ($this->input->option('help') === true) {
            $this->showHelp();

            return self::SUCCESS;
        }

        return $this->handle();
    }

    abstract public function handle(): int;

    // ─── دسترسی به ورودی ─────────────────────────────────────────────────

    protected function argument(string $name, mixed $default = null): mixed
    {
        return $this->input->argument($name, $default);
    }

    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->input->option($name, $default);
    }

    // ─── خروجی ────────────────────────────────────────────────────────────

    protected function info(string $text): void
    {
        $this->output->writeln($text, 'info');
    }

    protected function error(string $text): void
    {
        $this->output->writeln(' ' . $text . ' ', 'error');
    }

    protected function warn(string $text): void
    {
        $this->output->writeln($text, 'comment');
    }

    protected function line(string $text): void
    {
        $this->output->writeln($text);
    }

    protected function comment(string $text): void
    {
        $this->output->writeln($text, 'comment');
    }

    protected function newLine(int $count = 1): void
    {
        $this->output->newLine($count);
    }

    /**
     * @param list<string>                  $headers
     * @param list<array<array-key, mixed>> $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }

    // ─── تعامل ────────────────────────────────────────────────────────────

    protected function ask(string $question, ?string $default = null): ?string
    {
        $suffix = $default !== null ? " [{$default}]" : '';
        $answer = $this->input->readLine($question . $suffix . ': ');

        return $answer === null || $answer === '' ? $default : $answer;
    }

    protected function secret(string $question): ?string
    {
        return $this->input->readLine($question . ': ');
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        $suffix = $default ? '[Y/n]' : '[y/N]';
        $answer = $this->input->readLine($question . ' ' . $suffix . ' ');

        if ($answer === null || $answer === '') {
            return $default;
        }

        return \in_array(\strtolower($answer), ['y', 'yes', 'بله'], true);
    }

    /**
     * @param list<string> $choices
     */
    protected function choice(string $question, array $choices, ?string $default = null): ?string
    {
        foreach ($choices as $i => $choice) {
            $this->line(\sprintf('  [%d] %s', $i, $choice));
        }

        $answer = $this->input->readLine($question . ' ');
        if ($answer === null || $answer === '') {
            return $default;
        }
        if (\ctype_digit($answer) && isset($choices[(int) $answer])) {
            return $choices[(int) $answer];
        }

        return \in_array($answer, $choices, true) ? $answer : $default;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /** @return array{name: string, description: ?string, arguments: array<string, array{optional: bool, array: bool, description: ?string}>, options: array<string, array{default: mixed, flag: bool, description: ?string}>} */
    private function spec(): array
    {
        return $this->spec ??= (new SignatureParser())->parse($this->signature);
    }

    private function showHelp(): void
    {
        $spec = $this->spec();
        $this->newLine();
        $this->output->writeln('  ' . $spec['name'], 'bold');
        if ($spec['description'] !== null) {
            $this->comment('  ' . $spec['description']);
        }
        $this->newLine();

        if ($spec['arguments'] !== []) {
            $this->output->writeln('  Arguments:', 'bold');
            foreach ($spec['arguments'] as $name => $arg) {
                $optional = $arg['optional'] ? ' (optional)' : '';
                $this->line(\sprintf('    %-20s %s%s', $name, $arg['description'] ?? '', $optional));
            }
            $this->newLine();
        }

        $this->output->writeln('  Options:', 'bold');
        foreach (['help' => ['description' => 'نمایش راهنما'], ...$spec['options']] as $name => $opt) {
            $default = isset($opt['default']) && $opt['default'] !== null ? ' (default: ' . $opt['default'] . ')' : '';
            $this->line(\sprintf('    --%-18s %s%s', $name, $opt['description'] ?? '', $default));
        }
        $this->newLine();
    }
}
