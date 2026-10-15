<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Log\LogLevel;
use Tavaan\Core\Error\LogMasker;
use Tavaan\Core\Events\FrameworkEvents\ExceptionOccurred;
use Tavaan\Core\Exceptions\Http\HttpException;
use Tavaan\Core\Exceptions\ReportableException;
use Tavaan\Core\Exceptions\RenderableException;
use Tavaan\Core\Exceptions\TavaanException;
use Tavaan\Engine\Error\Renderers\CliRenderer;
use Tavaan\Engine\Error\Renderers\DebugRenderer;
use Tavaan\Engine\Error\Renderers\HtmlRenderer;
use Tavaan\Engine\Error\Renderers\JsonRenderer;

/**
 * تَوان | Tavaan — Error
 * ExceptionHandler — گزارش و رندر یکپارچهٔ استثناها (DOC-035 §4).
 *
 * جریان: prepare (mapper) → shouldReport → report (log + event) → render
 * (Renderable → callbacks → negotiation: Cli/Json/Debug/Html).
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class ExceptionHandler
{
    /** @var list<class-string<\Throwable>> */
    private array $dontReport = [];

    /** @var array<class-string<\Throwable>, LogLevel> */
    private array $levels = [];

    /** @var list<\Closure(\Throwable): (mixed|null)> */
    private array $renderCallbacks = [];

    /** @var list<\Closure(\Throwable): void> */
    private array $reportCallbacks = [];

    /** @var list<\Closure(): array<string, mixed>> */
    private array $contextProviders = [];

    private ExceptionMapper $mapper;

    private bool $debug = false;

    private string $environment = 'production';

    private string $jsonFormat = 'rfc9457';

    private bool $showRefCode = true;

    private ?string $supportUrl = null;

    private bool $reportClientErrors = false;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?RepositoryInterface $config = null,
        private readonly ?DispatcherInterface $events = null,
    ) {
        $this->mapper = new ExceptionMapper();

        if ($config !== null) {
            $this->environment = (string) $config->get('error.environment', $config->get('app.env', 'production'));
            $this->debug = (bool) $config->get('error.rendering.debug', $config->get('app.debug', false));
            $this->jsonFormat = (string) $config->get('error.rendering.json_format', 'rfc9457');
            $this->showRefCode = (bool) $config->get('error.rendering.show_ref_code', true);
            $supportUrl = $config->get('error.rendering.support_url');
            $this->supportUrl = \is_string($supportUrl) ? $supportUrl : null;
            $this->reportClientErrors = (bool) $config->get('error.reporting.report_client_errors', false);

            /** @var array<class-string<\Throwable>, string> $levelMap */
            $levelMap = (array) $config->get('error.reporting.level_map', []);
            foreach ($levelMap as $class => $level) {
                $this->levels[$class] = LogLevel::fromName((string) $level);
            }

            /** @var list<class-string<\Throwable>> $dontReport */
            $dontReport = (array) $config->get('error.reporting.dont_report', []);
            $this->dontReport = $dontReport;
        }
    }

    // ─── تنظیم ────────────────────────────────────────────────────────────

    /** نگاشت استثنا → وضعیت/استثنای دیگر. */
    public function map(string $exceptionClass, int|\Closure $to): void
    {
        $this->mapper->map($exceptionClass, $to);
    }

    /** Callback رندر سفارشی (بازگرداندن non-null = پاسخ نهایی). */
    public function renderable(\Closure $callback): void
    {
        $this->renderCallbacks[] = $callback;
    }

    /** Callback گزارش سفارشی. */
    public function reportable(\Closure $callback): void
    {
        $this->reportCallbacks[] = $callback;
    }

    /** @param list<class-string<\Throwable>> $exceptions */
    public function dontReport(array $exceptions): void
    {
        $this->dontReport = [...$this->dontReport, ...$exceptions];
    }

    public function level(string $exceptionClass, LogLevel $level): void
    {
        $this->levels[$exceptionClass] = $level;
    }

    /** Context افزودنی برای همهٔ گزارش‌ها. */
    public function context(\Closure $provider): void
    {
        $this->contextProviders[] = $provider;
    }

    // ─── گزارش ────────────────────────────────────────────────────────────

    /**
     * گزارش استثنا (لاگ + رویداد + callbackها).
     */
    public function report(\Throwable $e): void
    {
        $e = $this->mapper->prepare($e);

        if (!$this->shouldReport($e)) {
            return;
        }

        foreach ($this->reportCallbacks as $callback) {
            try {
                $callback($e);
            } catch (\Throwable) {
                // callback گزارش نباید خودش باعث حلقه شود
            }
        }

        $level = $this->levelFor($e);
        $context = $this->buildContext($e);

        $this->logger?->log($level, $this->messageKey($e), $context);

        if ($this->events !== null) {
            try {
                $this->events->dispatch(new ExceptionOccurred($e));
            } catch (\Throwable) {
                // dispatch رویداد خطا نباید چرخه بسازد
            }
        }
    }

    public function shouldReport(\Throwable $e): bool
    {
        foreach ($this->dontReport as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        if ($e instanceof ReportableException && !$e->shouldReport()) {
            return false;
        }

        $status = $this->mapper->statusFor($e);
        if ($status < 500 && !$this->reportClientErrors) {
            return false;
        }

        return true;
    }

    // ─── رندر ─────────────────────────────────────────────────────────────

    /**
     * رندر استثنا.
     *
     * @param string $mode auto|json|html|cli
     */
    public function render(\Throwable $e, string $mode = 'auto'): RenderResult
    {
        $e = $this->mapper->prepare($e);

        // 1) Renderable
        if ($e instanceof RenderableException) {
            $rendered = $e->render();
            if ($rendered instanceof RenderResult) {
                return $rendered;
            }
            if (\is_string($rendered)) {
                return new RenderResult($this->mapper->statusFor($e), $rendered);
            }
        }

        // 2) Callbackهای سفارشی
        foreach ($this->renderCallbacks as $callback) {
            $result = $callback($e);
            if ($result instanceof RenderResult) {
                return $result;
            }
            if (\is_string($result)) {
                return new RenderResult($this->mapper->statusFor($e), $result);
            }
        }

        // 3) Negotiation
        $status = $this->mapper->statusFor($e);
        $renderer = $mode === 'auto' ? $this->negotiate($e) : $mode;

        return match ($renderer) {
            'cli'   => (new CliRenderer($this->debug))->render($e),
            'json'  => (new JsonRenderer($this->debug, $this->jsonFormat === 'rfc9457'))->render($e, $status),
            'debug' => (new DebugRenderer())->render($e, $status),
            default => (new HtmlRenderer($this->showRefCode, $this->supportUrl))->render($e, $status),
        };
    }

    /** گزارش + رندر با هم (میان‌بر Kernel). */
    public function handle(\Throwable $e, string $mode = 'auto'): RenderResult
    {
        $this->report($e);

        return $this->render($e, $mode);
    }

    public function mapper(): ExceptionMapper
    {
        return $this->mapper;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function negotiate(\Throwable $e): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }

        if ($this->debug && $this->environment !== 'production') {
            return 'debug';
        }

        // بدون Request Engine (M1): JSON برای مسیرهای /api یا Accept: json
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (\str_contains((string) $accept, 'json') || \str_starts_with((string) $uri, '/api/')) {
            return 'json';
        }

        return 'html';
    }

    private function levelFor(\Throwable $e): LogLevel
    {
        foreach ($this->levels as $class => $level) {
            if ($e instanceof $class) {
                return $level;
            }
        }

        if ($e instanceof HttpException && $e->statusCode < 500) {
            return LogLevel::Info;
        }

        return LogLevel::Error;
    }

    private function messageKey(\Throwable $e): string
    {
        return $e instanceof TavaanException ? $e->messageId : 'exception.unhandled';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(\Throwable $e): array
    {
        $base = $e instanceof TavaanException
            ? $e->reportContext()
            : [
                'exception' => $e::class,
                'message'   => LogMasker::maskString($e->getMessage()),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ];

        foreach ($this->contextProviders as $provider) {
            try {
                $base = [...$base, ...LogMasker::mask($provider())];
            } catch (\Throwable) {
                // context provider خراب نباید گزارش را بشکند
            }
        }

        return $base;
    }
}
