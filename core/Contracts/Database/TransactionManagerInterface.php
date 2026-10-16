<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * TransactionManagerInterface — قرارداد مدیر تراکنش با Nesting/Savepoint (DOC-047 §7).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
interface TransactionManagerInterface
{
    /**
     * @param \Closure(static): mixed $callback
     */
    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed;

    public function begin(?string $isolation = null): void;

    public function commit(): void;

    public function rollBack(?int $toLevel = null): void;

    public function level(): int;
}
