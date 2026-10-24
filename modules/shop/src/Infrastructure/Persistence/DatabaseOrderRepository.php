<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * DatabaseOrderRepository — آداپتر سفارش (shop_orders + shop_order_items).
 */

namespace Modules\Shop\Infrastructure\Persistence;

use Modules\Shop\Domain\Entities\Order;
use Modules\Shop\Domain\Entities\OrderItem;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class DatabaseOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        $row = $order->toRow();
        $exists = DB::table('shop_orders')->where('id', $order->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('shop_orders')->where('id', $order->id())->update($row);
        } else {
            DB::table('shop_orders')->insert($row);
        }

        // اقلام — جایگزینی کامل (سفارش تنها در place ساخته می‌شود)
        if ($order->items() !== [] && !$exists) {
            foreach ($order->items() as $item) {
                DB::table('shop_order_items')->insert($item->toRow($order->id()));
            }
        }
    }

    public function findById(string $id): ?Order
    {
        $row = DB::table('shop_orders')->where('id', $id)->first();

        return $row === null ? null : Order::fromRow($row, $this->itemsOf($id));
    }

    public function findByNumber(string $orderNumber): ?Order
    {
        $row = DB::table('shop_orders')->where('order_number', $orderNumber)->first();

        return $row === null ? null : Order::fromRow($row, $this->itemsOf((string) $row['id']));
    }

    public function findByAuthority(string $authority): ?Order
    {
        if ($authority === '') {
            return null;
        }
        $row = DB::table('shop_orders')->where('payment_authority', $authority)->first();

        return $row === null ? null : Order::fromRow($row, $this->itemsOf((string) $row['id']));
    }

    public function nextOrderNumber(): string
    {
        // SH-<yyyymmdd>-<random4> — یکتایی با orderNumberExists تضمین و retry می‌شود
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd');

        do {
            $number = 'SH-' . $date . '-' . \strtoupper(\substr(\bin2hex(\random_bytes(3)), 0, 5));
        } while ($this->orderNumberExists($number));

        return $number;
    }

    public function orderNumberExists(string $orderNumber): bool
    {
        return DB::table('shop_orders')->where('order_number', $orderNumber)->exists();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $query = DB::table('shop_orders');
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }
        if (isset($filters['status']) && \is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['search']) && \is_string($filters['search']) && \trim($filters['search']) !== '') {
            $term = \trim($filters['search']);
            $query->where(static function ($q) use ($term): void {
                $q->where('order_number', 'like', '%' . $term . '%')
                    ->orWhere('tracking_code', 'like', '%' . $term . '%');
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        // items دسته‌ای برای ردیف‌های صفحه (بدون N+1)
        $ids = \array_map(static fn (array $r): string => (string) $r['id'], $rows);
        $itemsMap = $this->itemsForOrders($ids);

        $orders = [];
        foreach ($rows as $row) {
            $orders[] = Order::fromRow($row, $itemsMap[(string) $row['id']] ?? []);
        }

        return ['rows' => $orders, 'total' => $total];
    }

    public function salesStats(?string $fromDate = null, ?string $toDate = null): array
    {
        $byStatus = DB::table('shop_orders')
            ->select('status')
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw('SUM(total) AS sum_total')
            ->groupBy('status')
            ->get();

        $stats = ['by_status' => [], 'paid_total' => 0, 'paid_count' => 0];
        foreach ($byStatus as $row) {
            $stats['by_status'][(string) $row['status']] = [
                'count' => (int) $row['cnt'],
                'total' => (int) ($row['sum_total'] ?? 0),
            ];
        }

        $paidQuery = DB::table('shop_orders')->whereIn('status', ['paid', 'shipped', 'delivered']);
        if ($fromDate !== null) {
            $paidQuery->where('paid_at', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $paidQuery->where('paid_at', '<=', $toDate);
        }
        $paid = $paidQuery->selectRaw('COUNT(*) AS cnt')->selectRaw('SUM(total) AS sum_total')->first();
        $stats['paid_count'] = (int) ($paid['cnt'] ?? 0);
        $stats['paid_total'] = (int) ($paid['sum_total'] ?? 0);

        return $stats;
    }

    public function delete(string $id): void
    {
        DB::table('shop_order_items')->where('order_id', $id)->delete();
        DB::table('shop_orders')->where('id', $id)->delete();
    }

    /** @return list<OrderItem> */
    private function itemsOf(string $orderId): array
    {
        return $this->itemsForOrders([$orderId])[$orderId] ?? [];
    }

    /**
     * @param list<string> $orderIds
     *
     * @return array<string, list<OrderItem>>
     */
    private function itemsForOrders(array $orderIds): array
    {
        $orderIds = \array_values(\array_unique(\array_map('strval', $orderIds)));
        if ($orderIds === []) {
            return [];
        }

        $rows = DB::table('shop_order_items')->whereIn('order_id', $orderIds)->orderBy('id')->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['order_id']][] = OrderItem::fromRow($row);
        }

        return $map;
    }
}
