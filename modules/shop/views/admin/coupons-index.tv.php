@extends('shop::layouts.admin')

@section('title', 'کوپن‌ها')

@section('content')
    <div class="topline"><h1>🎟 کوپن‌های تخفیف</h1></div>

    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:10px">+ کوپن جدید</h2>
        <form action="/admin/shop/coupons" method="POST" class="form-grid">
            @csrf
            <label>کد *<input type="text" name="code" required placeholder="BAHAR1405"></label>
            <label>نوع *
                <select name="type" required>
                    <option value="percent">درصدی</option>
                    <option value="fixed">مبلغ ثابت (ریال)</option>
                </select>
            </label>
            <label>مقدار * (درصد ۱..۱۰۰ یا ریال)<input type="number" name="value" required min="1"></label>
            <label>سقف تخفیف (ریال، درصدی)<input type="number" name="max_discount" min="0"></label>
            <label>حداقل سبد (ریال)<input type="number" name="min_cart" min="0" value="0"></label>
            <label>ظرفیت مصرف<input type="number" name="usage_limit" min="1"></label>
            <label>انقضا (روز)<input type="number" name="expires_in_days" min="1" placeholder="مثلاً ۳۰"></label>
            <div class="full"><button class="btn" type="submit">ساخت کوپن</button></div>
        </form>
    </div>

    <div class="card" style="padding:0">
        <table class="data">
            <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>سقف</th><th>حداقل سبد</th><th>مصرف</th><th>انقضا</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @forelse($coupons as $coupon)
                <tr>
                    <td><strong>{{ $coupon->code() }}</strong></td>
                    <td>{{ $coupon->type() === 'percent' ? 'درصدی' : 'ثابت' }}</td>
                    <td>{{ $coupon->type() === 'percent' ? trans_number($coupon->value()) . '٪' : trans_number($coupon->value()) . ' ریال' }}</td>
                    <td>{{ $coupon->maxDiscount() !== null ? trans_number($coupon->maxDiscount()) : '—' }}</td>
                    <td>{{ trans_number($coupon->minCart()) }}</td>
                    <td>{{ trans_number($coupon->usedCount()) }}{{ $coupon->usageLimit() !== null ? ' / ' . trans_number($coupon->usageLimit()) : '' }}</td>
                    <td>{{ $coupon->expiresAt() !== null ? jalali($coupon->expiresAt(), 'Y/m/d') : '—' }}</td>
                    <td><span class="badge {{ $coupon->isActive() ? 'green' : 'red' }}">{{ $coupon->isActive() ? 'فعال' : 'غیرفعال' }}</span></td>
                    <td>
                        @if($coupon->isActive())
                            <form action="/admin/shop/coupons/{{ $coupon->id() }}/deactivate" method="POST" class="inline">
                                @csrf
                                <button class="btn ghost sm" type="submit">غیرفعال</button>
                            </form>
                        @endif
                        <form action="/admin/shop/coupons/{{ $coupon->id() }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger sm" type="submit" onclick="return confirm('حذف کوپن؟')">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">کوپنی وجود ندارد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
