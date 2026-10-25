<!DOCTYPE html>
@php
    $courseTitle = $courseTitle ?? '';
    $studentName = $studentName ?? '';
    $instructorName = $instructorName ?? '';
@endphp
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans('lms::messages.certificate_document_title') }} — {{ $certificate->serial() }}</title>
    @verbatim
    <style>
        /* سند گواهی — HTML آمادهٔ چاپ (print-to-PDF؛ همان رویکرد PdfExporter
           موتور report با driver=html — بدون ext، RTL/فارسی کامل). */
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, serif; background: #eef2f7; color: #0f172a; }
        .sheet { max-width: 820px; margin: 32px auto; background: #fff; border: 14px double #1d4ed8; outline: 2px solid #f59e0b; outline-offset: -22px; padding: 56px 48px; text-align: center; position: relative; }
        .crest { font-size: 3rem; }
        h1 { font-size: 1.7rem; margin: .3rem 0 1.2rem; color: #1e3a8a; letter-spacing: 1px; }
        .body-text { font-size: 1.05rem; line-height: 2.4; margin: 1.4rem auto; max-width: 620px; }
        .holder { font-size: 1.5rem; font-weight: 800; color: #1d4ed8; border-bottom: 2px dotted #94a3b8; display: inline-block; padding: 0 1.4rem .2rem; margin: .3rem 0; }
        .course { font-size: 1.25rem; font-weight: 800; }
        .meta { display: flex; justify-content: space-between; gap: 1rem; margin-top: 3rem; font-size: .85rem; color: #475569; }
        .meta .cell { flex: 1; }
        .serial { font-family: monospace; direction: ltr; font-size: .95rem; letter-spacing: 1px; }
        .verify { margin-top: 2.2rem; font-size: .8rem; color: #64748b; }
        .verify a { color: #1d4ed8; direction: ltr; display: inline-block; }
        .seal { position: absolute; bottom: 24px; left: 32px; width: 92px; height: 92px; border: 3px solid #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #b45309; font-weight: 800; font-size: .72rem; transform: rotate(-8deg); opacity: .85; }
        .toolbar { max-width: 820px; margin: 12px auto; text-align: center; }
        .toolbar a, .toolbar button { display: inline-block; background: #1d4ed8; color: #fff; border: 0; border-radius: 8px; padding: .5rem 1.2rem; font-family: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; border-width: 10px; }
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>
    @endverbatim
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨 {{ trans('lms::messages.certificate_print') }}</button>
    <a href="{{ $certificate->verifyUrl() }}">{{ trans('lms::messages.verify_certificate') }}</a>
</div>
<div class="sheet">
    <div class="crest">🎓</div>
    <h1>{{ trans('lms::messages.certificate_document_title') }}</h1>

    <div class="body-text">
        بدین‌وسیله گواهی می‌شود
        <div class="holder">{{ $studentName }}</div>
        <br>
        دورهٔ
        <span class="course">«{{ $courseTitle }}»</span>
        <br>
        را با موفقیت به پایان رسانده است.
    </div>

    <div class="meta">
        <div class="cell">
            <div>{{ trans('lms::messages.certificate_issued_at') }}</div>
            <strong>{{ jalali($certificate->issuedAt(), 'Y/m/d') }}</strong>
        </div>
        <div class="cell">
            <div>{{ trans('lms::messages.instructor') }}</div>
            <strong>{{ $instructorName }}</strong><br>
            <span style="font-size:.72rem">{{ trans('lms::messages.certificate_signature') }}</span>
        </div>
        <div class="cell">
            <div>{{ trans('lms::messages.certificate_serial') }}</div>
            <strong class="serial">{{ $certificate->serial() }}</strong>
        </div>
    </div>

    <div class="seal">تَوان<br>LMS</div>

    <div class="verify">
        {{ trans('lms::messages.verify_certificate') }}:
        <a href="{{ $certificate->verifyUrl() }}">{{ $certificate->verifyUrl() }}</a>
    </div>
</div>
</body>
</html>
