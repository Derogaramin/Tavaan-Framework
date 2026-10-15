<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error\Tests\Unit;

use Tavaan\Core\Contracts\Log\LogLevel;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Engine\Error\ExceptionHandler;
use Tavaan\Engine\Log\Handlers\ArrayHandler;
use Tavaan\Engine\Log\Logger;
use Tavaan\Testing\TestCase;

/**
 * تست ExceptionHandler — AC-ERR-01..08 (سطح M1).
 */
final class ExceptionHandlerTest extends TestCase
{
    private ArrayHandler $logHandler;

    private Logger $logger;

    protected function setUp(): void
    {
        Logger::resetRequestCounter();
        $this->logHandler = new ArrayHandler();
        $this->logger = new Logger('test', LogLevel::Debug, [$this->logHandler], environment: 'testing');
    }

    public function testDontReportSkipsLogging(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $handler->dontReport([ValidationException::class]);

        $handler->report(new ValidationException(['title' => ['required']]));
        $this->assertCount(0, $this->logHandler->lines());
    }

    public function testServerErrorIsReported(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $handler->report(new \RuntimeException('db exploded'));
        $this->assertCount(1, $this->logHandler->lines());
        $this->assertStringContainsString('db exploded', $this->logHandler->lines()[0]);
    }

    public function testClientErrorNotReportedByDefault(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $handler->report(new RouteNotFoundException());
        $this->assertCount(0, $this->logHandler->lines());
    }

    public function testMapperHierarchyStatus(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $custom = new class extends ValidationException {};
        $this->assertSame(422, $handler->mapper()->statusFor($custom));
        $this->assertSame(404, $handler->mapper()->statusFor(new RouteNotFoundException()));
        $this->assertSame(500, $handler->mapper()->statusFor(new \RuntimeException('x')));
    }

    public function testCustomMapToException(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $handler->map(\RuntimeException::class, static fn (\RuntimeException $e): RouteNotFoundException => new RouteNotFoundException());
        $result = $handler->render(new \RuntimeException('gone'), 'json');
        $this->assertSame(404, $result->status);
    }

    public function testJsonRendererRfc9457InDebug(): void
    {
        $config = new \Tavaan\Engine\Config\Repository([
            'error' => ['rendering' => ['debug' => true, 'json_format' => 'rfc9457']],
            'app' => ['env' => 'local'],
        ]);
        $handler = new ExceptionHandler($this->logger, $config);

        $exception = new RouteNotFoundException(context: ['path' => '/x', 'password' => 'hidden!']);
        $result = $handler->render($exception, 'json');

        $this->assertSame(404, $result->status);
        $this->assertStringContainsString('application/problem+json', $result->contentType);

        $body = \json_decode($result->body, true);
        $this->assertSame(404, $body['status']);
        $this->assertSame('http.route.not_found', $body['messageId']);
        $this->assertSame($exception->refCode, $body['refCode']);
        $this->assertArrayHasKey('X-Tavaan-Ref', $result->headers);
        $this->assertStringNotContainsString('hidden!', $result->body, 'context must be masked');
        $this->assertStringContainsString('********', $result->body);
    }

    public function testProductionHidesDetails(): void
    {
        $config = new \Tavaan\Engine\Config\Repository([
            'error' => ['rendering' => ['debug' => false]],
            'app' => ['env' => 'production'],
        ]);
        $handler = new ExceptionHandler($this->logger, $config);
        $result = $handler->render(new \RuntimeException('secret internal detail'), 'json');
        $this->assertStringNotContainsString('secret internal detail', $result->body);
    }

    public function testCliRendererContainsRefCode(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $result = $handler->render(new RouteNotFoundException(), 'cli');
        $this->assertStringContainsString('RouteNotFoundException', $result->body);
        $this->assertStringContainsString('http.route.not_found', $result->body);
    }

    public function testRenderableCallbackWins(): void
    {
        $handler = new ExceptionHandler($this->logger);
        $handler->renderable(static fn (\Throwable $e): ?string => $e instanceof ValidationException ? 'custom-render' : null);
        $result = $handler->render(new ValidationException(['a' => ['b']]), 'html');
        $this->assertSame('custom-render', $result->body);
        $this->assertSame(422, $result->status);
    }

    public function testLevelMapUsesInfoForValidation(): void
    {
        $config = new \Tavaan\Engine\Config\Repository([
            'error' => ['reporting' => ['report_client_errors' => true, 'level_map' => [ValidationException::class => 'info']]],
        ]);
        $handler = new ExceptionHandler($this->logger, $config);
        $handler->report(new ValidationException(['t' => ['required']]));
        $this->assertStringContainsString('.INFO:', $this->logHandler->lines()[0]);
    }
}
