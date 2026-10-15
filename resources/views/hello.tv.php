@extends('layouts.app')

@section('title', "سلام {$name}")

@section('content')
    <div class="card" style="text-align:center">
        <h1>سلام، {{ $name }}! 🎉</h1>
        <p>این صفحه از روت پارامتریک <code>/hello/{name}</code> رندر شده.</p>
        <p style="color:#94a3b8">{{ jalali(null, 'Y/m/d H:i') }}</p>
        <p><a href="{{ route('home') }}">→ بازگشت</a></p>
    </div>
@endsection
