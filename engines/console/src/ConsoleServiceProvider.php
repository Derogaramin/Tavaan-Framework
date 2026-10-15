<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console;

use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Core\Contracts\Console\OutputInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Console\Output\Output;

/**
 * تَوان | Tavaan — Console
 * ConsoleServiceProvider — ثبت Kernel/Output/SignatureParser.
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
final class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConsoleKernel::class, static fn (ContainerInterface $c): ConsoleKernel => new ConsoleKernel($c));
        $this->app->alias(ConsoleKernel::class, KernelInterface::class);
        $this->app->alias(ConsoleKernel::class, 'console.kernel');

        $this->app->singleton(OutputInterface::class, static fn (): OutputInterface => new Output());
        $this->app->singleton(SignatureParser::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ConsoleKernel::class, KernelInterface::class, 'console.kernel', OutputInterface::class];
    }
}
