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

class PostController extends Controller
{
public function index()
{
    $authUser = Auth::user();

    $followingIds = $authUser->following()
        ->where('status', 'accepted')
        ->pluck('followed_id');

    // ========== PINNED: Post terbaru dari following ==========
    $pinnedPosts = collect();

    if ($followingIds->isNotEmpty()) {
        $pinnedPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $followingIds)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                $post->type = 'post';
                $post->is_pinned = true;
                return $post;
            });
    }

    // Ambil id post pinned supaya tidak dobel
    $pinnedIds = $pinnedPosts->pluck('post_id');

    // ========== FEED POST (50/10/25/15) ==========
    if ($followingIds->isEmpty()) {
        $allPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds) // hindari duplikat
            ->inRandomOrder()
            ->take(50)
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                $post->type = 'post';
                return $post;
            });
    } else {
        $total = 100;
        $countTimeline  = (int) round($total * 0.50);
        $countRelasi    = (int) round($total * 0.10);
        $countRandom    = (int) round($total * 0.25);
        $countLiked     = (int) round($total * 0.15);

        // 50% timeline
        $timelinePosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $followingIds->push($authUser->user_id))
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds) // hindari duplikat
            ->inRandomOrder()
            ->take($countTimeline)
            ->get();

        // 10% relasi
        $secondDegreeIds = \DB::table('follows')
            ->whereIn('follower_id', $followingIds)
            ->whereNotIn('followed_id', $followingIds)
            ->where('followed_id', '!=', $authUser->user_id)
            ->pluck('followed_id');

        $relasiPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $secondDegreeIds)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->inRandomOrder()
            ->take($countRelasi)
            ->get();

        // 25% random
        $randomPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereNotIn('user_id', $followingIds)
            ->where('user_id', '!=', $authUser->user_id)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->inRandomOrder()
            ->take($countRandom)
            ->get();

        // 15% liked by following
        $likedByFollowingIds = \DB::table('likes')
            ->whereIn('user_id', $followingIds)
            ->pluck('post_id');

        $likedPosts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->whereIn('post_id', $likedByFollowingIds)
            ->whereHas('user', fn($q) => $q->where('is_private', 0))
            ->whereNotIn('post_id', $pinnedIds)
            ->inRandomOrder()
            ->take($countLiked)
            ->get();

        $allPosts = $timelinePosts
            ->merge($relasiPosts)
            ->merge($randomPosts)
            ->merge($likedPosts)
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                $post->type = 'post';
                return $post;
            })
            ->shuffle()
            ->values();
    }

    // ========== SUGGESTION ==========
    $suggestions = collect();

    if ($followingIds->isNotEmpty()) {
        $mutuals = \DB::table('follows')
            ->select('followed_id', \DB::raw('COUNT(*) as mutual_count'))
            ->whereIn('follower_id', $followingIds)
            ->whereNotIn('followed_id', $followingIds)
            ->where('followed_id', '!=', $authUser->user_id)
            ->groupBy('followed_id')
            ->orderByDesc('mutual_count')
            ->take(10)
            ->get();

        $userIds = $mutuals->pluck('followed_id');

        if ($userIds->isNotEmpty()) {
            $users = \App\Models\User::whereIn('user_id', $userIds)
                ->where('is_private', 0)
                ->orderByRaw("FIELD(user_id, " . implode(',', $userIds->toArray()) . ")")
                ->get();

            $suggestions = $suggestions->merge($users);
        }
    }

    if ($suggestions->count() < 10) {
        $need = 10 - $suggestions->count();

        $randomUsers = \App\Models\User::where('user_id', '!=', $authUser->user_id)
            ->whereNotIn('user_id', $followingIds)
            ->whereNotIn('user_id', $suggestions->pluck('user_id'))
            ->where('is_private', 0)
            ->inRandomOrder()
            ->take($need)
            ->get();

        $suggestions = $suggestions->merge($randomUsers);
    }

    $suggestions = $suggestions->map(function ($user) use ($authUser) {
        $isFollowBack = \DB::table('follows')
            ->where('follower_id', $user->user_id)
            ->where('followed_id', $authUser->user_id)
            ->where('status', 'accepted')
            ->exists();

        $user->is_follow_back = $isFollowBack;
        return $user;
    })->sortByDesc('is_follow_back')->values();

    // ========== GABUNGKAN POST + SUGGESTION ==========
    $feed = collect();
    $postCount = 0;

    // Tambahkan pinned posts dulu
    foreach ($pinnedPosts as $pinned) {
        $feed->push($pinned);
        $postCount++;
    }

    // Lanjut isi dengan allPosts + suggestion
    foreach ($allPosts as $post) {
        $feed->push($post);
        $postCount++;

        if ($postCount === 2) {
            $feed->push([
                'type' => 'suggestion',
                'users' => $suggestions->shuffle()->values() // acak tiap kali muncul
            ]);
        }

        if ($postCount > 2 && $postCount % 8 === 0) {
            $feed->push([
                'type' => 'suggestion',
                'users' => $suggestions->shuffle()->values()
            ]);
        }
    }

    return response()->json([
        'feed' => $feed
    ]);
}


    public function explore(Request $request)
    {
        $query = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments']) // ✅ Tambahkan count
            ->where('is_archived', false);

        if ($request->filled('tag')) {
            $tagName = $request->tag;
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('tag_name', $tagName);
            });
        }

        $sort = $request->input('sort', 'random');

        if ($sort === 'popular') {
            $query->orderByDesc('likes_count');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } else {
            $query->inRandomOrder();
        }

        $posts = $query->take(20)->get();

        return response()->json($posts);
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
