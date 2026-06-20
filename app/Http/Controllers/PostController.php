<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use App\Models\PostMention;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use DB;
use Carbon\Carbon;

class PostController extends Controller
{
    /**
     * Tambahkan info story (has_story & story_viewed) ke user
     */
    private function attachStoryInfo($user, $authUser)
    {
        $storyIds = DB::table('stories')
            ->where('user_id', $user->user_id)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->pluck('story_id');

        $user->has_story = $storyIds->isNotEmpty();

        if ($storyIds->isNotEmpty() && $authUser) {
            $viewedCount = DB::table('story_views')
                ->whereIn('story_id', $storyIds)
                ->where('viewer_id', $authUser->user_id)
                ->count();

            $user->story_viewed = ($viewedCount === $storyIds->count());
        } else {
            $user->story_viewed = false;
        }

        return $user;
    }

    public function index(Request $request)
    {
        $authUser = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 2; // pagination per 2

        $followingIds = $authUser->following()
            ->where('status', 'accepted')
            ->pluck('followed_id');

        // ========== MAIN POSTS ==========
        $mainPosts = collect();

        if ($followingIds->isEmpty()) {
            // random feed untuk user yang belum follow siapapun
            $mainPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->whereHas('user', fn($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($post) use ($authUser) {
                    $post->is_liked = (bool) $post->likes()->where('user_id', $authUser->user_id)->exists();
                    $post->is_bookmarked = (bool) $post->bookmarks()->where('user_id', $authUser->user_id)->exists();
                    $post->type = 'post';
                    $post->user = $this->attachStoryInfo($post->user, $authUser);
                    $post->user->is_verified = (bool) $post->user->is_verified;

                    // musik fields (safe)
                    $post->music_track_name        = $post->music_track_name ?? null;
                    $post->music_artist_name       = $post->music_artist_name ?? null;
                    $post->music_preview_url       = $post->music_preview_url ?? null;
                    $post->music_album_art_url     = $post->music_album_art_url ?? null;
                    $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                    $post->music_clip_duration_ms  = $post->music_clip_duration_ms ?? null;

                    // thumbnail safe field
                    $post->thumbnail_url = $post->thumbnail_url ?? null;

                    return $post;
                })
                ->shuffle()
                ->values();
        } else {
            // Distribusi feed
            $total = 100;
            $countTimeline = (int) round($total * 0.50);
            $countRelasi   = (int) round($total * 0.10);
            $countRandom   = (int) round($total * 0.25);
            $countLiked    = (int) round($total * 0.15);

            // Timeline posts (dari following + diri sendiri)
            $timelinePosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->whereIn('user_id', $followingIds->push($authUser->user_id))
                ->whereHas('user', fn($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->take($countTimeline)
                ->get();

            // Second degree (temannya teman)
            $secondDegreeIds = DB::table('follows')
                ->whereIn('follower_id', $followingIds)
                ->whereNotIn('followed_id', $followingIds)
                ->where('followed_id', '!=', $authUser->user_id)
                ->pluck('followed_id');

            $relasiPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->whereIn('user_id', $secondDegreeIds)
                ->whereHas('user', fn($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->take($countRelasi)
                ->get();

            // Random posts
            $randomPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->whereNotIn('user_id', $followingIds)
                ->where('user_id', '!=', $authUser->user_id)
                ->whereHas('user', fn($q) => $q->where('is_private', 0))
                ->inRandomOrder()
                ->take($countRandom)
                ->get();

            // Posts yang disukai following
            $likedByFollowingIds = DB::table('likes')
                ->whereIn('user_id', $followingIds)
                ->pluck('post_id');

            $likedPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->whereIn('post_id', $likedByFollowingIds)
                ->whereHas('user', fn($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->take($countLiked)
                ->get();

            // Shuffle tiap kategori
            $timelinePosts = $timelinePosts->shuffle();
            $relasiPosts   = $relasiPosts->shuffle();
            $randomPosts   = $randomPosts->shuffle();
            $likedPosts    = $likedPosts->shuffle();

            // Gabungkan dan tambahkan fields tambahan
            $mainPosts = $timelinePosts
                ->merge($relasiPosts)
                ->merge($randomPosts)
                ->merge($likedPosts)
                ->map(function ($post) use ($authUser) {
                    $post->is_liked = (bool) $post->likes()->where('user_id', $authUser->user_id)->exists();
                    $post->is_bookmarked = (bool) $post->bookmarks()->where('user_id', $authUser->user_id)->exists();
                    $post->type = 'post';
                    $post->user = $this->attachStoryInfo($post->user, $authUser);
                    $post->user->is_verified = (bool) $post->user->is_verified;

                    $post->music_track_name        = $post->music_track_name ?? null;
                    $post->music_artist_name       = $post->music_artist_name ?? null;
                    $post->music_preview_url       = $post->music_preview_url ?? null;
                    $post->music_album_art_url     = $post->music_album_art_url ?? null;
                    $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                    $post->music_clip_duration_ms  = $post->music_clip_duration_ms ?? null;

                    // thumbnail safe field
                    $post->thumbnail_url = $post->thumbnail_url ?? null;

                    return $post;
                })
                ->shuffle()
                ->values();
        }

        // ========== SUGGESTIONS ==========
        $suggestions = collect();

        if ($followingIds->isNotEmpty()) {
            $mutuals = DB::table('follows')
                ->select('followed_id', DB::raw('COUNT(*) as mutual_count'))
                ->whereIn('follower_id', $followingIds)
                ->whereNotIn('followed_id', $followingIds)
                ->where('followed_id', '!=', $authUser->user_id)
                ->groupBy('followed_id')
                ->orderByDesc('mutual_count')
                ->take(10)
                ->get();

            $userIds = $mutuals->pluck('followed_id');

            if ($userIds->isNotEmpty()) {
                $users = User::whereIn('user_id', $userIds)
                    ->where('is_private', 0)
                    ->orderByRaw("FIELD(user_id, " . implode(',', $userIds->toArray()) . ")")
                    ->get();

                $suggestions = $suggestions->merge($users);
            }
        }

        if ($suggestions->count() < 10) {
            $need = 10 - $suggestions->count();

            $randomUsers = User::where('user_id', '!=', $authUser->user_id)
                ->whereNotIn('user_id', $followingIds)
                ->whereNotIn('user_id', $suggestions->pluck('user_id'))
                ->where('is_private', 0)
                ->inRandomOrder()
                ->take($need)
                ->get();

            $suggestions = $suggestions->merge($randomUsers);
        }

        $suggestions = $suggestions->map(function ($user) use ($authUser) {
            $isFollowBack = DB::table('follows')
                ->where('follower_id', $user->user_id)
                ->where('followed_id', $authUser->user_id)
                ->where('status', 'accepted')
                ->exists();

            $user->is_follow_back = (bool) $isFollowBack;
            $user = $this->attachStoryInfo($user, $authUser);
            $user->is_verified = (bool) $user->is_verified;
            return $user;
        })
        ->shuffle()
        ->sortByDesc('is_follow_back')
        ->values();

        // ========== MERGE POSTS + SUGGESTIONS (FULL FEED DULU) ==========
        $feedWithSuggestions = collect();
        $postCount = 0;
        foreach ($mainPosts as $item) {
            $feedWithSuggestions->push($item);
            $postCount++;
            if ($postCount === 2 || ($postCount > 2 && $postCount % 8 === 0)) {
                $feedWithSuggestions->push((object)[
                    'type' => 'suggestion',
                    'users' => $suggestions->shuffle()
                        ->take(15)
                        ->map(function ($user) {
                            return [
                                'user_id'            => $user->user_id,
                                'username'           => $user->username,
                                'profile_picture_url'=> $user->profile_picture_url,
                                'is_follow_back'     => (bool) $user->is_follow_back,
                                'is_verified'        => (bool) $user->is_verified,
                                'has_story'          => (bool) $user->has_story,
                                'story_viewed'       => (bool) $user->story_viewed,
                            ];
                        })
                        ->values()
                ]);
            }
        }

        // ✅ Sekarang baru di-paginate setelah feed full terbentuk
        $totalFeed = $feedWithSuggestions->count();
        $feedSlice = $feedWithSuggestions
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $feedSlice,
            $totalFeed,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $nextPage = $paginator->currentPage() < $paginator->lastPage()
            ? $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $paginator->currentPage() + 1]))
            : null;

        $prevPage = $paginator->currentPage() > 1
            ? $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $paginator->currentPage() - 1]))
            : null;

        $lastPage = $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $paginator->lastPage()]));

        return response()->json([
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'next_page_url' => $nextPage,
            'prev_page_url' => $prevPage,
            'last_page_url' => $lastPage,
            'feed' => $feedSlice
        ]);
    }

    public function explore(Request $request)
    {
        $authUser = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 15));

        $query = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments'])
            ->where('is_archived', false);

        if ($request->filled('tag')) {
            $tagName = $request->tag;
            $query->whereHas('tags', fn($q) => $q->where('tag_name', $tagName));
        }

        $sort = $request->input('sort', 'random');
        if ($sort === 'popular') $query->orderByDesc('likes_count');
        elseif ($sort === 'newest') $query->orderByDesc('created_at');
        else $query->inRandomOrder();

        $total = $query->count();

        $posts = $query->skip(($page - 1) * $perPage)
                   ->take($perPage)
                   ->get()
                   ->map(function ($post) use ($authUser) {
                        // tambahkan is_liked / is_bookmarked jika ada auth
                        $post->is_liked = $authUser ? $post->likes()->where('user_id', $authUser->user_id)->exists() : false;
                        $post->is_bookmarked = $authUser ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists() : false;
                        $post->type = 'post';
                        $post->user = $this->attachStoryInfo($post->user, $authUser);
                        $post->user->is_verified = (bool) $post->user->is_verified;

                        // musik
                        $post->music_track_name        = $post->music_track_name ?? null;
                        $post->music_artist_name       = $post->music_artist_name ?? null;
                        $post->music_preview_url       = $post->music_preview_url ?? null;
                        $post->music_album_art_url     = $post->music_album_art_url ?? null;
                        $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                        $post->music_clip_duration_ms  = $post->music_clip_duration_ms ?? null;

                        // thumbnail
                        $post->thumbnail_url = $post->thumbnail_url ?? null;

                        return $post;
                   });

        $paginator = new LengthAwarePaginator(
            $posts,
            $total,
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()]
        );

        return response()->json($paginator);
    }

    public function show($id)
    {
        $authUser = Auth::user();

        $post = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->findOrFail($id);

        $owner = $post->user;

        // Cek akses view
        $canView = !$owner->is_private ||
            ($authUser && (
                $authUser->user_id === $owner->user_id ||
                $owner->followers()
                    ->where('follower_id', $authUser->user_id)
                    ->where('status', 'accepted')
                    ->exists()
            ));

        if (!$canView) {
            return response()->json([
                'message' => 'Post ini hanya bisa dilihat oleh followers yang telah diterima.'
            ], 403);
        }

        $post->is_liked = $authUser
            ? $post->likes()->where('user_id', $authUser->user_id)->exists()
            : false;

        $post->is_bookmarked = $authUser
            ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists()
            : false;

        if ($authUser) {
            if ($authUser->user_id === $owner->user_id) {
                $post->user->is_following = true;
            } else {
                $post->user->is_following = $owner->followers()
                    ->where('follower_id', $authUser->user_id)
                    ->where('status', 'accepted')
                    ->exists();
            }
        } else {
            $post->user->is_following = false;
        }

        $post->user = $this->attachStoryInfo($post->user, $authUser);
        $post->user->is_verified = (bool) $post->user->is_verified;

        // musik
        $post->music_track_name        = $post->music_track_name ?? null;
        $post->music_artist_name       = $post->music_artist_name ?? null;
        $post->music_preview_url       = $post->music_preview_url ?? null;
        $post->music_album_art_url     = $post->music_album_art_url ?? null;
        $post->music_start_position_ms = $post->music_start_position_ms ?? null;
        $post->music_clip_duration_ms  = $post->music_clip_duration_ms ?? null;

        // thumbnail
        $post->thumbnail_url = $post->thumbnail_url ?? null;

        return response()->json($post);
    }

    // Buat post baru (media upload pakai file). Menerima optional 'thumbnail' file untuk video.
    public function store(Request $request)
    {
        $request->validate([
            'caption'                 => 'nullable|string',
            'media'                   => 'required|file|mimes:jpg,jpeg,png,mp4,mov,webm,avi,3gp,mkv|max:512000',
            'thumbnail'               => 'nullable|file|mimes:jpg,jpeg,png|max:51200', // up to 50MB thumb jika perlu (ubah sesuai kebijakan)
            'location'                => 'nullable|string',
            'is_archived'             => 'nullable|boolean',
            'is_video'                => 'nullable|boolean',
            // musik fields
            'music_track_name'        => 'nullable|string|max:255',
            'music_artist_name'       => 'nullable|string|max:255',
            'music_preview_url'       => 'nullable|string',
            'music_album_art_url'     => 'nullable|string|max:255',
            'music_start_position_ms' => 'nullable|integer',
            'music_clip_duration_ms'  => 'nullable|string|max:255',
        ]);

        // Simpan file media. Use the configured default disk (r2 in prod,
        // 'public' locally) so it works without cloud credentials.
        $disk = config('filesystems.default');
        $mediaPath = $request->file('media')->store('uploads/posts', $disk);
        $mediaUrl = Storage::disk($disk)->url($mediaPath);

        // Simpan thumbnail jika ada (frontend disarankan mengirim screenshot 1 detik pertama)
        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $request->file('thumbnail')->store('uploads/posts/thumbnails', $disk);
            $thumbnailUrl = Storage::disk($disk)->url($thumbPath);
        }

        $post = Post::create([
            'user_id'                 => Auth::id(),
            'caption'                 => $request->caption,
            'media_url'               => $mediaUrl,
            'thumbnail_url'           => $thumbnailUrl,
            'location'                => $request->location,
            'is_archived'             => $request->is_archived ?? false,
            'is_video'                => $request->is_video ?? false,
            // musik
            'music_track_name'        => $request->music_track_name,
            'music_artist_name'       => $request->music_artist_name,
            'music_preview_url'       => $request->music_preview_url,
            'music_album_art_url'     => $request->music_album_art_url,
            'music_start_position_ms' => $request->music_start_position_ms,
            'music_clip_duration_ms'  => $request->music_clip_duration_ms,
        ]);

        // Tangani hashtag
        if ($request->filled('caption')) {
            preg_match_all('/#(\w+)/', $request->caption, $tags);
            foreach ($tags[1] as $tagName) {
                $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->tag_id);
            }
        }

        // Tangani mention
        if ($request->filled('caption')) {
            preg_match_all('/@(\w+)/', $request->caption, $mentions);
            foreach ($mentions[1] as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser && $mentionedUser->user_id !== Auth::id()) {
                    PostMention::create([
                        'post_id' => $post->post_id,
                        'mentioned_user_id' => $mentionedUser->user_id
                    ]);
                    Notification::create([
                        'recipient_id'     => $mentionedUser->user_id,
                        'type'             => 'mention',
                        'related_user_id'  => Auth::id(),
                        'related_post_id'  => $post->post_id,
                        'created_at'       => now(),
                        'is_read'          => false,
                    ]);
                }
            }
        }

        // Notifikasi ke followers (logika original dipertahankan; gunakan post_id konsisten)
        $author = $post->user;
        $followers = $author->followers()->withPivot('followed_at')->get();

        foreach ($followers as $follower) {
            $postCountSinceFollow = Post::where('user_id', $author->user_id)
                ->where('created_at', '>=', $follower->pivot->followed_at)
                ->count();

            if ($postCountSinceFollow <= 2) {
                $notification = Notification::create([
                    'recipient_id'    => $follower->user_id,
                    'type'            => 'new_post',
                    'related_user_id' => $author->user_id,
                    'related_post_id' => $post->post_id,
                ]);
                broadcast(new \App\Events\NotificationCreated($notification));
            }
        }

        return response()->json([
            'message' => 'Post created',
            'post' => $post
        ], 201);
    }

    // Edit post (media optional)
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'caption'                 => 'nullable|string',
            'media'                   => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,webm,avi,3gp,mkv|max:512000',
            'thumbnail'               => 'nullable|file|mimes:jpg,jpeg,png|max:51200',
            'location'                => 'nullable|string',
            'is_archived'             => 'nullable|boolean',
            'is_video'                => 'nullable|boolean',
            // musik
            'music_track_name'        => 'nullable|string|max:255',
            'music_artist_name'       => 'nullable|string|max:255',
            'music_preview_url'       => 'nullable|string',
            'music_album_art_url'     => 'nullable|string|max:255',
            'music_start_position_ms' => 'nullable|integer',
            'music_clip_duration_ms'  => 'nullable|string|max:255',
        ]);

        // Replace media jika ada
        if ($request->hasFile('media')) {
            if ($post->media_url) {
                $path = ltrim(parse_url($post->media_url, PHP_URL_PATH), '/');
                Storage::disk('r2')->delete($path);
            }
            $mediaPath = $request->file('media')->store('uploads/posts', 'r2');
            $post->media_url = Storage::disk('r2')->url($mediaPath);
        }

        // Replace thumbnail jika ada
        if ($request->hasFile('thumbnail')) {
            if ($post->thumbnail_url) {
                $thumbPath = ltrim(parse_url($post->thumbnail_url, PHP_URL_PATH), '/');
                Storage::disk('r2')->delete($thumbPath);
            }
            $thumbPathNew = $request->file('thumbnail')->store('uploads/posts/thumbnails', 'r2');
            $post->thumbnail_url = Storage::disk('r2')->url($thumbPathNew);
        }

        // Update fields
        $post->caption     = $request->caption ?? $post->caption;
        $post->location    = $request->location ?? $post->location;
        $post->is_archived = $request->is_archived ?? $post->is_archived;
        $post->is_video    = $request->is_video ?? $post->is_video;

        // musik
        $post->music_track_name        = $request->music_track_name ?? $post->music_track_name;
        $post->music_artist_name       = $request->music_artist_name ?? $post->music_artist_name;
        $post->music_preview_url       = $request->music_preview_url ?? $post->music_preview_url;
        $post->music_album_art_url     = $request->music_album_art_url ?? $post->music_album_art_url;
        $post->music_start_position_ms = $request->music_start_position_ms ?? $post->music_start_position_ms;
        $post->music_clip_duration_ms  = $request->music_clip_duration_ms ?? $post->music_clip_duration_ms;

        $post->save();

        return response()->json([
            'message' => 'Post updated',
            'post' => $post
        ]);
    }

    // Hapus post
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Hapus media
        if ($post->media_url) {
            $path = ltrim(parse_url($post->media_url, PHP_URL_PATH), '/');
            Storage::disk('r2')->delete($path);
        }

        // Hapus thumbnail jika ada
        if ($post->thumbnail_url) {
            $thumbPath = ltrim(parse_url($post->thumbnail_url, PHP_URL_PATH), '/');
            Storage::disk('r2')->delete($thumbPath);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }

    public function circleAvatar($id)
    {
        $authUser = Auth::user();
        $user = User::select('user_id', 'profile_picture_url')->findOrFail($id);

        // Tambahkan info story
        $user = $this->attachStoryInfo($user, $authUser);

        return response()->json([
            'circle_avatar' => $user
        ]);
    }

    public function clips($id, Request $request)
    {
        $authUser = Auth::user();

        // Ambil daftar exclude dari query param (kalau ada)
        $excludeIds = $request->input('exclude', []);
        if (!is_array($excludeIds)) {
            $excludeIds = [$excludeIds];
        }

        // Pastikan clip utama juga di-exclude dari next
        $excludeIds[] = $id;

        // Ambil clip utama
        $mainClip = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments'])
            ->where('is_video', true)
            ->where('is_archived', false)
            ->findOrFail($id);

        $mainClip->is_liked = $authUser
            ? $mainClip->likes()->where('user_id', $authUser->user_id)->exists()
            : false;

        $mainClip->is_bookmarked = $authUser
            ? $mainClip->bookmarks()->where('user_id', $authUser->user_id)->exists()
            : false;

        $mainClip->type = 'clip';
        $mainClip->user = $this->attachStoryInfo($mainClip->user, $authUser);
        $mainClip->user->is_verified = (bool) $mainClip->user->is_verified;

        // musik + thumbnail untuk mainClip
        $mainClip->music_track_name        = $mainClip->music_track_name ?? null;
        $mainClip->music_artist_name       = $mainClip->music_artist_name ?? null;
        $mainClip->music_preview_url       = $mainClip->music_preview_url ?? null;
        $mainClip->music_album_art_url     = $mainClip->music_album_art_url ?? null;
        $mainClip->music_start_position_ms = $mainClip->music_start_position_ms ?? null;
        $mainClip->music_clip_duration_ms  = $mainClip->music_clip_duration_ms ?? null;
        $mainClip->thumbnail_url           = $mainClip->thumbnail_url ?? null;

        // Ambil 1 clip random, exclude id utama + exclude dari param
        $nextClips = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments'])
            ->where('is_video', true)
            ->where('is_archived', false)
            ->whereNotIn('post_id', $excludeIds)
            ->inRandomOrder()
            ->take(1)
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $authUser
                    ? $post->likes()->where('user_id', $authUser->user_id)->exists()
                    : false;

                $post->is_bookmarked = $authUser
                    ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists()
                    : false;

                $post->type = 'clip';
                $post->user = $this->attachStoryInfo($post->user, $authUser);
                $post->user->is_verified = (bool) $post->user->is_verified;

                // musik + thumbnail
                $post->music_track_name        = $post->music_track_name ?? null;
                $post->music_artist_name       = $post->music_artist_name ?? null;
                $post->music_preview_url       = $post->music_preview_url ?? null;
                $post->music_album_art_url     = $post->music_album_art_url ?? null;
                $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                $post->music_clip_duration_ms  = $post->music_clip_duration_ms ?? null;
                $post->thumbnail_url           = $post->thumbnail_url ?? null;

                return $post;
            });

        // Update exclude list dengan id baru yang sudah dipakai
        $newExcludeIds = array_merge($excludeIds, $nextClips->pluck('post_id')->toArray());

        // Buat next_page_url, bawa exclude list biar ga duplikat
        $lastId = $nextClips->last()?->post_id;
        $nextPageUrl = $lastId
            ? url('/api/clips/' . $lastId . '?' . http_build_query(['exclude' => $newExcludeIds]))
            : null;

        return response()->json([
            'clip' => $mainClip,
            'next_clips' => $nextClips,
            'next_page_url' => $nextPageUrl
        ]);
    }
}
