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
@php
    $assets = 'https://app.portalsi.com/assets';
@endphp
<body style="margin:0; padding:0; width:100%; background:#f4ece0; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#2b2118;">
    @if(!empty($preheader))
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:1px; font-size:1px;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; background:#f4ece0;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="email-shell" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px;">
                    <tr>
                        <td style="padding:0 4px 14px 4px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" style="font-size:12px; color:#8a7a66; letter-spacing:0.4px; text-transform:uppercase;">
                                        Portal Sekolah Impian
                                    </td>
                                    <td align="right" style="font-size:12px; color:#8a7a66;">
                                        Karena seribu langkah dimulai dari satu langkah
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-card" style="background:#ffffff; border:1px solid #ecdcc0; border-radius:18px; overflow:hidden; box-shadow:0 14px 34px rgba(120, 71, 24, 0.12);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:#ef7d18; background-image:linear-gradient(120deg, #ef7d18 0%, #f59f2f 60%, #178f72 160%); padding:26px 32px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="52" style="vertical-align:middle;">
                                                    <img src="{{ $assets }}/logo-mark.png" width="44" height="44" alt="Portal SI" style="display:block; width:44px; height:44px; border-radius:12px; border:2px solid rgba(255,255,255,0.55);">
                                                </td>
                                                <td style="vertical-align:middle; padding-left:12px; font-size:22px; line-height:28px; font-weight:700; color:#ffffff; letter-spacing:-0.02em;">
                                                    Portal SI
                                                </td>
                                                <td align="right" style="vertical-align:middle;">
                                                    <span style="display:inline-block; padding:5px 12px; background:rgba(255,255,255,0.22); border:1px solid rgba(255,255,255,0.4); border-radius:999px; font-size:11px; color:#ffffff; text-transform:uppercase; letter-spacing:1px;">@yield('label', 'Akun')</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height:4px; background:#178f72; line-height:4px; font-size:4px;">&nbsp;</td></tr>
                                <tr>
                                    <td class="email-padding" style="padding:34px 36px 12px 36px;">
                                        @yield('content')
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:6px 36px 28px 36px;">
                                        <img src="{{ $assets }}/images/community.png" width="220" alt="" style="display:block; width:220px; max-width:70%; height:auto; opacity:0.96;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:22px 36px; background:#fbf6ee; border-top:1px solid #f0e6d6;">
                                        <p style="margin:0; font-size:12px; line-height:19px; color:#8a7a66;">
                                            Email ini dikirim otomatis oleh Portal SI. Jika Anda merasa tidak melakukan tindakan ini, abaikan email ini dengan aman.
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:12px; line-height:19px; color:#b09a80;">
                                            &copy; {{ date('Y') }} Portal SI · Portal Sekolah Impian. All rights reserved.
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
