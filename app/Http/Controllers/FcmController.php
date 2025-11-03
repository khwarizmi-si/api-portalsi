<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FcmController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $userId = Auth::id();

        // Bisa juga pakai guard custom kalau bukan sanctum/jwt
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Simpan token (hindari duplikat)
        DB::table('user_fcm_tokens')->updateOrInsert(
            ['user_id' => $userId, 'fcm_token' => $request->fcm_token],
            ['updated_at' => now()]
        );

        return response()->json(['message' => 'Token FCM disimpan']);
    }
}
