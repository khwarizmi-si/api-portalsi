<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\PusherController;
use App\Events\NewDirectMessage;
use App\Models\User;


Route::get('/test-reverb', function () {
    // Ambil user pertama sebagai contoh
    $user = User::first();

    if (!$user) {
        return response()->json(['message' => 'Belum ada user di database.'], 400);
    }

    // Buat data dummy pesan
    $messageData = [
        'sender_id' => $user->id,
        'receiver_id' => $user->id,
        'content' => 'Test broadcast Reverb ✅',
        'media_url' => null,
        'sent_at' => now(),
        'is_read' => false,
    ];

    // Broadcast event
    broadcast(new NewDirectMessage((object) $messageData))->toOthers();

    return response()->json([
        'message' => 'Test broadcast berhasil dikirim.',
        'data' => $messageData
    ]);
});


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

    if ($response->status() === 200 && $response->json('message') === 'Password berhasil direset.') {
        return redirect('/reset-password-success');
    } else {
        return redirect('/reset-password-error')->with('error', $response->json('message') ?? 'Reset password gagal.');
    }
    
    
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


Route::post('/pusher/user-auth', [PusherController::class, 'pusherAuth'])
    ->middleware('auth:sanctum'); // atau auth:api sesuai yang kamu pakai
