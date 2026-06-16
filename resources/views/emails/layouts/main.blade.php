<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'Portal SI')</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-card { border-radius: 0 !important; }
            .email-padding { padding: 24px !important; }
            .email-title { font-size: 24px !important; line-height: 32px !important; }
            .email-button { display: block !important; width: 100% !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background:#eef3f8; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    @if(!empty($preheader))
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:1px; font-size:1px;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; background:#eef3f8;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="email-shell" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px;">
                    <tr>
                        <td style="padding:0 0 14px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" style="font-size:13px; color:#64748b; letter-spacing:0.3px; text-transform:uppercase;">
                                        Portal Sekolah Impian
                                    </td>
                                    <td align="right" style="font-size:13px; color:#64748b;">
                                        Portal SI
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-card" style="background:#ffffff; border:1px solid #dbe4ee; border-radius:8px; overflow:hidden; box-shadow:0 12px 30px rgba(15, 23, 42, 0.08);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:#0f172a; padding:28px 32px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size:24px; line-height:30px; font-weight:700; color:#ffffff;">
                                                    Portal SI
                                                </td>
                                                <td align="right" style="font-size:12px; color:#fed7aa; text-transform:uppercase; letter-spacing:1px;">
                                                    Akun
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-padding" style="padding:34px 36px 30px 36px;">
                                        @yield('content')
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:22px 36px; background:#f8fafc; border-top:1px solid #e5edf5;">
                                        <p style="margin:0; font-size:12px; line-height:19px; color:#64748b;">
                                            Email ini dikirim otomatis oleh Portal SI. Jika Anda merasa tidak melakukan tindakan ini, abaikan email ini dengan aman.
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:12px; line-height:19px; color:#94a3b8;">
                                            &copy; {{ date('Y') }} Portal SI. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
