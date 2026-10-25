@extends('erp::layouts.admin')

@section('title', 'دوره‌های مالی')

@section('content')
<div class="topline"><h1>📅 دوره‌های مالی</h1></div>

<div class="card">
    <table class="data">
        <tr><th>عنوان</th><th>از</th><th>تا</th><th>وضعیت</th><th>سود/زیان انباشته تا پایان دوره</th><th></th></tr>
        @forelse($periods as $period)
            <tr>
                <td>{{ $period->title() }}</td>
                <td class="num">{{ $period->startDate() }}</td>
                <td class="num">{{ $period->endDate() }}</td>
                <td>
                    @if($period->isOpen())<span class="badge green">باز</span>@else<span class="badge red">بسته</span>@endif
                </td>
                <td class="num">
                    @if(isset($previews[$period->id()]) && $previews[$period->id()] !== null)
                        {{ trans_number($previews[$period->id()]['net_income']) }} ریال
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($period->isOpen())
                        <form method="post" action="/admin/erp/periods/{{ $period->id() }}/close" class="inline" onsubmit="return confirm('دوره بسته شود؟ پس از بستن، سند جدیدی در این بازه ثبت نمی‌شود.')">
                            @csrf<button class="btn sm danger" type="submit">بستن دوره</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">دوره‌ای تعریف نشده است.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">➕ دورهٔ جدید</h2>
    <form method="post" action="/admin/erp/periods">
        @csrf
        <div class="form-grid">
            <label>عنوان<input type="text" name="title" required maxlength="100" placeholder="مثلاً سال مالی ۱۴۰۵"></label>
            <label></label>
            <label>از تاریخ<input type="date" name="start_date" required></label>
            <label>تا تاریخ<input type="date" name="end_date" required></label>
        </div>
        <button class="btn" type="submit">ایجاد دوره</button>
    </form>
    <p class="muted" style="margin-top:8px">بستن دوره = صدور سند بستن حساب‌های موقت (درآمد/هزینه → خلاصهٔ سود و زیان → سود انباشته) و قفل بازه.</p>
</div>
@endsection
