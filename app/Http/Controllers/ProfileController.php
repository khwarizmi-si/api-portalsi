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
    $page     = (int) $request->input('page', 1);
    $perPage  = (int) $request->input('per_page', 9);

    $user = User::whereRaw('LOWER(username) = ?', [strtolower($username)])
        ->withCount(['followers', 'following', 'posts'])
        ->with('followers')
        ->firstOrFail();

    $canViewPosts = !$user->is_private ||
        ($authUser && ($authUser->user_id === $user->user_id || $user->followers->contains($authUser->user_id)));

    $recentPosts = [];
    $pagination = null;

    if ($canViewPosts) {
        $postsQuery = $user->posts()
            ->latest()
            ->select('post_id', 'caption', 'media_url', 'created_at');

        // Gunakan pagination bawaan Laravel
        $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

        $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
            $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $post->media_url) ? 1 : 0;
            return [
                'post_id'    => $post->post_id,
                'caption'    => $post->caption,
                'media_url'  => $post->media_url,
                'is_video'   => $isVideo,
                'created_at' => $post->created_at,
            ];
        });

        // Ambil info pagination (buat infinite scroll)
        $pagination = [
            'current_page' => $paginatedPosts->currentPage(),
            'last_page'    => $paginatedPosts->lastPage(),
            'per_page'     => $paginatedPosts->perPage(),
            'total'        => $paginatedPosts->total(),
            'next_page_url'=> $paginatedPosts->nextPageUrl(),
        ];
    }

    return response()->json([
        'user_id'             => $user->user_id,
        'username'            => $user->username,
        'full_name'           => $user->full_name,
        'bio'                 => $user->bio,
        'email'               => $user->email,
        'profile_picture_url' => $user->profile_picture_url,
        'banner_url'          => $user->banner_url,
        'is_verified'         => $user->is_verified,
        'role'                => $user->role,
        'is_private'          => $user->is_private,
        'followers_count'     => $user->followers_count,
        'following_count'     => $user->following_count,
        'posts_count'         => $user->posts_count,
        'recent_posts'        => $recentPosts,
        'pagination'          => $pagination,
        'message'             => $canViewPosts ? null : 'Akun private, follow untuk melihat postingan.',
    ]);
}

// ✅ Search user by username and/or full_name
public function search(Request $request)
{
    $username = $request->input('username');
    $fullName = $request->input('full_name');
    $perPage  = $request->input('per_page', 10); // default 10 data per page

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
        ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
        ->paginate($perPage)
        ->appends([ // ✅ jaga parameter tetap ada di URL pagination
            'username'  => $username,
            'full_name' => $fullName,
            'per_page'  => $perPage,
        ]);

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

    $page    = (int) $request->input('page', 1);
    $perPage = (int) $request->input('per_page', 9);

    // Ambil data user + followers/following/posts count
    $user = User::where('user_id', $authUser->user_id)
        ->withCount(['followers', 'following', 'posts'])
        ->with('followers')
        ->firstOrFail();

    // Query post dengan pagination
    $postsQuery = $user->posts()
        ->latest()
        ->select('post_id', 'caption', 'media_url', 'created_at');

    $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

    // Map hasil post
    $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
        $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $post->media_url) ? 1 : 0;
        return [
            'post_id'    => $post->post_id,
            'caption'    => $post->caption,
            'media_url'  => $post->media_url,
            'is_video'   => $isVideo,
            'created_at' => $post->created_at,
        ];
    });

    // Info pagination (buat infinite scroll)
    $pagination = [
        'current_page'  => $paginatedPosts->currentPage(),
        'last_page'     => $paginatedPosts->lastPage(),
        'per_page'      => $paginatedPosts->perPage(),
        'total'         => $paginatedPosts->total(),
        'next_page_url' => $paginatedPosts->nextPageUrl(),
    ];

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
        'role'                => $user->role,
        'is_private'          => $user->is_private,
        'followers_count'     => $user->followers_count,
        'following_count'     => $user->following_count,
        'posts_count'         => $user->posts_count,
        'recent_posts'        => $recentPosts,
        'pagination'          => $pagination,
    ]);
}


public function mutuals(Request $request)
{
    $authUser = Auth::user();

    if (!$authUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $perPage = $request->input('per_page', 10);

    // Ambil ID yang kita follow
    $followingIds = $authUser->following()->pluck('users.user_id')->toArray();

    // Ambil ID yang follow kita
    $followerIds = $authUser->followers()->pluck('users.user_id')->toArray();

    // Cari irisan (mutual)
    $mutualIds = array_intersect($followingIds, $followerIds);

    $mutuals = User::whereIn('user_id', $mutualIds)
        ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
        ->paginate($perPage);

    return response()->json($mutuals);
}

}