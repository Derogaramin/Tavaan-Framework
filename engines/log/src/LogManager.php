<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Log\FormatterInterface;
use Tavaan\Core\Contracts\Log\HandlerInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Log\LogLevel;
use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Core\Error\LogMasker;
use Tavaan\Engine\Log\Exceptions\LogException;
use Tavaan\Engine\Log\Formatters\JsonFormatter;
use Tavaan\Engine\Log\Formatters\LineFormatter;
use Tavaan\Engine\Log\Handlers\ArrayHandler;
use Tavaan\Engine\Log\Handlers\NullHandler;
use Tavaan\Engine\Log\Handlers\RotatingFileHandler;
use Tavaan\Engine\Log\Handlers\StderrHandler;
use Tavaan\Engine\Log\Handlers\StreamHandler;
use Tavaan\Engine\Log\Processors\BacktraceProcessor;
use Tavaan\Engine\Log\Processors\EnvironmentProcessor;
use Tavaan\Engine\Log\Processors\IdentityProcessor;
use Tavaan\Engine\Log\Processors\MaskProcessor;
use Tavaan\Engine\Log\Processors\MemoryProcessor;
use Tavaan\Engine\Log\Processors\RuntimeProcessor;
use Tavaan\Engine\Log\Processors\TraceContextProcessor;

