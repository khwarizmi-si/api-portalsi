@extends('emails.layouts.main')

@section('title', 'Reset Password')

@section('content')
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; background-color: #ffffff;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; padding: 16px;">
                <tr>
                    <td style="color: #333333; font-size: 16px; line-height: 1.6;">

                        <h2 style="font-size: 22px; font-weight: bold; margin-bottom: 16px; color: #1a202c;">
                            Permintaan Reset Password
                        </h2>

                        <p style="margin-bottom: 24px;">
                            Halo {{ $user->full_name ?? $user->name }}, kami menerima permintaan untuk mereset password Anda. Klik tombol di bawah untuk melanjutkan proses reset:
                        </p>

                        <p style="text-align: center; margin: 32px 0;">
                            <a href="{{ $url }}" style="display: inline-block; padding: 14px 24px; font-size: 16px; font-weight: bold; color: #ffffff; background-color: #1a202c; text-decoration: none; border-radius: 6px;">
                                Reset Password
                            </a>
                        </p>

                        <p style="color: #666666;">
                            Link ini hanya berlaku selama 60 menit dan hanya dapat digunakan sekali.
                        </p>

                        <p style="color: #666666;">
                            Jika Anda tidak meminta reset password, Anda bisa mengabaikan email ini dan tidak ada tindakan yang perlu dilakukan.
                        </p>

                        <p style="margin-top: 40px; color: #666666;">
                            Salam hangat,<br>
                            <strong>Tim Portal SI</strong>
                        </p>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
