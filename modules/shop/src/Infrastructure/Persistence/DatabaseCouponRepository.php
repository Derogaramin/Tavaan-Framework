<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * DatabaseCouponRepository — آداپتر کوپن‌ها (shop_coupons).
 */

namespace Modules\Shop\Infrastructure\Persistence;

use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Repositories\CouponRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 */
final class DatabaseCouponRepository implements CouponRepositoryInterface
{
    public function save(Coupon $coupon): int
    {
        if ($coupon->id() !== null) {
            DB::table('shop_coupons')->where('id', $coupon->id())->update($coupon->toRow());

            return $coupon->id();
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        return (int) DB::table('shop_coupons')->insertGetId([...$coupon->toRow(), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function findById(int $id): ?Coupon
    {
        $row = DB::table('shop_coupons')->where('id', $id)->first();

        return $row === null ? null : Coupon::fromRow($row);
    }

    public function findByCode(string $code): ?Coupon
    {
        $row = DB::table('shop_coupons')->where('code', \mb_strtoupper(\trim($code)))->first();

        return $row === null ? null : Coupon::fromRow($row);
    }

    public function findActiveByCode(string $code): ?Coupon
    {
        $row = DB::table('shop_coupons')
            ->where('code', \mb_strtoupper(\trim($code)))
            ->where('is_active', true)
            ->first();

        return $row === null ? null : Coupon::fromRow($row);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $query = DB::table('shop_coupons')->where('code', \mb_strtoupper(\trim($code)));
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function all(int $limit = 200): array
    {
        $rows = DB::table('shop_coupons')->orderByDesc('id')->limit(\max(1, $limit))->get();

        return \array_map(static fn (array $row): Coupon => Coupon::fromRow($row), $rows);
    }

    public function incrementUsage(Coupon $coupon): void
    {
        if ($coupon->id() === null) {
            return;
        }
        DB::table('shop_coupons')->where('id', $coupon->id())->update([
            'used_count' => DB::raw('used_count + 1'),
        ]);
    }

    public function delete(int $id): void
    {
        DB::table('shop_coupons')->where('id', $id)->delete();
    }
}
