<?php

use Illuminate\Support\Facades\Http;

Route::post('/submit-reset-password', function (Request $request) {
    // Ambil input
    $data = $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed',
    ]);

    // Kirim ke API asli Laravel (biasanya di routes/api.php)
    $response = Http::post(config('app.api_url') . '/api/reset-password', $data);

    if ($response->successful()) {
        // Jika berhasil, redirect ke halaman sukses
        return redirect('/reset-password-success');
    } else {
        // Jika gagal, kembali ke form dengan pesan error
        return back()->withErrors(['message' => 'Reset password gagal. Token mungkin tidak valid atau sudah kadaluarsa.']);
    }
});
