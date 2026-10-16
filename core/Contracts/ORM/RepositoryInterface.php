<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\ORM;

/**
 * تَوان | Tavaan — Contracts
 * RepositoryInterface — قرارداد مخزن (الگوی Repository — DOC-050 §7).
 *
 * 🔒 C2: هیچ جزئیات پیاده‌سازی (مثل PDO) در امضا نشت نمی‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
interface RepositoryInterface
{
    public function findById(int|string $id): ?object;

    public function save(object $entity): void;

    public function delete(int|string $id): void;

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<object>
     */
    public function findBy(array $criteria, int $limit = 50): array;
}
