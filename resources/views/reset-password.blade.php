<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Favicon from inline SVG -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,
  %3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E
  %3Ccircle cx='12' cy='12' r='10' fill='%23EDA130'/%3E
  %3Cpath d='M7 13l3 3 7-7' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E
  %3C/svg%3E">

  <style>
    :root {
      --primary: #EDA130;
      --bg: #fffdf8;
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

    h1 {
      font-size: 22px;
      margin-bottom: 24px;
    }

    p {
      color: var(--text-light);
      font-size: 15px;
      line-height: 1.5;
      margin-bottom: 24px;
    }

    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin: 10px 0 20px 0;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 15px;
    }

    button {
      background-color: var(--primary);
      color: white;
      padding: 12px;
      width: 100%;
      font-weight: bold;
      border: none;
      border-radius: 6px;
      font-size: 15px;
      cursor: pointer;
    }

    button:hover {
      background-color: #e1952a;
    }

    .message {
      color: #f00;
      margin-bottom: 12px;
      font-size: 14px;
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
    <h1>Reset Password</h1>
    <p>Silakan masukkan password baru Anda di bawah ini.</p>

    <form method="POST" action="{{ url('/submit-reset-password') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">

      <input type="password" name="password" placeholder="Password baru" required>
      <input type="password" name="password_confirmation" placeholder="Konfirmasi password" required>

      <button type="submit">Ubah Password</button>
    </form>
  </div>
</body>
</html>
