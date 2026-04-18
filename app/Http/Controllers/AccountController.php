<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class AccountController extends Controller
{
    // 🔧 UPDATE AKUN
    public function update(Request $request)
    {
        $user = Auth::user();
    
        $request->validate([
            'username' => [
                'nullable',
                'string',
                'regex:/^[a-zA-Z0-9._]+$/',
                'unique:users,username,' . $user->user_id . ',user_id'
            ],
            'full_name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->user_id . ',user_id',
            'bio' => 'nullable|string',
            'is_private' => 'nullable|boolean',
            'profile_picture' => 'nullable|image|max:10240', // max 10MB
            'banner' => 'nullable|image|max:20480', // max 20MB
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore tanpa spasi atau simbol lain.'
        ]);
    
        // ✅ Upload profile picture
        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture_url) {
                $oldPath = ltrim(parse_url($user->profile_picture_url, PHP_URL_PATH), '/');
                Storage::disk('r2')->delete($oldPath);
            }

            $path = $request->file('profile_picture')->store('profile_pictures', 'r2');
            $user->profile_picture_url = Storage::disk('r2')->url($path);
        }

        // ✅ Upload banner
        if ($request->hasFile('banner')) {
            if ($user->banner_url) {
                $oldPath = ltrim(parse_url($user->banner_url, PHP_URL_PATH), '/');
                Storage::disk('r2')->delete($oldPath);
            }

            $path = $request->file('banner')->store('banners', 'r2');
            $user->banner_url = Storage::disk('r2')->url($path);
        }
    
        // ✅ Update field lain
        $user->fill($request->only([
            'username',
            'full_name',
            'email',
            'bio',
            'is_private'
        ]));
    
        $user->save();
    
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

        // Hapus profile picture jika ada
        if ($user->profile_picture_url) {
            $oldPath = ltrim(parse_url($user->profile_picture_url, PHP_URL_PATH), '/');
            Storage::disk('r2')->delete($oldPath);
        }

        // Hapus banner jika ada
        if ($user->banner_url) {
            $oldPath = ltrim(parse_url($user->banner_url, PHP_URL_PATH), '/');
            Storage::disk('r2')->delete($oldPath);
        }

        $user->delete();

        return response()->json(['message' => 'Akun telah dihapus']);
    }


    // 🔍 Cek status private user login
    public function checkPrivateStatus()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'User belum login'
            ], 401); // 401 Unauthorized
        }

        return response()->json($user->is_private == 1 ? 1 : 0);
    }
}
