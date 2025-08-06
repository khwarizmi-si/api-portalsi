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
            'profile_picture' => 'nullable|image|max:2048', // upload file, bukan URL
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore tanpa spasi atau simbol lain.'
        ]);
    
        // ✅ Upload profile picture (jika ada)
        if ($request->hasFile('profile_picture')) {
            // Hapus file lama jika ada dan dari folder yang sama
            if ($user->profile_picture_url && str_contains($user->profile_picture_url, '/storage/profile_pictures/')) {
                $oldPath = str_replace(asset('storage') . '/', '', $user->profile_picture_url);
                Storage::disk('public')->delete($oldPath);
            }
    
            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            $user->profile_picture_url = asset('storage/' . $path);
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
        if ($user->profile_picture_url && str_contains($user->profile_picture_url, '/storage/profile_pictures/')) {
            $oldPath = str_replace(asset('storage') . '/', '', $user->profile_picture_url);
            Storage::disk('public')->delete($oldPath);
        }

        $user->delete();

        return response()->json(['message' => 'Akun telah dihapus']);
    }
}