/**
 * تَوان | Tavaan — Log
 * LogManager — مدیریت کانال‌ها (Lazy)، Stack و درایورهای سفارشی.
 *
 * خودش LoggerInterface است: فراخوانی‌های مستقیم به کانال default می‌روند.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class LogManager implements LoggerInterface
{
    /** @var array<string, Logger> */
    private array $channels = [];

    /** @var array<string, \Closure(array<string, mixed>): HandlerInterface> */
    private array $customDrivers = [];

    private string $defaultChannel = 'daily';

    private string $environment = 'production';

    public function __construct(private readonly ?RepositoryInterface $config = null)
    {
        if ($config !== null) {
            $this->defaultChannel = (string) $config->get('log.default', 'daily');
            $this->environment = (string) $config->get('app.env', 'production');
        }
    }

    // ─── کانال‌ها ─────────────────────────────────────────────────────────

    public function channel(string $name): Logger
    {
        return $this->channels[$name] ??= $this->resolve($name);
    }

    /** Logger ترکیبی روی چند کانال. */
    public function stack(array $names): Logger
    {
        $key = 'stack:' . \implode(',', $names);
        if (isset($this->channels[$key])) {
            return $this->channels[$key];
        }

        $handlers = [];
        $lowest = LogLevel::Emergency;
        foreach ($names as $name) {
            $logger = $this->channel($name);
            $handlers = [...$handlers, ...$this->handlersOf($logger)];
        }

        return $this->channels[$key] = new Logger($key, $lowest, $handlers, environment: $this->environment);
    }

    /** @return list<string> */
    public function getChannels(): array
    {
        return \array_keys($this->channels);
    }

    /** ثبت درایور سفارشی. @param \Closure(array<string, mixed>): HandlerInterface $factory */
    public function extend(string $driver, \Closure $factory): void
    {
        $this->customDrivers[$driver] = $factory;
    }

    public function flush(): void
    {
        foreach ($this->channels as $channel) {
            $channel->flush();
        }
    }

    // ─── LoggerInterface (کانال default) ──────────────────────────────────

    public function emergency(string $message, array|\Closure $context = []): void
    {
        $this->default()->emergency($message, $context);
    }

    public function alert(string $message, array|\Closure $context = []): void
    {
        $this->default()->alert($message, $context);
    }

    public function critical(string $message, array|\Closure $context = []): void
    {
        $this->default()->critical($message, $context);
    }

    public function error(string $message, array|\Closure $context = []): void
    {
        $this->default()->error($message, $context);
    }

    public function warning(string $message, array|\Closure $context = []): void
    {
        $this->default()->warning($message, $context);
    }

    public function notice(string $message, array|\Closure $context = []): void
    {
        $this->default()->notice($message, $context);
    }

    public function info(string $message, array|\Closure $context = []): void
    {
        $this->default()->info($message, $context);
    }

    public function debug(string $message, array|\Closure $context = []): void
    {
        $this->default()->debug($message, $context);
    }

    public function log(LogLevel|string $level, string $message, array|\Closure $context = []): void
    {
        $this->default()->log($level, $message, $context);
    }

    public function withContext(array $context): Logger
    {
        return $this->default()->withContext($context);
    }

    public function shareContext(array $context): void
    {
        foreach ($this->channels as $channel) {
            $channel->shareContext($context);
        }
        $this->default()->shareContext($context);
    }

    public function withoutContext(): void
    {
        foreach ($this->channels as $channel) {
            $channel->withoutContext();
        }
    }

    public function default(): Logger
    {
        return $this->channel($this->defaultChannel);
    }

    // ─── ساخت کانال از config ─────────────────────────────────────────────

    private function resolve(string $name): Logger
    {
        $config = $this->config === null ? [] : (array) $this->config->get('log.channels.' . $name, []);

        if ($config === [] && $name !== 'null') {
            throw LogException::channelNotFound($name);
        }

        $driver = (string) ($config['driver'] ?? 'null');

        if ($driver === 'stack') {
            return $this->stack((array) ($config['channels'] ?? []));
        }

        $enabled = (bool) ($config['enabled'] ?? true);
        $handlers = $enabled ? [$this->makeHandler($driver, $config)] : [new NullHandler()];

        $threshold = LogLevel::fromName((string) ($config['level'] ?? 'debug'));
        $formatter = $this->makeFormatter((string) ($config['formatter'] ?? 'line'));
        $processors = $this->makeProcessors((array) ($config['processors'] ?? ['mask', 'trace', 'identity']));
        $limits = $this->config === null ? [] : (array) $this->config->get('log.limits', []);

        return new Logger(
            channelName: $name,
            threshold: $threshold,
            handlers: $handlers,
            processors: $processors,
            formatter: $formatter,
            limits: $limits,
            environment: $this->environment,
            buffered: (bool) ($config['buffer']['enabled'] ?? false),
            bufferMaxLines: (int) ($config['buffer']['max_lines'] ?? 50),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function makeHandler(string $driver, array $config): HandlerInterface
    {
        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($config);
        }

        return match ($driver) {
            'single'   => new StreamHandler(
                (string) ($config['path'] ?? storage_path('logs/tavaan.log')),
                (int) ($config['permission'] ?? 0640),
                (bool) ($config['locking'] ?? true),
            ),
            'daily'    => new RotatingFileHandler(
                (string) ($config['path'] ?? storage_path('logs/tavaan.log')),
                (int) ($config['days'] ?? 14),
                (int) ($config['permission'] ?? 0640),
                (bool) ($config['locking'] ?? true),
            ),
            'stderr'   => new StderrHandler(),
            'array'    => new ArrayHandler(),
            'null'     => new NullHandler(),
            default    => throw LogException::channelNotFound('driver:' . $driver),
        };
    }

    private function makeFormatter(string $name): FormatterInterface
    {
        return match ($name) {
            'json' => new JsonFormatter(),
            default => new LineFormatter(),
        };
    }

    /**
     * @param list<string> $names
     *
     * @return list<ProcessorInterface>
     */
    private function makeProcessors(array $names): array
    {
        $masker = new LogMasker(
            (array) ($this->config?->get('log.masking.keys', []) ?? []),
            (array) ($this->config?->get('log.masking.patterns', []) ?? []),
        );

        $processors = [];
        foreach ($names as $name) {
            $processor = match ($name) {
                'mask'        => new MaskProcessor($masker),
                'trace'       => new TraceContextProcessor(),
                'identity'    => new IdentityProcessor(),
                'memory'      => new MemoryProcessor(),
                'runtime'     => new RuntimeProcessor(),
                'backtrace'   => new BacktraceProcessor(),
                'environment' => new EnvironmentProcessor(),
                default       => null,
            };
            if ($processor !== null) {
                $processors[] = $processor;
            }
        }

        return $processors;
    }

    /** @return list<HandlerInterface> */
    private function handlersOf(Logger $logger): array
    {
        $ref = new \ReflectionProperty(Logger::class, 'handlers');

        /** @var list<HandlerInterface> $handlers */
        $handlers = $ref->getValue($logger);

        return $handlers;
    }
}
