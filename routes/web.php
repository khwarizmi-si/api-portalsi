<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

// ✅ POST: Mengirim form reset password
Route::post('/submit-reset-password', function (Request $request) {
    $data = $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed',
        'password_confirmation' => 'required'
    ]);

    try {
        $response = Http::withHeaders([
            'Accept' => 'application/json'
        ])->post(config('app.api_url') . '/api/reset-password', $data);

        // 🔎 Log untuk debugging
        Log::info('Submit Reset Password Response', [
            'status' => $response->status(),
            'json'   => $response->json(),
        ]);

    } catch (\Exception $e) {
        Log::error('Submit Reset Password Exception', ['error' => $e->getMessage()]);
        return redirect('/reset-password-error')->with('error', 'Gagal terhubung ke server API.');
    }

    // ✅ Cek hanya status code
    if ($response->status() === 200) {
        return redirect('/reset-password-success');
    }

    // ❌ Kalau gagal
    return redirect('/reset-password-error')->with(
        'error',
        $response->json('message') ?? 'Reset password gagal.'
    );
});
