<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Workflow
 * WorkflowResolver — دسترسی ایستا (اختیاری) به Container.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class WorkflowResolver
{
    private static ?ContainerInterface $container = null;

    private function __construct()
    {
    }

    public static function set(?ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function container(): ?ContainerInterface
    {
        return self::$container;
    }
}
