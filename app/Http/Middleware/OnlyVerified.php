<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnlyVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || $user->is_verified != 1) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Akun Anda belum diverifikasi untuk mengakses fitur ini.'
            ], 403);
        }

        return $next($request);
    }
}
