<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\PusherController;


Route::get('/test-reverb-connection', function () {
    try {
        // Test basic broadcasting
        broadcast(new \App\Events\TestEvent('Test message from Reverb'));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Event broadcasted successfully',
            'config' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT'),
                'scheme' => env('REVERB_SCHEME')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Broadcast failed: ' . $e->getMessage(),
            'config' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT'),
                'scheme' => env('REVERB_SCHEME')
            ]
        ], 500);
    }
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
