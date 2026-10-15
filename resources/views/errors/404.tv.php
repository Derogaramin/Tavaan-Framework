@extends('layouts.app')

@section('title', '۴۰۴ — یافت نشد')

@section('content')
    <div class="card" style="text-align:center">
        <h1 style="font-size:64px;color:#f59e0b;margin:0">۴۰۴</h1>
        <p>صفحهٔ <code>{{ $path ?? '' }}</code> یافت نشد.</p>
        <p><a href="{{ route('home') }}">→ بازگشت به خانه</a></p>
    </div>
@endsection
