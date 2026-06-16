@extends('emails.layouts.main')

@section('title', 'Verifikasi Email')

@section('content')
@php
    $displayName = $user->full_name ?: ($user->username ?: 'Pengguna Portal SI');
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <p style="margin:0 0 10px 0; font-size:13px; line-height:20px; color:#f97316; font-weight:700; text-transform:uppercase; letter-spacing:0.8px;">
                Verifikasi email
            </p>
            <h1 class="email-title" style="margin:0 0 16px 0; font-size:28px; line-height:36px; color:#0f172a; font-weight:700;">
                Selamat datang, {{ $displayName }}.
            </h1>
            <p style="margin:0 0 24px 0; font-size:16px; line-height:26px; color:#475569;">
                Terima kasih sudah mendaftar di <strong style="color:#0f172a;">Portal Sekolah Impian</strong>. Klik tombol di bawah untuk mengaktifkan akun dan mulai menggunakan Portal SI.
            </p>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding:8px 0 28px 0;">
            <a href="{{ $url }}" class="email-button" style="display:inline-block; min-width:220px; padding:15px 24px; background:#f97316; color:#ffffff; border-radius:7px; font-size:16px; line-height:20px; font-weight:700; text-align:center; text-decoration:none;">
                Verifikasi Email
            </a>
        </td>
    </tr>
    <tr>
        <td style="padding:18px 20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
            <p style="margin:0 0 8px 0; font-size:14px; line-height:22px; color:#334155; font-weight:700;">
                Tautan ini berlaku selama 60 menit.
            </p>
            <p style="margin:0; font-size:14px; line-height:22px; color:#64748b;">
                Jika tombol tidak berfungsi, salin dan buka tautan berikut di browser:
            </p>
            <p style="margin:10px 0 0 0; font-size:13px; line-height:20px; color:#2563eb; word-break:break-all;">
                <a href="{{ $url }}" style="color:#2563eb; text-decoration:underline;">{{ $url }}</a>
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-top:28px;">
            <p style="margin:0; font-size:15px; line-height:24px; color:#475569;">
                Salam hangat,<br>
                <strong style="color:#0f172a;">Tim Portal SI</strong>
            </p>
        </td>
    </tr>
</table>
@endsection
