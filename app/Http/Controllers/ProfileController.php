<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use DB;

class ProfileController extends Controller
{
    // ---------------- Public Profile ----------------
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
                ->select('post_id', 'caption', 'media_url', 'created_at', 'thumbnail_url');

            $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

            $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
                $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm|3gp)$/i', $post->media_url);
                $thumbnail = $isVideo
                    ? $this->generateThumbnailUrl($post->media_url, $post)
                    : null;

                return [
                    'post_id'       => $post->post_id,
                    'caption'       => $post->caption,
                    'media_url'     => $this->normalizeMediaUrl($post->media_url),
                    'is_video'      => $isVideo ? 1 : 0,
                    'thumbnail_url' => $thumbnail,
                    'created_at'    => $post->created_at,
                ];
            });

            $pagination = [
                'current_page'  => $paginatedPosts->currentPage(),
                'last_page'     => $paginatedPosts->lastPage(),
                'per_page'      => $paginatedPosts->perPage(),
                'total'         => $paginatedPosts->total(),
                'next_page_url' => $paginatedPosts->nextPageUrl(),
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

    // ---------------- Profile sendiri ----------------
    public function me(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 9);

        $user = User::where('user_id', $authUser->user_id)
            ->withCount(['followers', 'following', 'posts'])
            ->with('followers')
            ->firstOrFail();

        $postsQuery = $user->posts()
            ->latest()
            ->select('post_id', 'caption', 'media_url', 'created_at', 'thumbnail_url');

        $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

        $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
            $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm|3gp)$/i', $post->media_url);
            $thumbnail = $isVideo
                ? $this->generateThumbnailUrl($post->media_url, $post)
                : null;

            return [
                'post_id'       => $post->post_id,
                'caption'       => $post->caption,
                'media_url'     => $this->normalizeMediaUrl($post->media_url),
                'is_video'      => $isVideo ? 1 : 0,
                'thumbnail_url' => $thumbnail,
                'created_at'    => $post->created_at,
            ];
        });

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

    // ---------------- Search user ----------------
    public function search(Request $request)
    {
        $username = $request->input('username');
        $fullName = $request->input('full_name');
        $perPage  = (int) $request->input('per_page', 10);

        if (!$username && !$fullName) {
            return response()->json(['message' => 'Parameter username atau full_name diperlukan.'], 400);
        }

        $users = User::query()
            ->where(function ($q) use ($username, $fullName) {
                if ($username) $q->where('username', 'like', "%{$username}%");
                if ($fullName) $q->orWhere('full_name', 'like', "%{$fullName}%");
            })
            // Exclude the current user from their own search results.
            ->when($request->user(), fn($q) => $q->where('user_id', '!=', $request->user()->user_id))
            ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
            ->paginate($perPage)
            ->appends([
                'username'  => $username,
                'full_name' => $fullName,
                'per_page'  => $perPage,
            ]);

        if ($users->isEmpty()) {
            return response()->json(['message' => 'Tidak ada hasil yang ditemukan.'], 404);
        }

        return response()->json($users);
    }

    // ---------------- Mutual followers ----------------
    public function mutuals(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $perPage = (int) $request->input('per_page', 10);

        // ambil id following & followers
        $followingIds = $authUser->following()->pluck('users.user_id')->toArray();
        $followerIds  = $authUser->followers()->pluck('users.user_id')->toArray();

        $mutualIds = array_values(array_intersect($followingIds, $followerIds));

        $mutuals = User::whereIn('user_id', $mutualIds)
            ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
            ->paginate($perPage);

        return response()->json($mutuals);
    }

    // ---------------- Helper functions ----------------

    /**
     * Generate thumbnail URL for a video post.
     * Prioritas:
     * 1) $post->thumbnail_url (jika ada)
     * 2) Cek file di storage 'public' pada uploads/posts/thumbnails/{basename or name.jpg/png}
     * 3) Fallback ke /storage/uploads/posts/thumbnails/{name}.jpg
     */
    private function generateThumbnailUrl($mediaUrl, $post = null)
    {
        if ($post && !empty($post->thumbnail_url)) {
            return $this->normalizeMediaUrl($post->thumbnail_url);
        }

        $basename = pathinfo($mediaUrl, PATHINFO_BASENAME);
        $nameOnly = pathinfo($basename, PATHINFO_FILENAME);

        $candidates = [
            $basename,
            $nameOnly . '.jpg',
            $nameOnly . '.jpeg',
            $nameOnly . '.png',
        ];

        foreach ($candidates as $candidate) {
            $p = "uploads/posts/thumbnails/{$candidate}";
            if (Storage::disk('r2')->exists($p)) {
                return Storage::disk('r2')->url($p);
            }
        }

        return Storage::disk('r2')->url("uploads/posts/thumbnails/{$nameOnly}.jpg");
    }

    /**
     * Normalize media URL / path menjadi URL yang bisa diakses klien.
     */
    private function normalizeMediaUrl($mediaUrl)
    {
        if (!$mediaUrl) {
            return null;
        }

        if (preg_match('#^https?://#i', $mediaUrl)) {
            return $mediaUrl;
        }

        if (strpos($mediaUrl, '/storage/') === 0) {
            return $mediaUrl;
        }

        if (strpos($mediaUrl, 'storage/app/public') !== false) {
            $parts = explode('storage/app/public', $mediaUrl);
            $rel = ltrim($parts[1], '/\\');
            return Storage::disk('r2')->url($rel);
        }

        if (strpos($mediaUrl, '/home/') === 0 || strpos($mediaUrl, 'C:\\') === 0) {
            $basename = pathinfo($mediaUrl, PATHINFO_BASENAME);
            $tryPaths = [
                "uploads/posts/thumbnails/{$basename}",
                "uploads/posts/{$basename}",
                $basename,
            ];
            foreach ($tryPaths as $p) {
                if (Storage::disk('r2')->exists($p)) {
                    return Storage::disk('r2')->url($p);
                }
            }
            return Storage::disk('r2')->url("uploads/posts/{$basename}");
        }

        $rel = ltrim($mediaUrl, '/');
        if (Storage::disk('r2')->exists($rel)) {
            return Storage::disk('r2')->url($rel);
        }

        return Storage::disk('r2')->url($rel);
    }
}
