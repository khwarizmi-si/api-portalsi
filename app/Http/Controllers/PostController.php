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

class PostController extends Controller
{
    /**
     * Tambahkan info story (has_story & story_viewed) ke user
     */
    private function attachStoryInfo($user, $authUser)
    {
        $storyIds = DB::table('stories')
            ->where('user_id', $user->user_id)
            ->pluck('story_id');

        $user->has_story = $storyIds->isNotEmpty();

        if ($storyIds->isNotEmpty()) {
            $viewedCount = DB::table('story_views')
                ->whereIn('story_id', $storyIds)
                ->where('viewer_id', $authUser->user_id)
                ->count();

            // Jika jumlah story yang dilihat = jumlah story → sudah semua dilihat
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
    $perPage = max(1, (int) $request->input('per_page', 10));

    $followingIds = $authUser->following()
        ->where('status', 'accepted')
        ->pluck('followed_id');

    // ========== PINNED POSTS ==========
    $pinnedPosts = collect();
    if ($page === 1 && $followingIds->isNotEmpty()) {
        $pinnedPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $followingIds)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                $post->is_bookmarked = $post->bookmarks()->where('user_id', $authUser->user_id)->exists();
                $post->type = 'post';
                $post->is_pinned = true;

                // tambahkan story info
                $post->user = $this->attachStoryInfo($post->user, $authUser);

                return $post;
            })
            ->shuffle(); // 🔥 pinned acak
    }
    $pinnedIds = $pinnedPosts->pluck('post_id');

    // ========== MAIN POSTS ==========
    $mainPosts = collect();

    if ($followingIds->isEmpty()) {
        $mainPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                $post->is_bookmarked = $post->bookmarks()->where('user_id', $authUser->user_id)->exists();
                $post->type = 'post';

                $post->user = $this->attachStoryInfo($post->user, $authUser);
                return $post;
            })
            ->shuffle() // 🔥 acak feed non-following
            ->values();
    } else {
        // distribusi feed
        $total = 100;
        $countTimeline = (int) round($total * 0.50);
        $countRelasi   = (int) round($total * 0.10);
        $countRandom   = (int) round($total * 0.25);
        $countLiked    = (int) round($total * 0.15);

        $timelinePosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $followingIds->push($authUser->user_id))
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->orderByDesc('created_at')
            ->take($countTimeline)
            ->get();

        $secondDegreeIds = DB::table('follows')
            ->whereIn('follower_id', $followingIds)
            ->whereNotIn('followed_id', $followingIds)
            ->where('followed_id', '!=', $authUser->user_id)
            ->pluck('followed_id');

        $relasiPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $secondDegreeIds)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->orderByDesc('created_at')
            ->take($countRelasi)
            ->get();

        $randomPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereNotIn('user_id', $followingIds)
            ->where('user_id', '!=', $authUser->user_id)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->inRandomOrder()
            ->take($countRandom)
            ->get();

        $likedByFollowingIds = DB::table('likes')
            ->whereIn('user_id', $followingIds)
            ->pluck('post_id');

        $likedPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('post_id', $likedByFollowingIds)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->orderByDesc('created_at')
            ->take($countLiked)
            ->get();

        // 🔥 Shuffle setiap kategori
        $timelinePosts = $timelinePosts->shuffle();
        $relasiPosts   = $relasiPosts->shuffle();
        $randomPosts   = $randomPosts->shuffle();
        $likedPosts    = $likedPosts->shuffle();

        $mainPosts = $timelinePosts
            ->merge($relasiPosts)
            ->merge($randomPosts)
            ->merge($likedPosts)
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                $post->is_bookmarked = $post->bookmarks()->where('user_id', $authUser->user_id)->exists();
                $post->type = 'post';

                $post->user = $this->attachStoryInfo($post->user, $authUser);
                return $post;
            })
            ->shuffle() // 🔥 acak lagi setelah merge
            ->values();
    }

    // ========== PAGINATION ==========
    $totalMainPosts = $mainPosts->count();
    $postsSlice = $mainPosts
        ->slice(($page - 1) * $perPage, $perPage)
        ->values();

    $paginator = new LengthAwarePaginator(
        $postsSlice,
        $totalMainPosts,
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

        $user->is_follow_back = $isFollowBack;
        $user = $this->attachStoryInfo($user, $authUser);
        return $user;
    })
    ->shuffle() // 🔥 suggestions acak setiap refresh
    ->sortByDesc('is_follow_back')
    ->values();

    // ========== MERGE POSTS + SUGGESTIONS ==========
    $feed = collect();
    if ($page === 1) {
        $feed = $feed->merge($pinnedPosts);
    }
    $feed = $feed->merge($postsSlice)->values();

    $feedWithSuggestions = collect();
    $postCount = 0;
    foreach ($feed as $item) {
        $feedWithSuggestions->push($item);
        $postCount++;
        if ($postCount === 2 || ($postCount > 2 && $postCount % 8 === 0)) {
            $feedWithSuggestions->push((object)[
                'type' => 'suggestion',
                'users' => $suggestions->shuffle()->take(15)->values()
            ]);
        }
    }

    return response()->json([
        'current_page' => $paginator->currentPage(),
        'per_page' => $paginator->perPage(),
        'total' => $paginator->total(),
        'next_page_url' => $nextPage,
        'prev_page_url' => $prevPage,
        'last_page_url' => $lastPage,
        'feed' => $feedWithSuggestions
    ]);
}


