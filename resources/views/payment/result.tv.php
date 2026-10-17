@extends('layouts.app')

@section('title', 'نتیجهٔ پرداخت')

@section('content')
    <div class="card" style="text-align:center">
        @if ($tx !== null)
            @if ($tx->isSuccess())
                <h1 style="color:#4ade80;margin:8px 0">پرداخت موفق ✅</h1>
            @elseif ($tx->isRefunded())
                <h1 style="color:#f59e0b;margin:8px 0">بازگشت داده شد ↩️</h1>
            @elseif ($tx->isPending())
                <h1 style="color:#38bdf8;margin:8px 0">در انتظار تأیید ⏳</h1>
            @else
                <h1 style="color:#f87171;margin:8px 0">پرداخت ناموفق ❌</h1>
            @endif

            <table style="width:100%;border-collapse:collapse;text-align:right;max-width:480px;margin:0 auto">
                <tr><td style="padding:6px;color:#94a3b8">شناسهٔ تراکنش</td><td style="padding:6px"><code>{{ $tx->getKey() }}</code></td></tr>
                <tr><td style="padding:6px;color:#94a3b8;border-top:1px solid #1f2937">درگاه</td><td style="padding:6px;border-top:1px solid #1f2937">{{ $tx->gateway() }}</td></tr>
                <tr><td style="padding:6px;color:#94a3b8;border-top:1px solid #1f2937">مبلغ</td><td style="padding:6px;border-top:1px solid #1f2937">{{ number_format((float) $tx->money()->amount) }} تومان</td></tr>
                <tr><td style="padding:6px;color:#94a3b8;border-top:1px solid #1f2937">کد رهگیری</td><td style="padding:6px;border-top:1px solid #1f2937"><code>{{ $tx->referenceId() ?? '—' }}</code></td></tr>
                <tr><td style="padding:6px;color:#94a3b8;border-top:1px solid #1f2937">کارت</td><td style="padding:6px;border-top:1px solid #1f2937"><code>{{ $tx->cardPan() ?? '—' }}</code> 🔒 mask</td></tr>
            </table>
        @else
            <h1 style="color:#94a3b8;margin:8px 0">تراکنش یافت نشد</h1>
            @if ($error !== null)
                <p style="color:#f87171;font-size:13px">{{ $error }}</p>
            @elseif ($id === '')
                <p style="color:#64748b">این صفحه مقصد redirect پس از callback درگاه است
                    (<code>payment.result_url</code> با <code>{id}</code> و <code>{status}</code>).</p>
            @else
                <p style="color:#64748b">شناسهٔ <code>{{ $id }}</code> در پایگاه داده نیست — ممکن است <code>tvn migrate</code> اجرا نشده باشد.</p>
            @endif
        @endif

        <p style="margin-top:16px"><a href="{{ route('demo.payment') }}">دموی پرداخت</a> • <a href="{{ route('home') }}">خانه</a></p>
    </div>
@endsection
