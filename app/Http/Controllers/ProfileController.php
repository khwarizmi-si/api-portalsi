<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
// ✅ Public Profile
public function show(Request $request, $username)
{
    $authUser = Auth::user();

    $user = User::whereRaw('LOWER(username) = ?', [strtolower($username)])
        ->withCount(['followers', 'following', 'posts'])
        ->with('followers')
        ->firstOrFail();

    $canViewPosts = !$user->is_private ||
        ($authUser && ($authUser->user_id === $user->user_id || $user->followers->contains($authUser->user_id)));

    $recentPosts = $canViewPosts
        ? $user->posts()
            ->latest()
            ->select('post_id', 'caption', 'media_url', 'is_video', 'created_at')
            ->get()
            ->map(function ($post) {
                // Cek ekstensi dari media_url
                $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $post->media_url) ? 1 : 0;
                $post->is_video = $isVideo;
                return $post;
            })
        : [];

    return response()->json([
        'user_id'             => $user->user_id,
        'username'            => $user->username,
        'full_name'           => $user->full_name,
        'bio'                 => $user->bio,
        'email'               => $user->email,
        'profile_picture_url' => $user->profile_picture_url,
        'banner_url'          => $user->banner_url,
        'is_verified'         => $user->is_verified,
        'is_private'          => $user->is_private,
        'followers_count'     => $user->followers_count,
        'following_count'     => $user->following_count,
        'posts_count'         => $user->posts_count,
        'recent_posts'        => $recentPosts,
        'message'             => $canViewPosts ? null : 'Akun private, follow untuk melihat postingan.',
    ]);
}

    // ✅ Search user by username and/or full_name
public function search(Request $request)
{
    $username = $request->input('username');
    $fullName = $request->input('full_name');

    if (!$username && !$fullName) {
        return response()->json(['message' => 'Parameter username atau full_name diperlukan.'], 400);
    }

    $users = User::query()
        ->where(function ($q) use ($username, $fullName) {
            if ($username) {
                $q->where('username', 'like', "%{$username}%");
            }
            if ($fullName) {
                $q->orWhere('full_name', 'like', "%{$fullName}%");
            }
        })
        ->select('user_id', 'username', 'full_name', 'profile_picture_url')
        ->get();

    if ($users->isEmpty()) {
        return response()->json(['message' => 'Tidak ada hasil yang ditemukan.'], 404);
    }

    return response()->json($users);
}


    public function me(Request $request)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Ambil data user + followers/following/posts count
        $user = User::where('user_id', $authUser->user_id)
            ->withCount(['followers', 'following', 'posts'])
            ->with('followers')
            ->firstOrFail();

        $recentPosts = $user->posts()
            ->latest()
            ->select('post_id', 'caption', 'media_url', 'created_at')
            ->get();

        return response()->json([
            'user_id'             => $user->user_id,
            'username'            => $user->username,
            'full_name'           => $user->full_name,
            'bio'                 => $user->bio,
            'email'               => $user->email,
            'email_verified'      => $user->hasVerifiedEmail(),
            'profile_picture_url' => $user->profile_picture_url,
            'banner_url'          => $user->banner_url,
            'is_verified'         => $user->is_verified,
            'is_private'          => $user->is_private,
            'followers_count'     => $user->followers_count,
            'following_count'     => $user->following_count,
            'posts_count'         => $user->posts_count,
            'recent_posts'        => $recentPosts,
        ]);
    }
}