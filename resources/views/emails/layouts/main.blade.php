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
            .email-padding { padding: 24px !important; }
            .email-title { font-size: 24px !important; line-height: 32px !important; }
            .email-button { display: block !important; width: 100% !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background:#f4efe7; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#2b2118;">
    @if(!empty($preheader))
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:1px; font-size:1px;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; background:#f4efe7;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" class="email-shell" width="560" cellpadding="0" cellspacing="0" style="width:560px; max-width:560px;">
                    <tr>
                        <td style="padding:0 2px 12px 2px; font-size:13px; line-height:19px; color:#8a7a66;">
                            Portal SI
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; border:1px solid #eadfce; border-radius:18px; overflow:hidden; box-shadow:0 10px 26px rgba(91, 61, 32, 0.08);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:24px 32px 20px 32px; border-bottom:1px solid #f0e6d8;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="vertical-align:middle;">
                                                    <div style="font-size:20px; line-height:26px; font-weight:750; color:#2b2118; letter-spacing:-0.02em;">
                                                        Portal SI
                                                    </div>
                                                    <div style="margin-top:3px; font-size:12px; line-height:18px; color:#8a7a66;">
                                                        Portal Sekolah Impian
                                                    </div>
                                                </td>
                                                <td align="right" style="vertical-align:middle;">
                                                    <span style="display:inline-block; padding:6px 11px; background:#fff4e8; border:1px solid #f2dac1; border-radius:999px; font-size:11px; color:#a95716; text-transform:uppercase; letter-spacing:0.8px; font-weight:700;">@yield('label', 'Akun')</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-padding" style="padding:32px 36px 12px 36px;">
                                        @yield('content')
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:22px 36px; background:#fbf8f2; border-top:1px solid #f0e6d6;">
                                        <p style="margin:0; font-size:12px; line-height:19px; color:#8a7a66;">
                                            Email ini dikirim otomatis oleh Portal SI. Jika Anda merasa tidak melakukan tindakan ini, abaikan email ini dengan aman.
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:12px; line-height:19px; color:#b09a80;">
                                            &copy; {{ date('Y') }} Portal SI · Portal Sekolah Impian.
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
