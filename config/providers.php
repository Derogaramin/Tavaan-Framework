<?php

declare(strict_types=1);

/*
 * فهرست ServiceProviderها — ترتیب = ترتیب register (DOC-027 §5: providers.php → list).
 */

return [
    \Tavaan\Engine\Container\ContainerServiceProvider::class,
    \Tavaan\Engine\Config\ConfigServiceProvider::class,
    \Tavaan\Engine\Log\LogServiceProvider::class,
    \Tavaan\Engine\Event\EventServiceProvider::class,
    \Tavaan\Engine\Error\ErrorServiceProvider::class,
    \Tavaan\Engine\Pipeline\PipelineServiceProvider::class,
    \Tavaan\Engine\Compat\CompatServiceProvider::class,
    \Tavaan\Engine\Module\ModuleServiceProvider::class,
    \Tavaan\Engine\Optimizer\OptimizerServiceProvider::class,
    \Tavaan\Engine\Console\ConsoleServiceProvider::class,
];