public function explore(Request $request)
{
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
                   ->get();

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

        // 🔒 Cek apakah post bisa dilihat
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

        // ❤️ Tambahkan informasi apakah sudah di-like / di-bookmark oleh user login
        $post->is_liked = $authUser 
            ? $post->likes()->where('user_id', $authUser->user_id)->exists()
            : false;

        $post->is_bookmarked = $authUser 
            ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists()
            : false;

        return response()->json($post);
    }

    // 🆕 Buat post baru (media upload pakai file)
    public function store(Request $request)
    {
        $request->validate([
            'caption'     => 'nullable|string',
            'media' => 'required|file|mimes:jpg,jpeg,png,mp4,mov,webm,avi,3gp,mkv|max:512000',
            'location'    => 'nullable|string',
            'is_archived' => 'nullable|boolean',
            'is_video'    => 'nullable|boolean',
        ]);

        // 🗂 Simpan file ke storage
        $mediaPath = $request->file('media')->store('uploads/posts', 'public');
        $mediaUrl = asset('storage/' . $mediaPath);

        $post = Post::create([
            'user_id'     => Auth::id(),
            'caption'     => $request->caption,
            'media_url'   => $mediaUrl,
            'location'    => $request->location,
            'is_archived' => $request->is_archived ?? false,
            'is_video'    => $request->is_video ?? false,
        ]);

        // 🎯 Tangani hashtag
        if ($request->filled('caption')) {
            preg_match_all('/#(\w+)/', $request->caption, $tags);
            foreach ($tags[1] as $tagName) {
                $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->tag_id);
            }
        }

        // 👥 Tangani mention
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

        return response()->json([
            'message' => 'Post created',
            'post' => $post
        ], 201);
    }

    // ✏️ Edit post (media optional)
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'caption'     => 'nullable|string',
            'media'       => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,webm,avi,3gp,mkv|max:512000',
            'location'    => 'nullable|string',
            'is_archived' => 'nullable|boolean',
            'is_video'    => 'nullable|boolean',
        ]);

        // 🔄 Ganti file jika ada media baru
        if ($request->hasFile('media')) {
            // Hapus media lama (opsional)
            if ($post->media_url) {
                $path = str_replace(asset('storage') . '/', '', $post->media_url);
                Storage::disk('public')->delete($path);
            }
            $mediaPath = $request->file('media')->store('uploads/posts', 'public');
            $post->media_url = asset('storage/' . $mediaPath);
        }

        // Update field lainnya
        $post->caption = $request->caption ?? $post->caption;
        $post->location = $request->location ?? $post->location;
        $post->is_archived = $request->is_archived ?? $post->is_archived;
        $post->is_video = $request->is_video ?? $post->is_video;
        $post->save();

        return response()->json([
            'message' => 'Post updated',
            'post' => $post
        ]);
    }

    // 🗑 Hapus post
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Hapus media
        if ($post->media_url) {
            $path = str_replace(asset('storage') . '/', '', $post->media_url);
            Storage::disk('public')->delete($path);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }
}
