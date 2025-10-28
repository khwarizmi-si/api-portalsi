<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
                ->select('post_id', 'caption', 'media_url', 'created_at', 'thumbnail', 'thumbnail_url'); // ambil field thumbnail kalau ada

            $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

            $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
                $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $post->media_url);
                $thumbnail = $isVideo
                    ? $this->generateThumbnailUrl($post->media_url, $post)
                    : $this->normalizeMediaUrl($post->media_url); // untuk gambar, pakai media_url langsung (dinormalisasi)

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

    // ✅ Profile sendiri
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
            ->select('post_id', 'caption', 'media_url', 'created_at', 'thumbnail', 'thumbnail_url');

        $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

        $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
            $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $post->media_url);
            $thumbnail = $isVideo
                ? $this->generateThumbnailUrl($post->media_url, $post)
                : $this->normalizeMediaUrl($post->media_url);

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

    // ✅ Search user
    public function search(Request $request)
    {
        $username = $request->input('username');
        $fullName = $request->input('full_name');
        $perPage  = $request->input('per_page', 10);

        if (!$username && !$fullName) {
            return response()->json(['message' => 'Parameter username atau full_name diperlukan.'], 400);
        }

        $users = User::query()
            ->where(function ($q) use ($username, $fullName) {
                if ($username) $q->where('username', 'like', "%{$username}%");
                if ($fullName) $q->orWhere('full_name', 'like', "%{$fullName}%");
            })
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

    // ✅ Mutual followers
    public function mutuals(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $perPage = $request->input('per_page', 10);
        $followingIds = $authUser->following()->pluck('users.user_id')->toArray();
        $followerIds = $authUser->followers()->pluck('users.user_id')->toArray();
        $mutualIds = array_intersect($followingIds, $followerIds);

        $mutuals = User::whereIn('user_id', $mutualIds)
            ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
            ->paginate($perPage);

        return response()->json($mutuals);
    }

    /**
     * Generate thumbnail URL for a video post.
     * Tries (in order):
     *  1. $post->thumbnail or $post->thumbnail_url if present
     *  2. thumbnails file with same basename as media_url (e.g. uploads/posts/thumbnails/{basename})
     *  3. thumbnails file with basename but .jpg extension (best-effort)
     * Returns a full URL via Storage::disk('public')->url(...)
     */
    private function generateThumbnailUrl($mediaUrl, $post = null)
    {
        // 1) If model already stores thumbnail info, use it
        if ($post) {
            if (!empty($post->thumbnail_url)) {
                return $this->normalizeMediaUrl($post->thumbnail_url);
            }
            if (!empty($post->thumbnail)) {
                return $this->normalizeMediaUrl($post->thumbnail);
            }
        }

        // Normalize incoming mediaUrl to basename
        $basename = pathinfo($mediaUrl, PATHINFO_BASENAME);
        $nameOnly = pathinfo($basename, PATHINFO_FILENAME);

        // Candidate filenames to check in storage/app/public/uploads/posts/thumbnails/
        $candidates = [
            $basename,
            $nameOnly . '.jpg',
            $nameOnly . '.jpeg',
            $nameOnly . '.png',
        ];

        foreach ($candidates as $candidate) {
            $path = "uploads/posts/thumbnails/{$candidate}";
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path); // returns /storage/uploads/...
            }
        }

        // Fallback: return a best-effort URL (most common layout)
        $fallbackPath = "uploads/posts/thumbnails/{$nameOnly}.jpg";
        return Storage::disk('public')->url($fallbackPath);
    }

    /**
     * Normalize various stored paths/URLs to a usable URL for clients.
     * - If it's already an HTTP(S) URL, return as-is.
     * - If it's a /storage/... URL, return as-is.
     * - If it's a filesystem path (starts with /home/.../storage/app/public), convert to Storage::url
     * - Otherwise assume it's a path relative to storage/app/public and use Storage::url
     */
    private function normalizeMediaUrl($mediaUrl)
    {
        if (!$mediaUrl) {
            return null;
        }

        // If already absolute URL (http/https) -> return
        if (preg_match('#^https?://#i', $mediaUrl)) {
            return $mediaUrl;
        }

        // If already /storage/... path -> return as-is
        if (strpos($mediaUrl, '/storage/') === 0) {
            return $mediaUrl;
        }

        // If contains storage/app/public (full filesystem path), try to extract relative part
        if (strpos($mediaUrl, 'storage/app/public') !== false) {
            $parts = explode('storage/app/public', $mediaUrl);
            $rel = ltrim($parts[1], '/\\');
            return Storage::disk('public')->url($rel);
        }

        // If it's a filesystem path starting from /home/... and ends with uploads/..., extract basename and assume it's in uploads/posts/...
        if (strpos($mediaUrl, '/home/') === 0 || strpos($mediaUrl, 'C:\\') === 0) {
            $basename = pathinfo($mediaUrl, PATHINFO_BASENAME);
            // try common locations
            $tryPaths = [
                "uploads/posts/thumbnails/{$basename}",
                "uploads/posts/{$basename}",
                $basename,
            ];
            foreach ($tryPaths as $p) {
                if (Storage::disk('public')->exists($p)) {
                    return Storage::disk('public')->url($p);
                }
            }
            // fallback to thumbnails/{basename}
            return Storage::disk('public')->url("uploads/posts/thumbnails/{$basename}");
        }

        // Otherwise assume it's a relative path inside storage/app/public
        $rel = ltrim($mediaUrl, '/');
        if (Storage::disk('public')->exists($rel)) {
            return Storage::disk('public')->url($rel);
        }

        // Final fallback: just prefix with /storage/ so clients still can try
        return '/storage/' . ltrim($rel, '/');
    }
}
