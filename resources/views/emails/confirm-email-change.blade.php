@extends('emails.layouts.main')

@section('title', 'Konfirmasi Perubahan Email')
@section('label', 'Email')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <p style="margin:0 0 10px 0; font-size:13px; line-height:20px; color:#e76e12; font-weight:700; text-transform:uppercase; letter-spacing:0.8px;">
                Perubahan email
            </p>
            <h1 class="email-title" style="margin:0 0 16px 0; font-size:28px; line-height:36px; color:#2b2118; font-weight:700;">
                Konfirmasi email baru Anda
            </h1>
            <p style="margin:0 0 24px 0; font-size:16px; line-height:26px; color:#6b5c48;">
                Halo {{ $name }}, kami menerima permintaan untuk mengganti email akun Portal SI Anda menjadi
                <strong style="color:#2b2118;">{{ $newEmail }}</strong>. Klik tombol di bawah untuk menyelesaikan perubahan.
            </p>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding:8px 0 28px 0;">
            <a href="{{ $url }}" class="email-button" style="display:inline-block; min-width:220px; padding:15px 24px; background:#e76e12; color:#ffffff; border-radius:11px; font-size:16px; line-height:20px; font-weight:700; text-align:center; text-decoration:none;">
                Konfirmasi Email Baru
            </a>
        </td>
    </tr>
    <tr>
        <td style="padding:18px 20px; background:#fbf6ee; border:1px solid #f0e6d6; border-radius:12px;">
            <p style="margin:0 0 8px 0; font-size:14px; line-height:22px; color:#3d3221; font-weight:700;">
                Tautan ini berlaku selama 60 menit.
            </p>
            <p style="margin:0; font-size:14px; line-height:22px; color:#8a7a66;">
                Jika tombol tidak berfungsi, salin dan buka tautan berikut di browser:
            </p>
            <p style="margin:10px 0 0 0; font-size:13px; line-height:20px; color:#178f72; word-break:break-all;">
                <a href="{{ $url }}" style="color:#178f72; text-decoration:underline;">{{ $url }}</a>
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-top:28px;">
            <p style="margin:0; font-size:15px; line-height:24px; color:#6b5c48;">
                Jika Anda tidak meminta perubahan ini, abaikan email ini — email akun Anda tidak akan berubah.
            </p>
        </td>
    </tr>
</table>
@endsection
