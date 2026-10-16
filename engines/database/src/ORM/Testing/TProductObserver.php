<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

/**
 * Fixture تست ORM — Observer با ثبت رویدادها (T-09).
 *
 * @api internal
 */
final class TProductObserver
{
    /** @var list<string> */
    public static array $fired = [];

    public static bool $blockUpdating = false;

    public function creating(TPost $model): void
    {
        self::$fired[] = 'creating';
    }

    public function created(TPost $model): void
    {
        self::$fired[] = 'created';
    }

    public function updating(TPost $model): bool
    {
        self::$fired[] = 'updating';

        return !self::$blockUpdating;
    }

    public function updated(TPost $model): void
    {
        self::$fired[] = 'updated';
    }

    public function saving(TPost $model): void
    {
        self::$fired[] = 'saving';
    }

    public function saved(TPost $model): void
    {
        self::$fired[] = 'saved';
    }

    public function deleting(TPost $model): void
    {
        self::$fired[] = 'deleting';
    }

    public function deleted(TPost $model): void
    {
        self::$fired[] = 'deleted';
    }

    public function retrieved(TPost $model): void
    {
        self::$fired[] = 'retrieved';
    }

    public function restoring(TPost $model): void
    {
        self::$fired[] = 'restoring';
    }

    public function restored(TPost $model): void
    {
        self::$fired[] = 'restored';
    }
}
