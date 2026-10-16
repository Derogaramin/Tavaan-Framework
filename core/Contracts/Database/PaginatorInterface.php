<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * PaginatorInterface — قرارداد صفحه‌بندی (DOC-053).
 *
 * @api 1.0
 * @see docs/03-engines/pagination.md
 */
interface PaginatorInterface extends \Tavaan\Core\Contracts\Support\Arrayable, \Tavaan\Core\Contracts\Support\Jsonable
{
    /** @return list<mixed> */
    public function items(): array;

    public function total(): int;

    public function perPage(): int;

    public function currentPage(): int;

    public function lastPage(): int;

    public function hasMorePages(): bool;

    public function hasPages(): bool;

    public function onFirstPage(): bool;

    public function from(): ?int;

    public function to(): ?int;

    /** @return array<string, mixed> */
    public function meta(): array;
}
