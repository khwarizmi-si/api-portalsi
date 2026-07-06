<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileController extends Controller
{
    private function mediaDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function storagePathFromUrl(string $url): string
    {
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/');

        return preg_replace('#^storage/#', '', $path);
    }

    // ---------------- Public Profile ----------------
    public function show(Request $request, $username)
    {
        $authUser = $request->user('sanctum') ?? Auth::user();
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 9);

        $user = User::whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->withCount([
                'followers as followers_count' => fn ($query) => $query->where('follows.status', 'accepted'),
                'following as following_count' => fn ($query) => $query->where('follows.status', 'accepted'),
                'posts',
            ])
            ->firstOrFail();

        $canViewPosts = ! $user->is_private ||
            ($authUser && ($authUser->user_id === $user->user_id || $user->followers()
                ->where('users.user_id', $authUser->user_id)
                ->wherePivot('status', 'accepted')
                ->exists()));

        $recentPosts = [];
        $pagination = null;

        if ($canViewPosts) {
            $postsQuery = $user->posts()
                ->latest()
                ->select('post_id', 'caption', 'media_url', 'created_at', 'thumbnail_url');

            $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

            $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
                try {
                    $mediaUrl = $post->media_url;
                    
                    // Jika media_url kosong/null/bukan string
                    if (empty($mediaUrl) || !is_string($mediaUrl)) {
                        return [
                            'post_id' => $post->post_id,
                            'caption' => $post->caption ?? '',
                            'media_url' => null,
                            'is_video' => 0,
                            'thumbnail_url' => null,
                            'created_at' => $post->created_at,
                        ];
                    }

                    $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm|3gp)$/i', $mediaUrl);
                    $thumbnail = $isVideo
                        ? $this->generateThumbnailUrl($mediaUrl, $post)
                        : null;

                    return [
                        'post_id' => $post->post_id,
                        'caption' => $post->caption,
                        'media_url' => $this->normalizeMediaUrl($mediaUrl),
                        'is_video' => $isVideo ? 1 : 0,
                        'thumbnail_url' => $thumbnail,
                        'created_at' => $post->created_at,
                    ];
                } catch (Throwable $e) {
                    Log::error('Failed to map post', [
                        'post_id' => $post->post_id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    
                    return [
                        'post_id' => $post->post_id ?? null,
                        'caption' => 'Error loading post',
                        'media_url' => null,
                        'is_video' => 0,
                        'thumbnail_url' => null,
                        'created_at' => $post->created_at ?? now(),
                    ];
                }
            });

            $pagination = [
                'current_page' => $paginatedPosts->currentPage(),
                'last_page' => $paginatedPosts->lastPage(),
                'per_page' => $paginatedPosts->perPage(),
                'total' => $paginatedPosts->total(),
                'next_page_url' => $paginatedPosts->nextPageUrl(),
            ];
        }

        $hasStory = $canViewPosts && Story::where('user_id', $user->user_id)
            ->where('expires_at', '>', now())
            ->exists();

        return response()->json([
            'user_id' => $user->user_id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'bio' => $user->bio,
            'email' => $user->email,
            'profile_picture_url' => $user->profile_picture_url,
            'banner_url' => $user->banner_url,
            'is_verified' => $user->is_verified,
            'role' => $user->role,
            'is_private' => $user->is_private,
            'has_story' => $hasStory,
            'followers_count' => $user->followers_count,
            'following_count' => $user->following_count,
            'posts_count' => $user->posts_count,
            'recent_posts' => $recentPosts,
            'pagination' => $pagination,
            'message' => $canViewPosts ? null : 'Akun private, follow untuk melihat postingan.',
        ]);
    }

    public function showById(Request $request, $id)
    {
        return response()->json(
            User::select('user_id', 'username', 'full_name', 'profile_picture_url', 'is_verified', 'role')
                ->findOrFail($id)
        );
    }

    // ---------------- Profile sendiri ----------------
    public function me(Request $request)
    {
        $authUser = Auth::user();
        if (! $authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 9);

        $user = User::where('user_id', $authUser->user_id)
            ->withCount([
                'followers as followers_count' => fn ($query) => $query->where('follows.status', 'accepted'),
                'following as following_count' => fn ($query) => $query->where('follows.status', 'accepted'),
                'posts',
            ])
            ->with('followers')
            ->firstOrFail();

        $postsQuery = $user->posts()
            ->latest()
            ->select('post_id', 'caption', 'media_url', 'created_at', 'thumbnail_url');

        $paginatedPosts = $postsQuery->paginate($perPage, ['*'], 'page', $page);

        $recentPosts = $paginatedPosts->getCollection()->map(function ($post) {
            try {
                $mediaUrl = $post->media_url;
                
                if (empty($mediaUrl) || !is_string($mediaUrl)) {
                    return [
                        'post_id' => $post->post_id,
                        'caption' => $post->caption ?? '',
                        'media_url' => null,
                        'is_video' => 0,
                        'thumbnail_url' => null,
                        'created_at' => $post->created_at,
                    ];
                }

                $isVideo = preg_match('/\.(mp4|mov|avi|mkv|webm|3gp)$/i', $mediaUrl);
                $thumbnail = $isVideo
                    ? $this->generateThumbnailUrl($mediaUrl, $post)
                    : null;

                return [
                    'post_id' => $post->post_id,
                    'caption' => $post->caption,
                    'media_url' => $this->normalizeMediaUrl($mediaUrl),
                    'is_video' => $isVideo ? 1 : 0,
                    'thumbnail_url' => $thumbnail,
                    'created_at' => $post->created_at,
                ];
            } catch (Throwable $e) {
                Log::error('Failed to map post in me()', [
                    'post_id' => $post->post_id ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                
                return [
                    'post_id' => $post->post_id ?? null,
                    'caption' => 'Error loading post',
                    'media_url' => null,
                    'is_video' => 0,
                    'thumbnail_url' => null,
                    'created_at' => $post->created_at ?? now(),
                ];
            }
        });

        $pagination = [
            'current_page' => $paginatedPosts->currentPage(),
            'last_page' => $paginatedPosts->lastPage(),
            'per_page' => $paginatedPosts->perPage(),
            'total' => $paginatedPosts->total(),
            'next_page_url' => $paginatedPosts->nextPageUrl(),
        ];

        return response()->json([
            'user_id' => $user->user_id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'bio' => $user->bio,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'profile_picture_url' => $user->profile_picture_url,
            'banner_url' => $user->banner_url,
            'is_verified' => $user->is_verified,
            'role' => $user->role,
            'is_private' => $user->is_private,
            'followers_count' => $user->followers_count,
            'following_count' => $user->following_count,
            'posts_count' => $user->posts_count,
            'recent_posts' => $recentPosts,
            'pagination' => $pagination,
        ]);
    }

    // ---------------- Search user ----------------
    public function search(Request $request)
    {
        $username = $request->input('username');
        $fullName = $request->input('full_name');
        $perPage = (int) $request->input('per_page', 10);

        if (! $username && ! $fullName) {
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
            ->when($request->user(), fn ($q) => $q->where('user_id', '!=', $request->user()->user_id))
            ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
            ->paginate($perPage)
            ->appends([
                'username' => $username,
                'full_name' => $fullName,
                'per_page' => $perPage,
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
        if (! $authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $perPage = (int) $request->input('per_page', 10);

        $followingIds = $authUser->following()->pluck('users.user_id')->toArray();
        $followerIds = $authUser->followers()->pluck('users.user_id')->toArray();

        $mutualIds = array_values(array_intersect($followingIds, $followerIds));

        $mutuals = User::whereIn('user_id', $mutualIds)
            ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url')
            ->paginate($perPage);

        return response()->json($mutuals);
    }

    // ---------------- Helper functions ----------------

    /**
     * Generate thumbnail URL.
     * 
     * NO Storage::exists() - langsung konstruksi URL.
     * Jika file tidak ada, frontend harus handle dengan onError fallback.
     */
    private function generateThumbnailUrl($mediaUrl, $post = null)
    {
        // 1. Database punya thumbnail_url? Pakai itu.
        if ($post && !empty($post->thumbnail_url) && is_string($post->thumbnail_url)) {
            return $this->normalizeMediaUrl($post->thumbnail_url);
        }

        // 2. Safety
        if (empty($mediaUrl) || !is_string($mediaUrl)) {
            return url('/img/video-placeholder-black.jpg');
        }

        // 3. Konstruksi URL thumbnail dari nama file video
        $basename = pathinfo($mediaUrl, PATHINFO_BASENAME);
        $nameOnly = pathinfo($basename, PATHINFO_FILENAME);

        // Langsung return URL, tidak peduli file ada atau tidak
        // Frontend harus handle 404 dengan <img onError={...} />
        return Storage::disk($this->mediaDisk())
            ->url("uploads/posts/thumbnails/{$nameOnly}.jpg");
    }

    /**
     * Normalize media URL.
     * 
     * NO Storage::exists() - langsung konstruksi URL.
     */
    private function normalizeMediaUrl($mediaUrl)
    {
        if (!$mediaUrl || !is_string($mediaUrl)) {
            return null;
        }

        // Already full URL
        if (preg_match('#^https?://#i', $mediaUrl)) {
            return $mediaUrl;
        }

        // /storage/... path
        if (strpos($mediaUrl, '/storage/') === 0) {
            return url($mediaUrl);
        }

        // storage/app/public/... path
        if (strpos($mediaUrl, 'storage/app/public') !== false) {
            $parts = explode('storage/app/public', $mediaUrl);
            $rel = ltrim($parts[1] ?? '', '/\\');
            if (!empty($rel)) {
                return Storage::disk($this->mediaDisk())->url($rel);
            }
        }

        // Absolute server path
        if (strpos($mediaUrl, '/home/') === 0 || strpos($mediaUrl, 'C:\\') === 0) {
            $basename = pathinfo($mediaUrl, PATHINFO_BASENAME);
            return Storage::disk($this->mediaDisk())->url("uploads/posts/{$basename}");
        }

        // Fallback
        $rel = $this->storagePathFromUrl($mediaUrl);
        return Storage::disk($this->mediaDisk())->url($rel);
    }
}