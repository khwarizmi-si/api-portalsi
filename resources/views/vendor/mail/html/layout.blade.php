<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Portal SI')</title>
</head>
<body style="background-color:#f4f4f4;padding:40px 0;margin:0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:white;border-radius:12px;overflow:hidden;box-shadow:0 0 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background-color:#00AEEF;padding:30px;text-align:center;">
                            <h1 style="color:white;margin:0;font-size:24px;">📘 Portal Sekolah Impian</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 30px;font-family:sans-serif;font-size:16px;line-height:1.5;color:#333;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f4f4f4;text-align:center;padding:20px;color:#999;font-size:12px;">
                            &copy; {{ date('Y') }} Portal SI. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
