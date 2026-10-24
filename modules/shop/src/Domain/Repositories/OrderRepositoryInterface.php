<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * OrderRepositoryInterface — پورت ماندگاری سفارش.
 */

namespace Modules\Shop\Domain\Repositories;

use Modules\Shop\Domain\Entities\Order;

/**
 * @api 1.0
 */
interface OrderRepositoryInterface
{
    /** ذخیرهٔ سفارش + اقلام (insert یا update). */
    public function save(Order $order): void;

    /** با اقلام hydrate شده. */
    public function findById(string $id): ?Order;

    public function findByNumber(string $orderNumber): ?Order;

    public function findByAuthority(string $authority): ?Order;

    /** شمارهٔ سفارش بعدی (قابل race — با retry در use case). */
    public function nextOrderNumber(): string;

    public function orderNumberExists(string $orderNumber): bool;

    /**
     * @param array{customer_id?: int|null, status?: string|null, search?: string|null} $filters
     *
     * @return array{rows: list<Order>, total: int} — rows بدون items (برای فهرست)
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** آمار تجمیعی برای داشبورد/گزارش فروش. @return array<string, mixed> */
    public function salesStats(?string $fromDate = null, ?string $toDate = null): array;

    public function delete(string $id): void;
}
