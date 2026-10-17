@extends('layouts.app')

@section('title', 'دمو — پرداخت (M7)')

@section('content')
    <div class="card">
        <h3 style="margin-top:0">💳 دمو پرداخت — کیف پول داخلی</h3>
        <p style="color:#94a3b8">
            موتور Payment (DOC-073) — درگاه <code>wallet</code> بدون هیچ تماس شبکه‌ای.
            چرخهٔ کامل: purchase → verify (🔒 با مبلغ محلی تراکنش) → refund.
        </p>

        @if ($error !== null)
            <p style="color:#f87171">⚠️ {{ $error }}</p>
        @endif

        @if ($steps !== [])
            <table style="width:100%;border-collapse:collapse">
                <tr style="color:#94a3b8"><th align="right" style="padding:6px">مرحله</th><th align="right" style="padding:6px">نتیجه</th></tr>
                @foreach ($steps as $step)
                    <tr>
                        <td style="padding:6px;border-top:1px solid #1f2937"><code>{{ $step[0] }}</code></td>
                        <td style="padding:6px;border-top:1px solid #1f2937">{{ $step[1] }}</td>
                    </tr>
                @endforeach
            </table>

            <p style="margin-top:12px">
                موجودی کیف پول:
                پیش از پرداخت <b>{{ number_format((float) ($balances['before'] ?? 0)) }}</b> ←
                پس از پرداخت <b>{{ number_format((float) ($balances['after_pay'] ?? 0)) }}</b> ←
                پس از بازگشت <b>{{ number_format((float) ($balances['after_refund'] ?? 0)) }}</b>
                <span style="color:#64748b">(تومان)</span>
            </p>
        @endif
    </div>

    @if ($transactionId !== null)
        <div class="card">
            <h3 style="margin-top:0">🧾 تراکنش</h3>
            <p>شناسه: <code>{{ $transactionId }}</code></p>
            <p><a href="{{ route('payment.result') }}?transaction={{ $transactionId }}&status=refunded">مشاهدهٔ صفحهٔ نتیجه (مقصد callback) ←</a></p>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top:0">🏦 درگاه‌های ثبت‌شده</h3>
        <p style="color:#94a3b8;font-size:13px">
            zarinpal • idpay • payping • saman • behpardakht • parsian • sadad • melli • stripe • wallet
            — همه با یک <code>GatewayInterface</code> مشترک (AC-PAY-10).
            برای درگاه واقعی، اعتبارنامه را در <code>.env</code> تنظیم کنید.
        </p>
    </div>

    <p><a href="{{ route('home') }}">→ بازگشت به خانه</a></p>
@endsection
