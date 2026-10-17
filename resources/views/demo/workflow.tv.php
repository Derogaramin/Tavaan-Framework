@extends('layouts.app')

@section('title', 'دمو — گردش‌کار (M7)')

@section('content')
    <div class="card">
        <h3 style="margin-top:0">🔁 دمو گردش‌کار — فرایند «تأیید سفارش»</h3>
        <p style="color:#94a3b8">
            موتور Workflow (DOC-071) — درایور array (بدون DB).
            فرایند: <code>draft → pending_review → check_amount → approved → completed</code>
            با کار انسانی و دروازهٔ انحصاری (مبلغ &lt; ۱۰٬۰۰۰٬۰۰۰).
        </p>

        <table style="width:100%;border-collapse:collapse">
            <tr style="color:#94a3b8"><th align="right" style="padding:6px">گام</th><th align="right" style="padding:6px">وضعیت پس از اجرا</th></tr>
            @foreach ($steps as $step)
                <tr>
                    <td style="padding:6px;border-top:1px solid #1f2937"><code>{{ $step[0] }}</code></td>
                    <td style="padding:6px;border-top:1px solid #1f2937">{{ $step[1] }}</td>
                </tr>
            @endforeach
        </table>

        <p>
            تکمیل‌شده؟
            @if ($completed)
                <b style="color:#4ade80">بله ✅</b>
            @else
                <b style="color:#f87171">خیر</b>
            @endif
        </p>
    </div>

    <div class="card">
        <h3 style="margin-top:0">📜 تاریخچه (History)</h3>
        <table style="width:100%;border-collapse:collapse">
            <tr style="color:#94a3b8">
                <th align="right" style="padding:6px">از</th>
                <th align="right" style="padding:6px">به</th>
                <th align="right" style="padding:6px">گذار</th>
            </tr>
            @foreach ($history as $row)
                <tr>
                    <td style="padding:6px;border-top:1px solid #1f2937">{{ $row['from_state'] }}</td>
                    <td style="padding:6px;border-top:1px solid #1f2937">{{ $row['to_state'] }}</td>
                    <td style="padding:6px;border-top:1px solid #1f2937"><code>{{ $row['transition'] }}</code></td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🗺️ نمودار فرایند (Visualizer — AC-WF-11)</h3>
        <p style="color:#94a3b8;font-size:13px">خروجی Mermaid — در مرورگرهای دارای شبکه رندر می‌شود؛ متن آن همیشه قابل کپی است.</p>
        <pre class="mermaid" style="background:#0b1120;padding:12px;border-radius:8px;direction:ltr;text-align:left">{{ $mermaid }}</pre>
    </div>

    <p><a href="{{ route('home') }}">→ بازگشت به خانه</a></p>
@endsection

@push('scripts')
<script type="module">
    try {
        const m = await import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs');
        m.default.initialize({ startOnLoad: true, theme: 'dark' });
    } catch (e) { /* بدون شبکه — فقط متن نمودار نمایش داده می‌شود */ }
</script>
@endpush
