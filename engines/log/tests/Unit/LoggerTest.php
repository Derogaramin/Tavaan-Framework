<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Tests\Unit;

use Tavaan\Core\Contracts\Log\LogLevel;
use Tavaan\Engine\Log\Buffer;
use Tavaan\Engine\Log\Formatters\JsonFormatter;
use Tavaan\Engine\Log\Handlers\ArrayHandler;
use Tavaan\Engine\Log\Logger;
use Tavaan\Engine\Log\LogRecord;
use Tavaan\Engine\Log\Processors\MaskProcessor;
use Tavaan\Core\Error\LogMasker;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Log — سناریوهای DOC-036 §12 (AC-LOG-01).
 */
final class LoggerTest extends TestCase
{
    private ArrayHandler $handler;

    protected function setUp(): void
    {
        Logger::resetRequestCounter();
        $this->handler = new ArrayHandler();
    }

    private function logger(LogLevel $threshold = LogLevel::Debug, array $processors = [], $formatter = null, bool $buffered = false): Logger
    {
        return new Logger(
            channelName: 'test',
            threshold: $threshold,
            handlers: [$this->handler],
            processors: $processors,
            formatter: $formatter,
            environment: 'testing',
            buffered: $buffered,
            bufferMaxLines: 3,
        );
    }

    /** T-01: هر ۸ سطح → severity RFC 5424 */
    public function testAllLevelsSeverity(): void
    {
        $this->assertSame(0, LogLevel::Emergency->severity());
        $this->assertSame(3, LogLevel::Error->severity());
        $this->assertSame(7, LogLevel::Debug->severity());
        $this->assertTrue(LogLevel::Error->withinThreshold(LogLevel::Debug));
        $this->assertFalse(LogLevel::Debug->withinThreshold(LogLevel::Error));
    }

    /** T-02: سطح فیلتر */
    public function testLevelThresholdFilters(): void
    {
        $logger = $this->logger(LogLevel::Warning);
        $logger->info('ignored.message');
        $logger->debug('ignored.too');
        $this->assertCount(0, $this->handler->lines());

        $logger->warning('shown.message');
        $this->assertCount(1, $this->handler->lines());
        $this->assertStringContainsString('shown.message', $this->handler->lines()[0]);
    }

    /** T-04+T-05: Mask کلید password و الگوی کارت */
    public function testMaskProcessor(): void
    {
        $logger = $this->logger(processors: [new MaskProcessor(new LogMasker())], formatter: new JsonFormatter());
        $logger->info('auth.login', ['password' => 'super-secret', 'note' => 'card 6219861034567890 charged']);

        $line = $this->handler->lines()[0];
        $this->assertStringNotContainsString('super-secret', $line);
        $this->assertStringContainsString('********', $line);
        $this->assertStringNotContainsString('6219861034567890', $line);
        $this->assertStringContainsString('62************90', $line);
    }

    /** T-10: Log Injection با \r\n پاک می‌شود */
    public function testCrlfInjectionSanitized(): void
    {
        $logger = $this->logger();
        $logger->info("evil\r\n[FAKE] injected", ['x' => "a\rb"]);
        $line = $this->handler->lines()[0];
        $this->assertStringNotContainsString("\r", $line === '' ? '' : \substr($line, 0, -1));
        $this->assertStringNotContainsString("[FAKE]", \str_replace('evil [FAKE] injected', '', $line));
        $this->assertStringContainsString('evil [FAKE] injected', $line);
    }

    /** T-12: JSON formatter — UTF-8 فارسی بدون escape */
    public function testJsonFormatterUnescapedUnicode(): void
    {
        $logger = $this->logger(formatter: new JsonFormatter());
        $logger->info('shop.order.paid', ['title' => 'سفارش شمارهٔ ۱']);
        $line = $this->handler->lines()[0];
        $this->assertStringContainsString('سفارش', $line);
        $this->assertStringNotContainsString('\\u0633', $line);

        $decoded = \json_decode($line, true);
        $this->assertSame('shop.order.paid', $decoded['message']);
        $this->assertSame('test', $decoded['channel']);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame(6, $decoded['severity']);
        $this->assertSame('testing', $decoded['env']);
    }

    /** T-08: Buffer — N رکورد → یک writeBatch */
    public function testBufferBatchesWrites(): void
    {
        $logger = $this->logger(buffered: true);
        $logger->info('m1');
        $logger->info('m2');
        $this->assertCount(0, $this->handler->lines(), 'buffer holds writes');
        $logger->info('m3');   // آستانه (3) → flush
        $this->assertCount(3, $this->handler->lines());
    }

    /** T-13: shareContext در همهٔ رکوردها */
    public function testSharedContext(): void
    {
        $logger = $this->logger(formatter: new JsonFormatter());
        $logger->shareContext(['tenant' => 'acme']);
        $logger->info('first');
        $logger->info('second');

        foreach ($this->handler->lines() as $line) {
            $decoded = \json_decode($line, true);
            $this->assertSame('acme', $decoded['context']['tenant']);
        }
    }

    /** withContext یک‌بار مصرف */
    public function testWithContextOneShot(): void
    {
        $logger = $this->logger(formatter: new JsonFormatter());
        $logger->withContext(['orderId' => 5])->info('order.paid');
        $logger->info('other');

        $first = \json_decode($this->handler->lines()[0], true);
        $second = \json_decode($this->handler->lines()[1], true);
        $this->assertSame(5, $first['context']['orderId']);
        $this->assertArrayNotHasKey('orderId', $second['context']);
    }

    /** Context تنبل (Closure) فقط هنگام لاگ واقعی اجرا می‌شود */
    public function testLazyContextClosure(): void
    {
        $ran = false;
        $logger = $this->logger(LogLevel::Error);   // آستانه error → debug رد می‌شود
        $logger->debug('expensive', static function () use (&$ran): array { $ran = true; return ['x' => 1]; });
        $this->assertFalse($ran, 'closure must not run below threshold');

        $logger->error('real', static function () use (&$ran): array { $ran = true; return ['x' => 1]; });
        $this->assertTrue($ran);
    }

    /** T-11: per_request_limit */
    public function testPerRequestLimit(): void
    {
        $logger = $this->logger(formatter: new JsonFormatter(), processors: []);
        $limited = new Logger('lim', LogLevel::Debug, [$this->handler], [], null, ['per_request_limit' => 5], 'testing');
        Logger::resetRequestCounter();
        for ($i = 0; $i < 12; $i++) {
            $limited->info('msg', ['i' => $i]);
        }
        $lines = $this->handler->lines();
        $this->assertSame(6, \count($lines), '5 messages + 1 limit warning');
        $this->assertStringContainsString('log.per_request_limit_exceeded', $lines[5]);
    }

    /** LogRecord readonly + withSource */
    public function testLogRecordImmutability(): void
    {
        $record = new LogRecord('c', LogLevel::Info, 'm');
        $copy = $record->withSource('f.php', 10);
        $this->assertNull($record->file);
        $this->assertSame('f.php', $copy->file);
        $this->assertSame(10, $copy->line);
    }
}
