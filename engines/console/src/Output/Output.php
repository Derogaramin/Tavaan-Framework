<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Output;

use Tavaan\Core\Contracts\Console\OutputInterface;

/**
 * تَوان | Tavaan — Console
 * Output — خروجی رنگی ANSI (با احترام به NO_COLOR/FORCE_COLOR).
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
final class Output implements OutputInterface
{
    /** @var array<string, array{fg?: string, bg?: string, bold?: bool}> */
    private const STYLES = [
        'info'    => ['fg' => 'green'],
        'error'   => ['fg' => 'white', 'bg' => 'red'],
        'warn'    => ['fg' => 'black', 'bg' => 'yellow'],
        'comment' => ['fg' => 'yellow'],
        'success' => ['fg' => 'green', 'bold' => true],
        'dim'     => ['fg' => 'gray'],
        'bold'    => ['bold' => true],
    ];

    /** @var array<string, string> */
    private const FG = [
        'black' => '30', 'red' => '31', 'green' => '32', 'yellow' => '33',
        'blue' => '34', 'magenta' => '35', 'cyan' => '36', 'white' => '37', 'gray' => '90',
    ];

    /** @var array<string, string> */
    private const BG = [
        'black' => '40', 'red' => '41', 'green' => '42', 'yellow' => '43',
        'blue' => '44', 'magenta' => '45', 'cyan' => '46', 'white' => '47',
    ];

    /** @var resource */
    private $stream;

    /** @param resource|null $stream */
    public function __construct($stream = null, private ?bool $colorsOverride = null)
    {
        $this->stream = $stream ?? STDOUT;
    }

    public function write(string $text, string $style = ''): void
    {
        \fwrite($this->stream, $this->format($text, $style));
    }

    public function writeln(string $text = '', string $style = ''): void
    {
        \fwrite($this->stream, $this->format($text, $style) . PHP_EOL);
    }

    public function newLine(int $count = 1): void
    {
        \fwrite($this->stream, \str_repeat(PHP_EOL, $count));
    }

    public function table(array $headers, array $rows): void
    {
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = \mb_strlen((string) $header);
        }
        foreach ($rows as $row) {
            foreach (\array_values($row) as $i => $cell) {
                $widths[$i] = \max($widths[$i] ?? 0, \mb_strlen($this->stripAnsi((string) $cell)));
            }
        }

        $line = '+';
        foreach ($widths as $w) {
            $line .= \str_repeat('-', $w + 2) . '+';
        }

        $renderRow = function (array $cells) use ($widths): string {
            $out = '|';
            foreach ($widths as $i => $w) {
                $cell = (string) (\array_values($cells)[$i] ?? '');
                $pad = $w - \mb_strlen($this->stripAnsi($cell));
                $out .= ' ' . $cell . \str_repeat(' ', \max(0, $pad)) . ' |';
            }

            return $out;
        };

        $this->writeln($line, 'dim');
        $this->writeln($renderRow($headers), 'bold');
        $this->writeln($line, 'dim');
        foreach ($rows as $row) {
            $this->writeln($renderRow($row));
        }
        $this->writeln($line, 'dim');
    }

    public function supportsColor(): bool
    {
        if ($this->colorsOverride !== null) {
            return $this->colorsOverride;
        }
        if (($_SERVER['NO_COLOR'] ?? \getenv('NO_COLOR')) !== false && ($_SERVER['NO_COLOR'] ?? '') !== '') {
            return false;
        }
        if (($_SERVER['FORCE_COLOR'] ?? \getenv('FORCE_COLOR')) !== false && ($_SERVER['FORCE_COLOR'] ?? '') !== '') {
            return true;
        }

        return \function_exists('stream_isatty') ? \stream_isatty($this->stream) : false;
    }

    private function format(string $text, string $style): string
    {
        if ($style === '' || !$this->supportsColor() || !isset(self::STYLES[$style])) {
            return $text;
        }

        $codes = [];
        $spec = self::STYLES[$style];
        if (isset($spec['fg'])) {
            $codes[] = self::FG[$spec['fg']];
        }
        if (isset($spec['bg'])) {
            $codes[] = self::BG[$spec['bg']];
        }
        if (($spec['bold'] ?? false) === true) {
            $codes[] = '1';
        }

        return "\033[" . \implode(';', $codes) . 'm' . $text . "\033[0m";
    }

    private function stripAnsi(string $text): string
    {
        return (string) \preg_replace('/\033\[[0-9;]*m/', '', $text);
    }
}
