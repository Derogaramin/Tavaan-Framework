<?php

declare(strict_types=1);

namespace Tavaan\Core\Application;

/**
 * تَوان | Tavaan — Core
 * RuntimeMode — حالت‌های اجرا (DOC-027 §8).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
enum RuntimeMode: string
{
    case Http = 'http';
    case Console = 'console';
    case Queue = 'queue';
    case Scheduler = 'scheduler';
    case Realtime = 'realtime';
    case Testing = 'testing';
    case Repl = 'repl';
}
