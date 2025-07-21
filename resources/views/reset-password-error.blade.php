<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Password Gagal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Favicon dari inline SVG (ikon X merah) -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,
  %3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E
  %3Ccircle cx='12' cy='12' r='10' fill='%23DA3C3C'/%3E
  %3Cpath d='M8 8l8 8M16 8l-8 8' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E
  %3C/svg%3E">

  <style>
    :root {
      --primary: #DA3C3C;
      --bg: #fffafa;
      --text-dark: #2f2f2f;
      --text-light: #666;
      --radius: 16px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
      color: var(--text-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      padding: 24px;
    }

    .card {
      background: #fff;
      padding: 48px 32px;
      max-width: 420px;
      width: 100%;
      border-radius: var(--radius);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.06);
      text-align: center;
      border-top: 6px solid var(--primary);
    }

    .icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 80px;
      height: 80px;
      margin: 0 auto 24px auto;
    }

    .icon svg {
      width: 100%;
      height: 100%;
      stroke: var(--primary);
      stroke-width: 2.5;
      fill: none;
      display: block;
    }

    h1 {
      font-size: 22px;
      margin-bottom: 12px;
    }

    p {
      color: var(--text-light);
      font-size: 15px;
      line-height: 1.5;
    }

    @media (max-width: 480px) {
      .card {
        padding: 36px 24px;
      }

      h1 {
        font-size: 20px;
      }

      p {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" stroke-opacity="0.15" fill="none"/>
        <path d="M8 8l8 8M16 8l-8 8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>

    <h1>Reset Password Gagal</h1>
    <p>{{ $error ?? 'Terjadi kesalahan saat mengubah password. Token mungkin tidak valid atau sudah kadaluarsa.' }}</p>
  </div>
</body>
</html>
