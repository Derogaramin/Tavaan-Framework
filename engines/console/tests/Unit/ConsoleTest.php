<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Tests\Unit;

use Tavaan\Engine\Console\Input\ArgvInput;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Engine\Console\SignatureParser;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Console — DOC-076 §10.
 */
final class ConsoleTest extends TestCase
{
    /** T-01: Signature parsing */
    public function testSignatureParser(): void
    {
        $spec = (new SignatureParser())->parse(
            'cache:clear
                {store? : نام store}
                {paths* : مسیرها}
                {--tags= : فقط تگها}
                {--force : بدون تایید}'
        );

        $this->assertSame('cache:clear', $spec['name']);
        $this->assertTrue($spec['arguments']['store']['optional']);
        $this->assertFalse($spec['arguments']['store']['array']);
        $this->assertTrue($spec['arguments']['paths']['array']);
        $this->assertFalse($spec['options']['tags']['flag']);
        $this->assertTrue($spec['options']['force']['flag']);
        $this->assertSame('نام store', $spec['arguments']['store']['description']);
    }

    /** ArgvInput: option/argument/flag */
    public function testArgvInputParsing(): void
    {
        $spec = (new SignatureParser())->parse('cmd {name?} {rest*} {--opt=default} {--flag}');
        $input = new ArgvInput(['myname', 'a', 'b', '--opt=custom', '--flag', '--extra'], $spec['arguments'], $spec['options']);

        $this->assertSame('myname', $input->argument('name'));
        $this->assertSame(['a', 'b'], $input->argument('rest'));
        $this->assertSame('custom', $input->option('opt'));
        $this->assertTrue($input->option('flag'));
        $this->assertTrue($input->option('extra'));
    }

    public function testArgvInputDefaults(): void
    {
        $spec = (new SignatureParser())->parse('cmd {--level=debug} {name?}');
        $input = new ArgvInput([], $spec['arguments'], $spec['options']);
        $this->assertSame('debug', $input->option('level'));
        $this->assertNull($input->argument('name'));
        $this->assertSame('fb', $input->argument('name', 'fb'));
    }

    /** interactive input queue (برای تست ask) */
    public function testInteractiveInputQueue(): void
    {
        $input = new ArgvInput();
        $input->setInteractiveInput('answer');
        $this->assertSame('answer', $input->readLine('q: '));
    }

    /** T-02: NO_COLOR → بدون ANSI */
    public function testOutputColorOverride(): void
    {
        $stream = \fopen('php://memory', 'w+');
        \assert($stream !== false);
        $output = new Output($stream, colorsOverride: false);
        $output->writeln('plain', 'info');
        \rewind($stream);
        $content = (string) \stream_get_contents($stream);
        $this->assertSame("plain\n", $content);

        $stream2 = \fopen('php://memory', 'w+');
        \assert($stream2 !== false);
        $colored = new Output($stream2, colorsOverride: true);
        $colored->writeln('green', 'info');
        \rewind($stream2);
        $content2 = (string) \stream_get_contents($stream2);
        $this->assertStringContainsString("\033[32m", $content2);
    }

    public function testTableRendering(): void
    {
        $stream = \fopen('php://memory', 'w+');
        \assert($stream !== false);
        $output = new Output($stream, colorsOverride: false);
        $output->table(['Name', 'Qty'], [['سیب', '2'], ['pear', '10']]);
        \rewind($stream);
        $content = (string) \stream_get_contents($stream);
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('سیب', $content);
        $this->assertStringContainsString('+', $content);
    }
}
