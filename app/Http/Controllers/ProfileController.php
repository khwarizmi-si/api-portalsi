<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    // ✅ Public Profile
    public function show($id)
    {
        $user = User::withCount(['followers', 'following', 'posts'])
                    ->findOrFail($id);

        $recentPosts = $user->posts()
            ->latest()
            ->take(5)
            ->select('post_id', 'caption', 'media_url', 'created_at')
            ->get();

        return response()->json([
            'user_id'             => $user->user_id,
            'username'            => $user->username,
            'full_name'           => $user->full_name,
            'bio'                 => $user->bio,
            'email'               => $user->email,
            'profile_picture_url' => $user->profile_picture_url,
            'is_verified'         => $user->is_verified,
            'is_private'          => $user->is_private,
            'followers_count'     => $user->followers_count,
            'following_count'     => $user->following_count,
            'posts_count'         => $user->posts_count,
            'recent_posts'        => $recentPosts
        ]);
    }

    // ✅ Update profile (auth user)
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'username'            => 'nullable|string|unique:users,username,' . $user->user_id . ',user_id',
            'full_name'           => 'nullable|string',
            'email'               => 'nullable|email|unique:users,email,' . $user->user_id . ',user_id',
            'bio'                 => 'nullable|string',
            'profile_picture_url' => 'nullable|url',
            'is_private'          => 'nullable|boolean',
        ]);

        $user->update($request->only([
            'username',
            'full_name',
            'email',
            'bio',
            'profile_picture_url',
            'is_private',
        ]));

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user
        ]);
    }

    // ✅ Ganti password
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json(['error' => 'Password lama salah'], 403);
        }

        $user->update([
            'password_hash' => bcrypt($request->new_password),
        ]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    // ✅ Hapus akun
    public function destroy()
    {
        $user = Auth::user();
        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
