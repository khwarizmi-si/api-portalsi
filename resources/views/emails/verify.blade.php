@extends('vendor.mail.html.layout')

@section('title', 'Verifikasi Email Anda')

@section('content')
<p>Hai {{ $user->full_name }},</p>

<p>Terima kasih telah mendaftar di <strong>Portal SI</strong>!</p>

<p>Untuk mulai menggunakan akun Anda, silakan klik tombol di bawah ini untuk memverifikasi alamat email Anda:</p>

<p style="text-align:center;">
    <a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 24px;background-color:#00AEEF;color:white;border-radius:8px;text-decoration:none;font-weight:bold;">
        Verifikasi Email Saya
    </a>
</p>

<p>Jika Anda tidak mendaftar akun, abaikan email ini.</p>
@endsection
