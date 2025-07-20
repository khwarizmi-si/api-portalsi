@extends('emails.layouts.main')

@section('title', 'Verifikasi Email')

@section('content')
<p>Halo {{ $user->full_name ?? $user->name }},</p>
<p>Silakan klik tombol di bawah untuk memverifikasi email Anda:</p>

<p style="text-align:center;">
    <a href="{{ $url }}" style="padding:12px 24px;background:#1a202c;color:white;text-decoration:none;border-radius:5px;">
        Verifikasi Email
    </a>
</p>

<p>Jika Anda tidak membuat akun, abaikan email ini.</p>
@endsection
