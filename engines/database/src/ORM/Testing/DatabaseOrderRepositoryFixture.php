<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\ORM\RepositoryInterface;

/**
 * Fixture تست ORM — پیاده‌سازی Repository روی ConnectionInterface (DOC-050 §7).
 *
 * @api internal
 */
final class DatabaseOrderRepositoryFixture implements RepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function findById(int|string $id): ?object
    {
        $row = $this->db->table('shop_orders')->where('id', $id)->first();

        return $row === null ? null : OrderFixture::fromRow($row);
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof OrderFixture) {
            throw new \InvalidArgumentException('Expected OrderFixture entity.');
        }

        $this->db->table('shop_orders')->updateOrInsert(['id' => $entity->id()], $entity->toRow());
    }

    public function delete(int|string $id): void
    {
        $this->db->table('shop_orders')->where('id', $id)->delete();
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<OrderFixture>
     */
    public function findBy(array $criteria, int $limit = 50): array
    {
        $query = $this->db->table('shop_orders');
        foreach ($criteria as $column => $value) {
            $query->where((string) $column, $value);
        }

        return \array_map(
            static fn (array $row): OrderFixture => OrderFixture::fromRow($row),
            $query->limit($limit)->get(),
        );
    }
}
