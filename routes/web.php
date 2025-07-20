<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// 👇 Route GET untuk menampilkan form reset password
Route::get('/reset-password', function (Request $request) {
    $token = $request->query('token');
    $email = $request->query('email');

    if (!$token || !$email) {
        abort(404, 'Token atau email tidak ditemukan.');
    }

    return view('reset-password', compact('token', 'email'));
});

// 👇 Route POST untuk submit form
Route::post('/submit-reset-password', function (Request $request) {
    $data = $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed',
    ]);

    $response = Http::post(config('app.api_url') . '/api/reset-password', $data);

    if ($response->successful()) {
        return redirect('/reset-password-success');
    } else {
        return back()->withErrors(['message' => 'Reset password gagal. Token mungkin tidak valid atau sudah kadaluarsa.']);
    }
});

