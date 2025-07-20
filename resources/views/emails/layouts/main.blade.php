<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Portal SI')</title>
</head>
<body style="background:#f4f4f4;padding:40px;">
    <table align="center" width="600" style="background:#fff;padding:30px;border-radius:8px;">
        <tr>
            <td>
                <h2 style="color:#EDA130;">Portal Sekolah Impian</h2>
                @yield('content')
                <hr>
                <p style="color:#999;font-size:12px;">© {{ date('Y') }} Portal SI, All Rights Reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
