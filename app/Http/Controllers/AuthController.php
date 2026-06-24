<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\LoginHistory;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        // Catat login history
        LoginHistory::create([
            'user_id'   => $user->user_id,
            'token_id'  => $user->tokens()->latest('id')->first()?->id,
            'ip_address'=> $request->ip(),
            'user_agent'=> $request->header('User-Agent'),
            'device'    => substr($request->header('User-Agent'), 0, 255),
            'browser'   => $this->getBrowser($request->header('User-Agent')),
            'platform'  => $this->getPlatform($request->header('User-Agent')),
            'login_at'  => now(),
        ]);

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    private function getBrowser($userAgent)
    {
        if (stripos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (stripos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (stripos($userAgent, 'Safari') !== false) return 'Safari';
        return 'Unknown';
    }

    private function getPlatform($userAgent)
    {
        if (stripos($userAgent, 'Windows') !== false) return 'Windows';
        if (stripos($userAgent, 'Mac') !== false) return 'MacOS';
        if (stripos($userAgent, 'Linux') !== false) return 'Linux';
        return 'Unknown';
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
