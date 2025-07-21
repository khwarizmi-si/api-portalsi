<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// ✅ GET: Menampilkan form reset password
Route::get('/reset-password', function (Request $request) {
    $token = $request->query('token');
    $email = $request->query('email');

    if (!$token || !$email) {
        abort(404, 'Token atau email tidak ditemukan.');
    }

    return view('reset-password', compact('token', 'email'));
});

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
    } catch (\Exception $e) {
        return redirect('/reset-password-error')->with('error', 'Gagal terhubung ke server API.');
    }

    dd([
        'status' => $response->status(),
        'body' => $response->body(),
        'json' => $response->json(),
    ]);
    
});

// ✅ GET: Halaman sukses
Route::get('/reset-password-success', function () {
    return view('reset-password-success');
});

// ✅ GET: Halaman gagal
Route::get('/reset-password-error', function () {
    return view('reset-password-error', [
        'error' => session('error') ?? 'Terjadi kesalahan saat mengubah password.'
    ]);
});
