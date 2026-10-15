<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session;

/**
 * تَوان | Tavaan — Session
 * SessionHandlerInterface — قرارداد درایورهای ذخیرهٔ نشست.
 *
 * 🔒 سریال‌سازی فقط JSON (نه serialize() — Object Injection ممنوع).
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
interface SessionHandlerInterface
{
    /** @return array<string, mixed> */
    public function read(string $id): array;

    /** @param array<string, mixed> $data */
    public function write(string $id, array $data): bool;

    public function destroy(string $id): bool;

    /** Garbage Collection — تعداد رکوردهای حذف‌شده. */
    public function gc(int $maxLifetimeSeconds): int;
}
