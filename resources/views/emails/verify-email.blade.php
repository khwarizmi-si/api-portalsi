@extends('emails.layouts.main')

@section('title', 'Verifikasi Email')

@section('content')
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color: #333; background-color: #ffffff; padding: 24px;">
    <tr>
        <td>
            <h2 style="font-size: 24px; font-weight: bold; margin-bottom: 16px; color: #1a202c;">
                Selamat datang, {{ $user->full_name ?? $user->name }}!
            </h2>
            <p style="font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
                Terima kasih telah mendaftar di <strong>Portal Sekolah Impian</strong>. Untuk mengaktifkan akun Anda, silakan klik tombol di bawah ini:
            </p>

            <p style="text-align: center; margin: 32px 0;">
                <a href="{{ $url }}" style="display: inline-block; padding: 14px 28px; font-size: 16px; font-weight: bold; color: #ffffff; background-color: #1a202c; text-decoration: none; border-radius: 8px;">
                    Verifikasi Email
                </a>
            </p>

            <p style="font-size: 14px; line-height: 1.6; color: #777;">
                Jika Anda tidak membuat akun ini, Anda dapat mengabaikan email ini dan tidak akan ada tindakan yang dilakukan.
            </p>

            <p style="font-size: 14px; line-height: 1.6; margin-top: 40px; color: #777;">
                Salam hangat,<br>
                <strong>Tim Portal SI</strong>
            </p>
        </td>
    </tr>
</table>
@endsection
