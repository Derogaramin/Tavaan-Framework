<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Log;

/**
 * سطوح لاگ RFC 5424 با severity عددی.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert     = 'alert';
    case Critical  = 'critical';
    case Error     = 'error';
    case Warning   = 'warning';
    case Notice    = 'notice';
    case Info      = 'info';
    case Debug     = 'debug';

    /** RFC 5424 severity: 0 (emergency) .. 7 (debug) */
    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert     => 1,
            self::Critical  => 2,
            self::Error     => 3,
            self::Warning   => 4,
            self::Notice    => 5,
            self::Info      => 6,
            self::Debug     => 7,
        };
    }

    /** آیا این سطح در آستانهٔ $threshold لاگ می‌شود؟ */
    public function withinThreshold(self $threshold): bool
    {
        return $this->severity() <= $threshold->severity();
    }

    /** @throws \ValueError */
    public static function fromName(string $name): self
    {
        return self::from(\strtolower($name));
    }
}
