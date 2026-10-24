<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CouponAdminController — مدیریت کوپن‌های تخفیف (/admin/shop/coupons).
 */

namespace Modules\Shop\Http\Controllers\Admin;

use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Repositories\CouponRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CouponAdminController
{
    public function __construct(private readonly CouponRepositoryInterface $coupons)
    {
    }

    public function index(): mixed
    {
        $this->authorize();

        return View::render('shop::admin.coupons-index', [
            'coupons' => $this->coupons->all(),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize();

        $validated = Validator::validate($request->all(), [
            'code' => ['required', 'string', 'min:3', 'max:50'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'min_cart' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ], attributes: ['code' => 'کد کوپن', 'value' => 'مقدار']);

        $code = \mb_strtoupper(\trim((string) $validated['code']));
        if ($this->coupons->codeExists($code)) {
            Session::flash('error', 'این کد پیش‌تر ثبت شده است.');

        return back();
        }

        $expiresAt = isset($validated['expires_in_days']) && $validated['expires_in_days'] !== null && $validated['expires_in_days'] !== ''
            ? new \DateTimeImmutable('+' . (int) $validated['expires_in_days'] . ' days', new \DateTimeZone('UTC'))
            : null;

        try {
            $coupon = Coupon::create(
                code: $code,
                type: (string) $validated['type'],
                value: (int) $validated['value'],
                maxDiscount: isset($validated['max_discount']) && $validated['max_discount'] !== null && $validated['max_discount'] !== ''
                    ? (int) $validated['max_discount']
                    : null,
                minCart: isset($validated['min_cart']) ? (int) $validated['min_cart'] : 0,
                usageLimit: isset($validated['usage_limit']) && $validated['usage_limit'] !== null && $validated['usage_limit'] !== ''
                    ? (int) $validated['usage_limit']
                    : null,
                expiresAt: $expiresAt,
            );
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        $this->coupons->save($coupon);

        Session::flash('status', 'coupon_created');



        return redirect('/admin/shop/coupons');
    }

    public function deactivate(string $id): mixed
    {
        $this->authorize();

        $coupon = $this->coupons->findById((int) $id);
        if ($coupon === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.coupon_not_found');
        }

        $coupon->deactivate();
        $this->coupons->save($coupon);

        Session::flash('status', 'coupon_deactivated');



        return back();
    }

    public function destroy(string $id): mixed
    {
        $this->authorize();
        $this->coupons->delete((int) $id);

        Session::flash('status', 'coupon_deleted');



        return redirect('/admin/shop/coupons');
    }

    private function authorize(): void
    {
        if (!Gate::allows('shop.discount.manage')) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden');
        }
    }
}
