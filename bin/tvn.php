#!/usr/bin/env php
<?php

/**
 * تَوان | Tavaan — CLI Entry Point
 * تنها require دستی: core/bootstrap.php
 *
 * @see docs/04-structure/cli-reference.md
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Contracts\Console\KernelInterface;
use Tavaan\Engine\Console\Input\ArgvInput;
use Tavaan\Engine\Console\Output\Output;

require \dirname(__DIR__) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();

/** @var KernelInterface $kernel */
$kernel = $app->make(KernelInterface::class);

exit($kernel->handle(new ArgvInput(\array_slice($argv, 1)), new Output()));
