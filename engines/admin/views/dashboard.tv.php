@extends('admin::layout')

@section('title', 'داشبورد')

@section('content')
    <h1 class="admin-title">🏠 داشبورد</h1>

    @if(count($widgets) > 0)
        <div class="widget-grid">
            @foreach($widgets as $widget)
                <div class="admin-card" style="grid-column: span {{ $widget['width'] }}">
                    @if($widget['error'] !== null)
                        <div class="flash-error">ویجت {{ $widget['class'] }} خطا داد: {{ $widget['error'] }}</div>
                    @else
                        {!! $widget['html'] !!}
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @foreach($charts as $chart)
        <div class="admin-card">
            <h3 style="margin-top:0">📈 {{ $chart['label'] }}</h3>
            {!! $chart['svg'] !!}
        </div>
    @endforeach

    @if(count($widgets) === 0 && count($charts) === 0)
        <div class="admin-card muted">
            ویجت یا نموداری ثبت نشده است — با
            <code>Admin::dashboard(fn($d) => $d->widget(StatsWidget::class)->chart('sales', $data))</code>
            اضافه کنید (DOC-078 §5).
        </div>
    @endif
@endsection
