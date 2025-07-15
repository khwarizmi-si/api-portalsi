<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AccountController extends Controller
{
    // 🔧 Update akun
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'username' => 'nullable|string|unique:users,username,' . $user->user_id . ',user_id',
            'full_name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->user_id . ',user_id',
            'bio' => 'nullable|string',
            'profile_picture_url' => 'nullable|url',
            'is_private' => 'nullable|boolean',
        ]);

        $user->update($request->only([
            'username',
            'full_name',
            'email',
            'bio',
            'profile_picture_url',
            'is_private'
        ]));

        return response()->json([
            'message' => 'Akun berhasil diperbarui',
            'user' => $user
        ]);
    }

    // 🔒 Ganti password
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6'
        ]);

        if (!Hash::check($request->current_password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah']
            ]);
        }

        $user->password_hash = bcrypt($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password berhasil diganti']);
    }

    // ❌ Hapus akun
    public function destroy(Request $request)
    {
        $user = Auth::user();
        $user->delete();

        return response()->json(['message' => 'Akun telah dihapus']);
    }
}
