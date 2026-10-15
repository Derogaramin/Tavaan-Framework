<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error;

/**
 * تَوان | Tavaan — Error
 * ErrorCode — جدول یگانهٔ کدهای خطا (DOC-013 §4).
 *
 * ساختار: T<Area><NNN> — مانند TCR001 برای container.binding_not_found.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
final class ErrorCode
{
    /** @var array<string, string> messageId → code */
    private const MAP = [
        'auth.credentials.invalid'        => 'TAU001',
        'auth.token.expired'              => 'TAU002',
        'auth.account.locked'             => 'TAU003',
        'authorization.denied'            => 'TAZ001',
        'config.generic'                  => 'TCF001',
        'config.frozen'                   => 'TCF002',
        'config.not_cacheable'            => 'TCF003',
        'core.env.parse_failed'           => 'TCF004',
        'config.schema_invalid'           => 'TCF005',
        'config.missing_key'              => 'TCF006',
        'container.binding_not_found'     => 'TCR001',
        'container.circular_dependency'   => 'TCR002',
        'container.ambiguous_union'       => 'TCR003',
        'container.untyped_parameter'     => 'TCR004',
        'container.scope_not_active'      => 'TCR005',
        'database.query.failed'           => 'TDB001',
        'database.unique_violation'       => 'TDB002',
        'database.deadlock'               => 'TDB003',
        'engine.not_installed'            => 'TEN001',
        'engine.capability_missing'       => 'TEN002',
        'event.listener_failed'           => 'TEV001',
        'event.max_listeners'             => 'TEV002',
        'event.outbox_unavailable'        => 'TEV003',
        'event.not_allowlisted'           => 'TEV004',
        'core.autoload.unsafe_class_name' => 'TKR001',
        'core.autoload.path_escape'       => 'TKR002',
        'core.autoload.class_not_declared' => 'TKR003',
    ];

    private function __construct()
    {
    }

    /** کد خطای یک Throwable (از messageId در TavaanException). */
    public static function forException(\Throwable $e): ?string
    {
        if ($e instanceof \Tavaan\Core\Exceptions\TavaanException) {
            return self::MAP[$e->messageId] ?? null;
        }

        return null;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::MAP;
    }
}
